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

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\Video;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

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
 */
final class CustomerResourceType extends DplanResourceType
{
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

    public function getAccessCondition(): PathsBasedInterface
    {
        return $this->conditionFactory->true();
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
            $properties[] = $this->createToOneRelationship($this->branding)->readable();
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
}
