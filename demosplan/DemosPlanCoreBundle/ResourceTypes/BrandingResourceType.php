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
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<Branding>
 *
 * @property-read End              $cssvars
 * @property-read End              $styling
 * @property-read FileResourceType $logo
 */
class BrandingResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface
{
    public static function getName(): string
    {
        return 'Branding';
    }

    protected function getProperties(): array
    {
        $properties = [
            $this->createAttribute($this->id)->readable(true),
        ];

        if ($this->currentUser->hasAnyPermissions(
            'feature_orga_branding_edit',
            'feature_customer_branding_edit'
        )) {
            $properties[] = $this->createAttribute($this->cssvars)->readable(true);
        }

        if ($this->currentUser->hasPermission('feature_platform_logo_edit')) {
            $properties[] = $this->createToOneRelationship($this->logo)->readable();
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

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    protected function getAccessConditions(): array
    {
        return [];
    }

    /**
     * @param Branding $updateTarget
     */
    public function getUpdatableProperties(object $updateTarget): array
    {
        if (!$this->currentUser->hasPermission('area_customer_settings')) {
            return [];
        }

        $currentCustomerBrandingId = $this->currentCustomerService->getCurrentCustomer()->getBranding()?->getId();
        if (null === $currentCustomerBrandingId) {
            return [];
        }

        if ($currentCustomerBrandingId !== $updateTarget->getId()) {
            return [];
        }

        $properties = [
            $this->logo,
        ];

        if ($this->currentUser->hasPermission('feature_customer_branding_edit')) {
            $properties[] = $this->styling;
        }

        return $this->toProperties(...$properties);
    }

    /**
     * @param Branding $object
     */
    public function updateObject(object $object, array $properties): ResourceChange
    {
        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->logo, $object->setLogo(...));
        $updater->ifPresent($this->styling, $object->setCssvars(...));

        $this->resourceTypeService->validateObject($object);

        return new ResourceChange($object, $this, $properties);
    }
}
