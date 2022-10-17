<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic;

use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Permissions\EvaluatablePermission;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionDecision;
use demosplan\DemosPlanCoreBundle\Permissions\Permission;
use PHPUnit\Framework\TestCase;

class ConditionalPermissionTest extends TestCase
{
    private const TEST_ONLY_USER_CONDITION = [
        'filter_condition_0' => [
            'condition' => [
                'path'      => 'roleInCustomer.role.code',
                'operator'  => '=',
                'value'     => Role::PLANNING_AGENCY_ADMIN,
                'memberOf'  => 'OR_GROUP',
                'parameter' => false,
            ],
        ],
        'filter_condition_1' => [
            'condition' => [
                'path'      => 'roleInCustomer.role.code',
                'operator'  => '=',
                'value'     => Role::PLANNING_AGENCY_WORKER,
                'memberOf'  => 'OR_GROUP',
                'parameter' => false,
            ],
        ],
        'OR_GROUP' => [
            'group' => [
                'conjunction' => 'OR',
            ],
        ],
        'filter_condition_2' => [
            'condition' => [
                'path'      => 'roleInCustomer.customer.id',
                'operator'  => '=',
                'value'     => '$currentCustomerId',
                'parameter' => true,
            ],
        ],
    ];

    public function testWithUserOnly(): void
    {
        $myPermission = $this->createPermission('feature_import_statement_via_email')
            ->addUserCondition('roleInCustomer.role.code', '=', Role::PLANNING_AGENCY_ADMIN, 'OR_GROUP')
            ->addUserCondition('roleInCustomer.role.code', '=', Role::PLANNING_AGENCY_WORKER, 'OR_GROUP')
            ->addUserGroup('OR_GROUP', 'OR')
            ->addUserCondition('roleInCustomer.customer.id', '=', EvaluatablePermission::CURRENT_CUSTOMER_ID, null, true);

        $userCondition = $myPermission->getUserFilters();
        $procedureCondition = $myPermission->getProcedureFilters();
        $customerCondition = $myPermission->getCustomerFilters();

        self::assertEmpty($procedureCondition);
        self::assertEmpty($customerCondition);
        self::assertEquals(self::TEST_ONLY_USER_CONDITION, $userCondition);
    }

    protected function createPermission(string $name): PermissionDecision
    {
        $newPermission = Permission::instanceFromArray($name, []);

        return new PermissionDecision($newPermission);
    }
}
