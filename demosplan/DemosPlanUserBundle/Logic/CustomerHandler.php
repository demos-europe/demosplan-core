<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanUserBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use demosplan\DemosPlanCoreBundle\Entity\Branding;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Logic\MessageBag;
use demosplan\DemosPlanUserBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanUserBundle\ValueObject\CustomerInterface;
use function array_key_exists;

class CustomerHandler extends CoreHandler
{
    /**
     * @var CustomerService
     */
    protected $customerService;
    /**
     * @var PermissionsInterface
     */
    private $permissions;
    /**
     * @var ValidatorInterface
     */
    private $validator;

    public function __construct(
        CustomerService $customerService,
        MessageBag $messageBag,
        PermissionsInterface $permissions,
        ValidatorInterface $validator)
    {
        $this->customerService = $customerService;
        parent::__construct($messageBag);
        $this->permissions = $permissions;
        $this->validator = $validator;
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
    public function updateCustomer(CustomerInterface $customerUpdateData): Customer
    {
        $groups = array_keys($customerUpdateData->getActiveGetters());
        $constraintViolationList = $this->validator->validate($customerUpdateData, null, $groups);

        if (0 !== $constraintViolationList->count()) {
            throw ViolationsException::fromConstraintViolationList($constraintViolationList);
        }

        $currentCustomer = $this->getCurrentCustomer();
        $activeGetters = $customerUpdateData->getActiveGetters();

        if ([] !== $activeGetters) {
            if (array_key_exists(CustomerInterface::IMPRINT, $activeGetters)
                && $this->permissions->hasPermission('field_imprint_text_customized_edit_customer')
            ) {
                $currentCustomer->setImprint($customerUpdateData->getImprint());
            }
            if (array_key_exists(CustomerInterface::DATA_PROTECTION, $activeGetters)
                && $this->permissions->hasPermission('field_data_protection_text_customized_edit_customer')
            ) {
                $currentCustomer->setDataProtection($customerUpdateData->getDataProtection());
            }
            if (array_key_exists(CustomerInterface::LOGO, $activeGetters)) {
                $branding = $currentCustomer->getBranding() ?? new Branding();
                $branding->setLogo($customerUpdateData->getLogo());
                $currentCustomer->setBranding($branding);
            }
            if (array_key_exists(CustomerInterface::MAP_ATTRIBUTION, $activeGetters)) {
                $currentCustomer->setMapAttribution($customerUpdateData->getMapAttribution());
            }
            if (array_key_exists(CustomerInterface::BASE_LAYER_URL, $activeGetters)) {
                $currentCustomer->setBaseLayerUrl($customerUpdateData->getBaseLayerUrl());
            }
            if (array_key_exists(CustomerInterface::BASE_LAYER_LAYERS, $activeGetters)) {
                $currentCustomer->setBaseLayerLayers($customerUpdateData->getBaseLayerLayers());
            }
            if (array_key_exists(CustomerInterface::TERMS_OF_USE, $activeGetters)
                && $this->permissions->hasPermission('feature_customer_terms_of_use_edit')
            ) {
                $currentCustomer->setTermsOfUse($customerUpdateData->getTermsOfUse());
            }
            if (array_key_exists(CustomerInterface::XPLANNING, $activeGetters)
                && $this->permissions->hasPermission('feature_customer_xplanning_edit')
            ) {
                $currentCustomer->setXplanning($customerUpdateData->getXplanning());
            }
            if (array_key_exists(CustomerInterface::STYLING, $activeGetters)
                && $this->permissions->hasPermission('feature_customer_branding_edit')
            ) {
                $branding = $currentCustomer->getBranding() ?? new Branding();
                $branding->setCssvars($customerUpdateData->getCssVars());
                $currentCustomer->setBranding($branding);
            }
            if (array_key_exists(CustomerInterface::ACCESSIBILITY_EXPLANATION, $activeGetters)
                && $this->permissions->hasPermission('field_customer_accessibility_explanation_edit')
            ) {
                $currentCustomer->setAccessibilityExplanation($customerUpdateData->getAccessibilityExplanation());
            }
            if (array_key_exists(CustomerInterface::SIMPLE_LANGUAGE_OVERVIEW_DESCRIPTION, $activeGetters)) {
                $currentCustomer->setOverviewDescriptionInSimpleLanguage($customerUpdateData->getOverviewDescriptionInSimpleLanguage());
            }

            if (array_key_exists(CustomerInterface::SIGN_LANGUAGE_OVERVIEW_DESCRIPTION, $activeGetters)) {
                $currentCustomer->setSignLanguageOverviewDescription($customerUpdateData->getSignLanguageOverviewDescription());
            }

            $this->validator->validate($currentCustomer);

            $currentCustomer = $this->customerService->updateCustomer($currentCustomer);
        }

        return $currentCustomer;
    }
}
