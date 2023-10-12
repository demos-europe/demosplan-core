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

use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\Branding;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\Video;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\BrandingRepository;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<Customer>
 *
 * @property-read End                                   $name
 * @property-read End                                   $subdomain
 * @property-read End                                   $signLanguageOverviewDescription
 * @property-read End                                   $overviewDescriptionInSimpleLanguage
 * @property-read End                                   $imprint
 * @property-read SignLanguageOverviewVideoResourceType $signLanguageOverviewVideo
 * @property-read SignLanguageOverviewVideoResourceType $signLanguageOverviewVideos
 * @property-read BrandingResourceType                  $branding
 * @property-read End                                   $dataProtection
 * @property-read End                                   $termsOfUse
 * @property-read End                                   $xplanning
 * @property-read End                                   $accessibilityExplanation
 * @property-read End                                   $baseLayerUrl
 * @property-read End                                   $baseLayerLayers
 * @property-read End                                   $mapAttribution
 */
final class CustomerResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface
{
    public function __construct(
        protected readonly BrandingRepository $brandingRepository
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
            'field_customer_accessibility_explanation_edit',
            'field_sign_language_overview_video_edit',
            'field_simple_language_overview_description_edit'
        );
    }

    protected function getAccessConditions(): array
    {
        return [];
    }

    public function isReferencable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'area_manage_orgadata',
            'area_manage_orgas',
            'area_manage_orgas_all',
            'area_organisations',
            'area_report_mastertoeblist'
        );
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    protected function getProperties(): array
    {
        $id = $this->createAttribute($this->id)->readable(true);
        $properties = [$id];

        if ($this->currentUser->hasAnyPermissions(
            'area_manage_orgadata',
            'area_manage_orgas',
            'area_manage_orgas_all',
            'area_organisations',
            'area_report_mastertoeblist'
        )) {
            $id->filterable()->sortable();
            $properties[] = $this->createAttribute($this->name)->readable(true)->filterable()->sortable();
            $properties[] = $this->createAttribute($this->subdomain)->readable(true)->filterable()->sortable();
        }

        if ($this->currentUser->hasPermission('field_sign_language_overview_video_edit')) {
            $properties[] = $this->createAttribute($this->signLanguageOverviewDescription)->readable();
            $properties[] = $this->createToOneRelationship($this->signLanguageOverviewVideo)
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
            $properties[] = $this->createToOneRelationship($this->branding)
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
            $properties[] = $this->createAttribute($this->imprint)->readable();
        }

        if ($this->currentUser->hasPermission('feature_data_protection_text_customized_view')) {
            $properties[] = $this->createAttribute($this->dataProtection)->readable();
        }

        if ($this->currentUser->hasPermission('feature_customer_terms_of_use_edit')) {
            $properties[] = $this->createAttribute($this->termsOfUse)->readable();
        }

        if ($this->currentUser->hasPermission('feature_customer_xplanning_edit')) {
            $properties[] = $this->createAttribute($this->xplanning)->readable();
        }

        if ($this->currentUser->hasPermission('field_customer_accessibility_explanation_edit')) {
            $properties[] = $this->createAttribute($this->accessibilityExplanation)->readable();
        }

        if ($this->currentUser->hasPermission('field_simple_language_overview_description_edit')) {
            $properties[] = $this->createAttribute($this->overviewDescriptionInSimpleLanguage)->readable();
        }

        return $properties;
    }

    /**
     * @param Customer $object
     */
    public function updateObject(object $object, array $properties): ResourceChange
    {
        $updater = new PropertiesUpdater($properties);

        $updater->ifPresent($this->baseLayerUrl, $object->setBaseLayerUrl(...));
        $updater->ifPresent($this->baseLayerLayers, $object->setBaseLayerLayers(...));
        $updater->ifPresent($this->mapAttribution, $object->setMapAttribution(...));
        $updater->ifPresent($this->imprint, $object->setImprint(...));
        $updater->ifPresent($this->dataProtection, $object->setDataProtection(...));
        $updater->ifPresent($this->termsOfUse, $object->setTermsOfUse(...));
        $updater->ifPresent($this->xplanning, $object->setXplanning(...));
        $updater->ifPresent($this->signLanguageOverviewDescription, $object->setSignLanguageOverviewDescription(...));
        $updater->ifPresent($this->overviewDescriptionInSimpleLanguage, $object->setOverviewDescriptionInSimpleLanguage(...));
        $updater->ifPresent($this->accessibilityExplanation, $object->setAccessibilityExplanation(...));

        $this->resourceTypeService->validateObject($object, [Customer::GROUP_UPDATE]);

        return new ResourceChange($object, $this, $properties);
    }

    /**
     * @param Customer $updateTarget
     */
    public function getUpdatableProperties(object $updateTarget): array
    {
        if (!$this->currentUser->hasPermission('area_customer_settings')) {
            return [];
        }

        $currentCustomerId = $this->currentCustomerService->getCurrentCustomer()->getId();
        if (null === $currentCustomerId) {
            return [];
        }

        if ($currentCustomerId !== $updateTarget->getId()) {
            return [];
        }

        $properties = [
            $this->baseLayerUrl,
            $this->baseLayerLayers,
            $this->mapAttribution,
        ];

        if ($this->currentUser->hasPermission('field_imprint_text_customized_edit_customer')) {
            $properties[] = $this->imprint;
        }
        if ($this->currentUser->hasPermission('field_data_protection_text_customized_edit_customer')) {
            $properties[] = $this->dataProtection;
        }
        if ($this->currentUser->hasPermission('feature_customer_terms_of_use_edit')) {
            $properties[] = $this->termsOfUse;
        }
        if ($this->currentUser->hasPermission('feature_customer_xplanning_edit')) {
            $properties[] = $this->xplanning;
        }
        if ($this->currentUser->hasPermission('field_customer_accessibility_explanation_edit')) {
            $properties[] = $this->accessibilityExplanation;
        }
        if ($this->currentUser->hasPermission('field_simple_language_overview_description_edit')) {
            $properties[] = $this->overviewDescriptionInSimpleLanguage;
        }
        if ($this->currentUser->hasPermission('field_sign_language_overview_video_edit')) {
            $properties[] = $this->signLanguageOverviewDescription;
        }

        return $this->toProperties(...$properties);
    }
}
