<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Entity;

use demosplan\DemosPlanCoreBundle\Permissions\Permission;
use RuntimeException;
use Tests\Base\UnitTestCase;

class PermissionTest extends UnitTestCase
{
    protected $permissionsArraySection = [
        'field_statement_meta_city' => [
            'label'         => 'Ort',
            'enabled'       => true,
            'loginRequired' => false,
            'expose'        => true,
        ],
    ];

    protected $permissionName = 'field_statement_meta_city';

    /**
     * @var Permission
     */
    protected $sut;

    public function setUp(): void
    {
        $this->sut = Permission::instanceFromArray($this->permissionName, $this->permissionsArraySection[$this->permissionName]);
    }

    public function testInstanceFromArray()
    {
        $sut = Permission::instanceFromArray($this->permissionName, $this->permissionsArraySection[$this->permissionName]);
        self::assertInstanceOf(Permission::class, $sut);
    }

    /**
     * @depends testInstanceFromArray
     */
    public function testCannotChangeImmutableValues()
    {
        $this->expectException(RuntimeException::class);

        $this->sut['label'] = 'New Label';
    }
}
