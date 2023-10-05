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

use demosplan\DemosPlanCoreBundle\Entity\Branding;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<Branding>
 *
 * @property-read End              $cssvars
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
        return false;
    }

    protected function getAccessConditions(): array
    {
        return [];
    }
}
