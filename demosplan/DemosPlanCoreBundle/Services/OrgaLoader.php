<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services;

use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\ValueObject\OrgaValueObject;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Symfony\Component\HttpFoundation\Request;

class OrgaLoader
{
    /** @var OrgaService */
    private $orgaService;

    /** @var ProcedureService */
    private $procedureService;

    public function __construct(OrgaService $orgaService, ProcedureService $procedureService)
    {
        $this->orgaService = $orgaService;
        $this->procedureService = $procedureService;
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
        $orga = $this->getOrgaFromProcedureId($request->get('procedure'));
        if (null === $orga) {
            $orga = $this->getOrgaFromOrgaSlug($request->get('orgaSlug'));
        }

        return $orga;
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

    /**
     * Returns an object with Customer, Orga and predefined styles, to be used by Frontend.
     *
     * @return OrgaValueObject|null
     */
    public function getOrgaObject(Request $request)
    {
        $orgaObject = null;
        try {
            $orga = $this->getOrga($request);
        } catch (NoResultException|NonUniqueResultException|Exception $e) {
            $orga = null;
        }

        if ($orga instanceof Orga) {
            $orgaObject = new OrgaValueObject();
            $orgaObject->setName($orga->getName());
            $orgaObject->setId($orga->getId());
            $orgaObject->lock();
        }

        return $orgaObject;
    }
}
