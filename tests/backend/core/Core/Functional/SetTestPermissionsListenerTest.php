<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\EventListener\SetHttpTestPermissionsListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tests\Base\FunctionalTestCase;

class SetTestPermissionsListenerTest extends FunctionalTestCase
{
    /**
     * @dataProvider permissionsProvider
     */
    public function testOnKernelController(array $inputPermissions, array $expectedPermissions): void
    {
        // Mock the PermissionsInterface
        $permissions = $this->createMock(PermissionsInterface::class);
        $permissions->expects($this->once())
            ->method('enablePermissions')
            ->with($this->equalTo($expectedPermissions));

        // Create an instance of SetHttpTestPermissionsListener
        $listener = new SetHttpTestPermissionsListener(static::$kernel, $permissions);

        // Create a mock request
        $request = new Request();
        $request->server->set(
            SetHttpTestPermissionsListener::X_DPLAN_TEST_PERMISSIONS,
            implode(',', $inputPermissions)
        );

        // Create an instance of ControllerEvent
        $httpKernel = $this->createMock(HttpKernelInterface::class);
        $controller = static function () {};
        $event = new ControllerEvent($httpKernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST);

        // Call the method
        $listener->onKernelController($event);
    }

    public function permissionsProvider(): array
    {
        return [
            [['permission1', 'permission2'], ['permission1', 'permission2']],
            [['permission1'], ['permission1']],
            [[''], ['']],
        ];
    }
}
