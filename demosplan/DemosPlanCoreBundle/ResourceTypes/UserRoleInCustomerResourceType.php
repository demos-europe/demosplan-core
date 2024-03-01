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

use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;

/**
 * @template-extends DplanResourceType<UserRoleInCustomer>
 *
 * @property-read RoleResourceType     $role
 * @property-read CustomerResourceType $customer
 */
final class UserRoleInCustomerResourceType extends DplanResourceType
{
    public function getEntityClass(): string
    {
        return UserRoleInCustomer::class;
    }

    public static function getName(): string
    {
        return 'UserRoleInCustomer';
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('feature_json_api_user');
    }

    protected function getAccessConditions(): array
    {
        return [];
    }

    protected function getProperties(): array
    {
        $properties = [
            $this->createIdentifier()->readable()->sortable()->filterable(),
        ];
        if ($this->resourceTypeStore->getCustomerResourceType()->isReferencable()) {
            $properties[] = $this->createToOneRelationship($this->customer)->readable()->sortable()->filterable();
        }

        return $properties;
    }
}
