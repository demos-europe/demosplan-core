<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Branding;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\SupportContact;
use demosplan\DemosPlanCoreBundle\Entity\Video;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\BrandingRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\CustomerResourceConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Webmozart\Assert\Assert;

/**
 * @template-extends DplanResourceType<Customer>
 *
 * @property-read End                                       $name
 * @property-read End                                       $subdomain
 * @property-read End                                       $signLanguageOverviewDescription
 * @property-read End                                       $overviewDescriptionInSimpleLanguage
 * @property-read End                                       $imprint
 * @property-read SignLanguageOverviewVideoResourceType     $signLanguageOverviewVideo
 * @property-read SignLanguageOverviewVideoResourceType     $signLanguageOverviewVideos
 * @property-read BrandingResourceType                      $branding
 * @property-read End                                       $dataProtection
 * @property-read End                                       $termsOfUse
 * @property-read End                                       $xplanning
 * @property-read End                                       $accessibilityExplanation
 * @property-read End                                       $baseLayerUrl
 * @property-read End                                       $baseLayerLayers
 * @property-read End                                       $mapAttribution
 * @property-read CustomerContactResourceType               $contacts
 * @property-read CustomerContactResourceType               $customerContacts
 * @property-read CustomerLoginSupportContactResourceType   $customerLoginSupportContact
 */
final class CustomerResourceType extends DplanResourceType
{
    public function __construct(
        protected readonly BrandingRepository $brandingRepository,
        protected readonly CustomerLoginSupportContactResourceType $customerLoginSupportContactResourceType,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function getEntityClass(): string
    {
        return Customer::class;
    }

    public static function getName(): string
    {
        return 'Customer';
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'area_manage_orgadata',
            'area_manage_orgas',
            'area_manage_orgas_all',
            'area_organisations',
            'area_report_mastertoeblist',
            'feature_platform_logo_edit',
            'feature_customer_branding_edit',
            'feature_imprint_text_customized_view',
            'feature_data_protection_text_customized_view',
            'feature_customer_terms_of_use_edit',
            'feature_customer_xplanning_edit',
            'feature_organisation_user_list',
            'field_customer_accessibility_explanation_edit',
            'field_sign_language_overview_video_edit',
            'field_simple_language_overview_description_edit',
            'feature_customer_login_support_contact_administration',
            'feature_customer_support_contact_administration'
        );
    }

    protected function getAccessConditions(): array
    {
        $currentCustomerId = $this->currentCustomerService->getCurrentCustomer()->getId();
        if (null === $currentCustomerId) {
            return [$this->conditionFactory->false()];
        }

        return [
            // allow access to current customer only
            $this->conditionFactory->propertyHasValue($currentCustomerId, $this->id),
        ];
    }

