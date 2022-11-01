<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\Addons\AddonActivatorInterface;

class MaillaneAddonActivator implements AddonActivatorInterface
{
    /**
     * @var PermissionCollectionInterface
     */
    private $permissions;

    /**
     * @param PermissionCollectionInterface $permissions FIXME: inject correct instance (i.e. create a service definition in the addon that uses the correct YAML)
     */
    public function __construct(PermissionCollectionInterface $permissions)
    {
        $this->permissions = $permissions;
    }

    public function getAddonPermissionsWithDefaults(): array
    {
        return [
            $this->createPermission('feature_import_statement_via_email')
                ->addUserCondition('roleInCustomers.role.code', '=', Role::PLANNING_AGENCY_ADMIN, 'OR_GROUP')
                ->addUserCondition('roleInCustomers.role.code', '=', Role::PLANNING_AGENCY_WORKER, 'OR_GROUP')
                ->addUserGroup('OR_GROUP', 'OR')
                ->addUserCondition('roleInCustomers.customer.id', '=', EvaluatablePermission::CURRENT_CUSTOMER_ID, null, true),
        ];
    }

    protected function createPermission(string $name): PermissionDecision
    {
        return new PermissionDecision($this->permissions->getPermission($name));
    }

    public function getPackageName(): string
    {
        // FIXME: return correct package name, ideally read from the composer.json and not hardcoded here
        return '';
    }
}
