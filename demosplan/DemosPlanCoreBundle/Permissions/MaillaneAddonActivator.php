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

/**
 *
 */
class MaillaneAddonActivator implements AddonActivatorInterface
{

    /**
     * @var array<string, Permission>
     */
    private $permissions;

    /**
     * @retrun list<ConditionalPermission>
     */
    public function getAddonPermissionsWithDefaults(): array
    {
        return [
            $this->createPermission('feature_import_statement_via_email')
                // parameter: path, operator, value, memberOf
                ->addUserCondition('', '=', 'OR_GROUP', Role::PLANNING_AGENCY_ADMIN)
                ->addUserCondition('', '=', 'OR_GROUP', Role::PLANNING_AGENCY_WORKER)
                // parameters: conjunction = AND, memberOf = null
                ->addUserGroup('OR'),
        ];
    }

    protected function createPermission(string $name): ConditionalPermission
    {
        return new ConditionalPermission($this->permissions[$name]);
    }
}
