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

use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<OrgaStatusInCustomer>
 *
 * @property-read CustomerResourceType $customer
 * @property-read OrgaResourceType $orga
 * @property-read OrgaTypeResourceType $orgaType
 * @property-read End $status
 */
final class OrgaStatusInCustomerResourceType extends DplanResourceType
{
    protected function getAccessConditions(): array
    {
        return [$this->conditionFactory->propertyHasValue(
            $this->currentCustomerService->getCurrentCustomer()->getId(),
            $this->customer->id
        )];
    }

    public function getEntityClass(): string
    {
        return OrgaStatusInCustomer::class;
    }

    public function isAvailable(): bool
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

    public static function getName(): string
    {
        return 'OrgaStatusInCustomer';
    }

    protected function getProperties(): array
    {
        $properties = [
            $this->createIdentifier()->readable()->filterable()->sortable(),
            $this->createToOneRelationship($this->orgaType)->readable()->sortable()->filterable(),
            $this->createToOneRelationship($this->orga)->readable()->sortable()->filterable(),
            $this->createAttribute($this->status)->readable(true)->filterable()->sortable(),
        ];

        if ($this->resourceTypeStore->getCustomerResourceType()->isReferencable()) {
            $properties[] = $this->createToOneRelationship($this->customer)->readable()->sortable()->filterable();
        }

        return $properties;
    }
}
