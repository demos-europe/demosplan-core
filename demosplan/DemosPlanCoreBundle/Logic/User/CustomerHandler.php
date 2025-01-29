<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Branding;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\ValueObject\User\CustomerResourceInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function array_key_exists;

class CustomerHandler extends CoreHandler
{
    /**
     * @var CustomerService
     */
    protected $customerService;

    public function __construct(
        CustomerService $customerService,
        MessageBagInterface $messageBag,
        private readonly PermissionsInterface $permissions,
        private readonly ValidatorInterface $validator)
    {
        $this->customerService = $customerService;
        parent::__construct($messageBag);
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function findCustomerBySubdomain(string $subdomain): Customer
    {
        return $this->customerService->findCustomerBySubdomain($subdomain);
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function getCurrentCustomer(): Customer
    {
        return $this->customerService->getCurrentCustomer();
    }

    /**
     * @return Customer updated Customer
     *
     * @throws CustomerNotFoundException
     * @throws ViolationsException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateCustomer(CustomerResourceInterface $customerUpdateData): Customer
    {
        $groups = array_keys($customerUpdateData->getActiveGetters());
        $constraintViolationList = $this->validator->validate($customerUpdateData, null, $groups);

        if (0 !== $constraintViolationList->count()) {
            throw ViolationsException::fromConstraintViolationList($constraintViolationList);
        }

        $currentCustomer = $this->getCurrentCustomer();
        $activeGetters = $customerUpdateData->getActiveGetters();

        if ([] !== $activeGetters) {
            if (array_key_exists(CustomerResourceInterface::IMPRINT, $activeGetters)
                && $this->permissions->hasPermission('field_imprint_text_customized_edit_customer')
            ) {
                $currentCustomer->setImprint($customerUpdateData->getImprint());
            }
            if (array_key_exists(CustomerResourceInterface::DATA_PROTECTION, $activeGetters)
                && $this->permissions->hasPermission('field_data_protection_text_customized_edit_customer')
            ) {
                $currentCustomer->setDataProtection($customerUpdateData->getDataProtection());
            }
            if (array_key_exists(CustomerResourceInterface::LOGO, $activeGetters)) {
                $branding = $currentCustomer->getBranding() ?? new Branding();
                $branding->setLogo($customerUpdateData->getLogo());
                $currentCustomer->setBranding($branding);
            }
            if (array_key_exists(CustomerResourceInterface::MAP_ATTRIBUTION, $activeGetters)) {
                $currentCustomer->setMapAttribution($customerUpdateData->getMapAttribution());
            }
            if (array_key_exists(CustomerResourceInterface::BASE_LAYER_URL, $activeGetters)) {
                $currentCustomer->setBaseLayerUrl($customerUpdateData->getBaseLayerUrl());
            }
            if (array_key_exists(CustomerResourceInterface::BASE_LAYER_LAYERS, $activeGetters)) {
                $currentCustomer->setBaseLayerLayers($customerUpdateData->getBaseLayerLayers());
            }
            if (array_key_exists(CustomerResourceInterface::TERMS_OF_USE, $activeGetters)
                && $this->permissions->hasPermission('feature_customer_terms_of_use_edit')
            ) {
                $currentCustomer->setTermsOfUse($customerUpdateData->getTermsOfUse());
            }
            if (array_key_exists(CustomerResourceInterface::XPLANNING, $activeGetters)
                && $this->permissions->hasPermission('feature_customer_xplanning_edit')
            ) {
                $currentCustomer->setXplanning($customerUpdateData->getXplanning());
            }
            if (array_key_exists(CustomerResourceInterface::STYLING, $activeGetters)
                && $this->permissions->hasPermission('feature_customer_branding_edit')
            ) {
                $branding = $currentCustomer->getBranding() ?? new Branding();
                $branding->setCssvars($customerUpdateData->getCssVars());
                $currentCustomer->setBranding($branding);
            }
            if (array_key_exists(CustomerResourceInterface::ACCESSIBILITY_EXPLANATION, $activeGetters)
                && $this->permissions->hasPermission('field_customer_accessibility_explanation_edit')
            ) {
                $currentCustomer->setAccessibilityExplanation($customerUpdateData->getAccessibilityExplanation());
            }
            if (array_key_exists(CustomerResourceInterface::SIMPLE_LANGUAGE_OVERVIEW_DESCRIPTION, $activeGetters)) {
                $currentCustomer->setOverviewDescriptionInSimpleLanguage($customerUpdateData->getOverviewDescriptionInSimpleLanguage());
            }

            if (array_key_exists(CustomerResourceInterface::SIGN_LANGUAGE_OVERVIEW_DESCRIPTION, $activeGetters)) {
                $currentCustomer->setSignLanguageOverviewDescription($customerUpdateData->getSignLanguageOverviewDescription());
            }

            $this->validator->validate($currentCustomer);

            $currentCustomer = $this->customerService->updateCustomer($currentCustomer);
        }

        return $currentCustomer;
    }
}
