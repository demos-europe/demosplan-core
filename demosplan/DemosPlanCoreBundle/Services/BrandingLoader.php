<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\Branding;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\BrandingProvider;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\ValueObject\BrandingValueObject;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Symfony\Component\HttpFoundation\Request;

/**
 * Chooses the right logo and branding styles for every request.
 */
class BrandingLoader
{
    /** @var OrgaService */
    private $orgaService;
    /** @var ProcedureService */
    private $procedureService;
    /** @var CustomerHandler */
    private $customerHandler;
    /** @var GlobalConfigInterface */
    private $globalConfig;
    /**
     * @var BrandingProvider
     */
    private $brandingProvider;

    public function __construct(
        BrandingProvider $brandingProvider,
        CustomerHandler $customerHandler,
        GlobalConfigInterface $globalConfig,
        OrgaService $orgaService,
        ProcedureService $procedureService)
    {
        $this->orgaService = $orgaService;
        $this->procedureService = $procedureService;
        $this->customerHandler = $customerHandler;
        $this->globalConfig = $globalConfig;
        $this->brandingProvider = $brandingProvider;
    }

    /**
     * Returns an object with Customer, Orga and predefined styles, to be used by Frontend.
     */
    public function getBrandingObject(Request $request): BrandingValueObject
    {
        $brandingContainer = new BrandingValueObject();
        try {
            $brandingContainer = $this->setCustomerBranding($brandingContainer);
        } catch (CustomerNotFoundException $e) {
            // no customer, no customer branding
        }

        try {
            $orga = $this->getOrga($request);
        } catch (NoResultException|NonUniqueResultException|Exception $e) {
            $orga = null;
        }

        $brandingContainer = $this->setOrgaBranding($brandingContainer, $orga);

        $brandingContainer->lock();

        return $brandingContainer;
    }

    /**
     * @throws CustomerNotFoundException
     */
    private function setCustomerBranding(BrandingValueObject $brandingContainer): BrandingValueObject
    {
        $customer = $this->getCustomer();
        $brandingContainer->setCustomerLogo(null);
        $brandingContainer->setCustomerSubdomain('');
        $brandingContainer->setCustomerCss(null);

        if ($customer instanceof Customer) {
            $brandingContainer->setCustomerSubdomain($customer->getSubdomain());
            $currentCustomerBranding = $customer->getBranding();
            if ($currentCustomerBranding instanceof Branding) {
                $brandingContainer->setCustomerCss($this->brandingProvider->generateFullCss($customer));
                $brandingContainer->setCustomerLogo($currentCustomerBranding->getLogo());
            }
        }

        return $brandingContainer;
    }

    private function setOrgaBranding(BrandingValueObject $brandingContainer, ?Orga $orga): BrandingValueObject
    {
        $brandingContainer->setOrgaId('');
        $brandingContainer->setOrgaLogo(null);
        $brandingContainer->setOrgaCss(null);

        if ($orga instanceof Orga) {
            $brandingContainer->setOrgaLogo($orga->getLogo());
            $brandingContainer->setOrgaId($orga->getId());
            if (null !== $orga->getBranding()) {
                $orgaCss = $this->brandingProvider->generateFullCss($orga);
                $brandingContainer->setOrgaCss($orgaCss);
            }
        }

        return $brandingContainer;
    }

    /**
     * @return Customer|null
     *
     * @throws CustomerNotFoundException
     */
    private function getCustomer()
    {
        $subdomain = $this->globalConfig->getSubdomain();

        return $this->customerHandler->findCustomerBySubdomain($subdomain);
    }

    /**
     * @return Orga|null
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws Exception
     */
    private function getOrga(Request $request)
    {
        $orga = null;

        if ($this->isOrgaBrandedRoute($request)) {
            $orga = $this->getOrgaFromProcedureId($request->get('procedure'));

            if (null === $orga) {
                $orga = $this->getOrgaFromOrgaSlug($request->get('orgaSlug'));
            }
        }

        return $orga;
    }

    /**
     * Returns true or false based on whether current request belongs to some orga branded page.
     */
    private function isOrgaBrandedRoute(Request $request): bool
    {
        return in_array($request->get('_route'), $this->globalConfig->getOrgaBrandedRoutes(), true);
    }

    /**
     * @param string|null $procedureId
     *
     * @return Orga|null
     *
     * @throws Exception
     */
    private function getOrgaFromProcedureId($procedureId)
    {
        if (null !== $procedureId) {
            $procedure = $this->procedureService->getProcedure($procedureId);
            if (null !== $procedure) {
                return $procedure->getOrga();
            }
        }

        return null;
    }

    /**
     * @param string|null $orgaSlug
     *
     * @return Orga|null
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    private function getOrgaFromOrgaSlug($orgaSlug)
    {
        if (null !== $orgaSlug) {
            return $this->orgaService->findOrgaBySlug($orgaSlug);
        }

        return null;
    }
}
