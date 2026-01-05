<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities\Twig;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Twig\Extension\HasPermissionExtension;
use Exception;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\Base\UnitTestCase;

/**
 * @group UnitTest
 */
class HasPermissionExtensionTest extends UnitTestCase
{
    /**
     * @var HasPermissionExtension
     */
    private $twigExtension;

    public function setUp(): void
    {
        parent::setUp();

        $this->twigExtension = new HasPermissionExtension(self::getContainer(), self::getContainer()->get(PermissionsInterface::class));
    }

    /**
     * @param bool $cluster
     *
     * @return Session
     */
    protected function getSessionMock($cluster = true)
    {
        $sessionMock = $this->getMockBuilder(
            '\\Symfony\\Component\\HttpFoundation\\Session\\Session'
        )->disableOriginalConstructor()->getMock();

        $permissions['f1']['enabled'] = $cluster;
        $permissions['f2']['enabled'] = $cluster;

        $sessionMock->expects($this->any())
            ->method('get')
            ->with($this->logicalOr('permissions', 'user'))
            ->will(
                static::returnCallback(function ($parameter) use ($permissions) {
                    switch ($parameter) {
                        case 'permissions':
                            return $permissions;
                            break;
                        default:
                            return null;
                            break;
                    }
                }));

        return $sessionMock;
    }

    public function testPermission()
    {
        self::markSkippedForCIIntervention();
        // Could atm not be tested, because we have to inject permissions

        try {
            $permissionToTest = '';
            $result = $this->twigExtension->hasPermission($permissionToTest);
            static::assertFalse($result);

            $permissionToTest = ['f1', 'f2', 'f23768'];
            $result = $this->twigExtension->hasPermission($permissionToTest);
            static::assertFalse($result);

            // check for array with permissions
            $permissionToTest = ['f1', 'f2'];
            $result = $this->twigExtension->hasPermission($permissionToTest);
            static::assertTrue($result);
        } catch (Exception $e) {
            static::assertTrue(false);

            return;
        }
    }

    public function testHasOneOfPermissions()
    {
        self::markSkippedForCIIntervention();
        // Could atm not be tested, because we have to inject permissions

        $permissionToTest = 'f23768';
        $result = $this->twigExtension->hasOneOfPermissions($permissionToTest);
        static::assertFalse($result);

        $permissionToTest = ['f23768'];
        $result = $this->twigExtension->hasOneOfPermissions($permissionToTest);
        static::assertFalse($result);

        $permissionToTest = ['f1'];
        $result = $this->twigExtension->hasOneOfPermissions($permissionToTest);
        static::assertTrue($result);

        $permissionToTest = 'f1';
        $result = $this->twigExtension->hasOneOfPermissions($permissionToTest);
        static::assertTrue($result);

        $permissionToTest = ['f1', 'f23768'];
        $result = $this->twigExtension->hasOneOfPermissions($permissionToTest);
        static::assertTrue($result);

        $permissionToTest = ['f1', 'f2'];
        $result = $this->twigExtension->hasOneOfPermissions($permissionToTest);
        static::assertTrue($result);
    }
}
