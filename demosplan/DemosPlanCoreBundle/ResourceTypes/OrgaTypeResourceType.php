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

use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<OrgaType>
 *
 * @property-read End $name
 * @property-read End $label
 * @property-read OrgaStatusInCustomerResourceType $orgaStatusInCustomers
 */
final class OrgaTypeResourceType extends DplanResourceType
{
    protected function getAccessConditions(): array
    {
        return [$this->conditionFactory->propertyHasValue(
            $this->currentCustomerService->getCurrentCustomer()->getId(),
            $this->orgaStatusInCustomers->customer->id
        )];
    }

    public function getEntityClass(): string
    {
        return OrgaType::class;
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
        return 'OrgaType';
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable()->sortable()->filterable(),
            $this->createAttribute($this->name)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->label)->readable(true)->sortable()->filterable(),
        ];
    }
}
