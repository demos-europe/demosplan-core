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
use demosplan\DemosPlanCoreBundle\Permissions\PermissionDecision;
use demosplan\DemosPlanCoreBundle\Permissions\Permission;
use PHPUnit\Framework\TestCase;

class ConditionalPermissionTest extends TestCase
{
    private const TEST_ONLY_USER_CONDITION = [
        [
            'condition' => [
                'path'     => '',
                'operator' => '=',
                'value'    => Role::PLANNING_AGENCY_ADMIN,
                'memberOf' => 'OR_GROUP',
            ],
        ],
        [
            'condition' => [
                'path'     => '',
                'operator' => '=',
                'value'    => Role::PLANNING_AGENCY_WORKER,
                'memberOf' => 'OR_GROUP',
            ],
        ],
        [
            'group' => [
                'conjunction' => 'OR',
            ],
        ],
    ];

    public function testWithUserOnly(): void
    {
        $myPermission = $this->createPermission('feature_import_statement_via_email')
            ->addUserCondition('', '=', Role::PLANNING_AGENCY_ADMIN, 'OR_GROUP')
            ->addUserCondition('', '=', Role::PLANNING_AGENCY_WORKER, 'OR_GROUP')
            ->addUserGroup('OR');

        $userCondition = $myPermission->getUserConditon();
        $procedureCondition = $myPermission->getProcedureCondition();
        $customerCondition = $myPermission->getCustomerCondition();

        self::assertNull($procedureCondition);
        self::assertNull($customerCondition);
        self::assertEquals(self::TEST_ONLY_USER_CONDITION, $userCondition);
    }

    protected function createPermission(string $name): PermissionDecision
    {
        $newPermission = Permission::instanceFromArray($name, []);

        return new PermissionDecision($newPermission);
    }
}
