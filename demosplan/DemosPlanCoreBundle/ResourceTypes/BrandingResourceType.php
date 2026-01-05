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

use DemosEurope\DemosplanAddon\Contracts\Entities\BrandingInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Branding;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<BrandingInterface>
 *
 * @property-read End              $cssvars @deprecated, expose {@link self::$styling} instead
 * @property-read End              $styling
 * @property-read FileResourceType $logo
 */
class BrandingResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'Branding';
    }

    protected function getProperties(): array
    {
        $currentCustomerBrandingId = $this->currentCustomerService->getCurrentCustomer()->getBranding()?->getId();
        $customerCondition = null === $currentCustomerBrandingId
            ? $this->conditionFactory->false()
            : $this->conditionFactory->propertyHasValue($currentCustomerBrandingId, Paths::branding()->id);

        $logo = $this->createToOneRelationship($this->logo);

        $properties = [
            $this->createIdentifier()->readable(),
            $logo,
        ];

        if ($this->currentUser->hasPermission('feature_customer_branding_edit')) {
            $properties[] = $this->createAttribute($this->styling)
                ->updatable([$customerCondition])
                ->readable()
                ->aliasedPath(Paths::branding()->cssvars);
        }

        if ($this->currentUser->hasAnyPermissions(
            'feature_orga_branding_edit',
            'feature_customer_branding_edit'
        )) {
            $properties[] = $this->createAttribute($this->cssvars)->readable(true);
        }

        if ($this->currentUser->hasPermission('area_customer_settings')) {
            $logo->updatable([$customerCondition]);
        }

        if ($this->currentUser->hasPermission('feature_platform_logo_edit')) {
            $logo->readable();
        }

        return $properties;
    }

    public function getEntityClass(): string
    {
        return Branding::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_orga_branding_edit',
            'feature_customer_branding_edit',
            'feature_platform_logo_edit'
        );
    }

    protected function getAccessConditions(): array
    {
        return [];
    }

    public function isUpdateAllowed(): bool
    {
        if (!$this->currentUser->hasPermission('area_customer_settings')) {
            return false;
        }

        $currentCustomerBrandingId = $this->currentCustomerService->getCurrentCustomer()->getBranding()?->getId();

        return null !== $currentCustomerBrandingId;
    }
}