    public function isReferencable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'area_manage_orgadata',
            'area_manage_orgas',
            'area_manage_orgas_all',
            'area_organisations',
            'area_report_mastertoeblist',
            'feature_organisation_user_list',
        );
    }

    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        /** @var CustomerResourceConfigBuilder $configBuilder */
        $configBuilder = $this->getConfig(CustomerResourceConfigBuilder::class);
        $currentCustomerId = $this->currentCustomerService->getCurrentCustomer()->getId();
        $customerCondition = null === $currentCustomerId
            ? $this->conditionFactory->false()
            : $this->conditionFactory->propertyHasValue($currentCustomerId, Paths::customer()->id);

        $configBuilder->id->readable();

        if ($this->currentUser->hasAnyPermissions(
            'area_manage_orgadata',
            'area_manage_orgas',
            'area_manage_orgas_all',
            'area_organisations',
            'area_report_mastertoeblist',
            'feature_organisation_user_list',
        )) {
            $configBuilder->id->filterable()->sortable();
            $configBuilder->name->readable(true)->filterable()->sortable();
            $configBuilder->subdomain->readable(true)->filterable()->sortable();
        }

        if ($this->currentUser->hasPermission('field_sign_language_overview_video_edit')) {
            $configBuilder->signLanguageOverviewDescription->readable()->updatable([$customerCondition]);
            $configBuilder->signLanguageOverviewVideo
                ->setRelationshipType($this->resourceTypeStore->getSignLanguageOverviewVideoResourceType())
                ->readable(false, static function (Customer $customer): ?Video {
                    $firstVideo = $customer->getSignLanguageOverviewVideos()->first();

                    return false === $firstVideo
                        ? null
                        : $firstVideo;
                });
        }

        if ($this->currentUser->hasAnyPermissions(
            'feature_platform_logo_edit',
            'feature_customer_branding_edit'
        )) {
            $configBuilder->branding
                ->setRelationshipType($this->resourceTypeStore->getBrandingResourceType())
                ->readable(false, function (Customer $customer): Branding {
                    $branding = $customer->getBranding();
                    if (null === $branding) {
                        $branding = $this->brandingRepository->createFromData([]);
                        $this->brandingRepository->persistEntities([$branding]);
                        $customer->setBranding($branding);
                        $this->brandingRepository->flushEverything();
                    }

                    return $branding;
                });
        }

        if ($this->currentUser->hasPermission('feature_imprint_text_customized_view')) {
            $configBuilder->imprint->readable();
        }

        if ($this->currentUser->hasPermission('feature_data_protection_text_customized_view')) {
            $configBuilder->dataProtection->readable();
        }

        if ($this->currentUser->hasPermission('feature_customer_terms_of_use_edit')) {
            $configBuilder->termsOfUse->readable()->updatable([$customerCondition]);
        }

        if ($this->currentUser->hasPermission('feature_customer_xplanning_edit')) {
            $configBuilder->xplanning->readable()->updatable([$customerCondition]);
        }

        if ($this->currentUser->hasPermission('field_customer_accessibility_explanation_edit')) {
            $configBuilder->accessibilityExplanation->readable()->updatable([$customerCondition]);
        }

        if ($this->currentUser->hasPermission('field_simple_language_overview_description_edit')) {
            $configBuilder->overviewDescriptionInSimpleLanguage->readable()->updatable([$customerCondition]);
        }

        if ($this->currentUser->hasPermission('area_customer_settings')) {
            $configBuilder->baseLayerUrl->readable()->updatable(
                [$customerCondition],
                function (Customer $object, string $baseLayerUrl): array {
                    // the previously set value may be invalid, hence this validation can only be executed when the
                    // value is changed, not on any update
                    $violations = $this->validator->validate($baseLayerUrl, [new Url()]);
                    if (0 === $violations->count()) {
                        $object->setBaseLayerUrl($baseLayerUrl);
                    } else {
                        throw ViolationsException::fromConstraintViolationList($violations);
                    }

                    return [];
                }
            );
            $configBuilder->baseLayerLayers->readable()->updatable(
                [$customerCondition],
                function (Customer $object, string $baseLayerLayers): array {
                    // the previously set value may be invalid, hence this validation can only be executed when the
                    // value is changed, not on any update
                    $violations = $this->validator->validate($baseLayerLayers, [new Length(null, 5, 4096)]);
                    if (0 === $violations->count()) {
                        $object->setBaseLayerLayers($baseLayerLayers);
                    } else {
                        throw ViolationsException::fromConstraintViolationList($violations);
                    }

                    return [];
                }
            );
            $configBuilder->mapAttribution->readable()->updatable([$customerCondition]);
        }

        if ($this->currentUser->hasPermission('field_imprint_text_customized_edit_customer')) {
            $configBuilder->imprint->readable()->updatable([$customerCondition]);
        }

        if ($this->currentUser->hasPermission('field_data_protection_text_customized_edit_customer')) {
            $configBuilder->dataProtection->readable()->updatable([$customerCondition]);
        }

        if ($this->currentUser->hasPermission('feature_customer_login_support_contact_administration')) {
            $configBuilder->customerLoginSupportContact
                ->setRelationshipType($this->resourceTypeStore->getCustomerLoginSupportContactResourceType())
                ->readable(
                    false,
                    function (Customer $customer): ?SupportContact {
                        $supportContact = $this->customerLoginSupportContactResourceType->getEntities([
                            $this->conditionFactory->propertyHasValue($customer->getId(), $this->customerLoginSupportContactResourceType->customer->id),
                        ], []);
                        Assert::lessThanEq(count($supportContact), 1);

                        return array_pop($supportContact);
                    }
                );
        }
        if ($this->currentUser->hasPermission('feature_customer_support_contact_administration')) {
            $configBuilder->customerContacts
                ->setRelationshipType($this->resourceTypeStore->getCustomerContactResourceType())
                ->aliasedPath($this->contacts)
                ->readable();
        }

        return $configBuilder;
    }

    public function getUpdateValidationGroups(): array
    {
        return [Customer::GROUP_UPDATE];
    }

    public function isUpdateAllowed(): bool
    {
        if (!$this->currentUser->hasPermission('area_customer_settings')) {
            return false;
        }

        $currentCustomerId = $this->currentCustomerService->getCurrentCustomer()->getId();

        return null !== $currentCustomerId;
    }
}
