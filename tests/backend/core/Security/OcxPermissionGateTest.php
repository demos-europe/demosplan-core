<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Security;

use DemosEurope\DemosplanAddon\Permission\PermissionCondition;
use DemosEurope\DemosplanAddon\Permission\PermissionIdentifier;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventListener\ConfigureApiTokenContextListener;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Permissions\Permission;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Permissions\ResolvablePermissionCollection;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenContextInterface;
use demosplan\DemosPlanCoreBundle\Security\Ocx\OcxRequestContext;
use ReflectionObject;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tests\Base\UnitTestCase;

use function in_array;

/**
 * Every permission below is enabled on the evaluator, standing in for a role that granted it, so
 * each denial comes from the OCX gate and not from a missing grant.
 */
class OcxPermissionGateTest extends UnitTestCase
{
    private const UNRELATED_PERMISSION = 'feature_statement_content_changes_view';

    private ?Permissions $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->permissionsWithEnabled([
            OcxRequestContext::FEATURE_STATEMENT,
            OcxRequestContext::FEATURE_RESPONSE,
            self::UNRELATED_PERMISSION,
        ]);
    }

    public function testOcxContextAllowsItsFeaturePermissions(): void
    {
        $this->sut->setApiTokenContext(new OcxRequestContext());

        self::assertTrue($this->sut->hasPermission(OcxRequestContext::FEATURE_STATEMENT));
        self::assertTrue($this->sut->hasPermission(OcxRequestContext::FEATURE_RESPONSE));
    }

    public function testOcxContextDeniesPermissionsItDoesNotList(): void
    {
        $this->sut->setApiTokenContext(new OcxRequestContext());

        self::assertFalse(
            $this->sut->hasPermission(self::UNRELATED_PERMISSION),
            'an unlisted permission must not ride in on a role'
        );
    }

    public function testOcxContextDoesNotEnableFeaturePermissionsTheProjectLeftOff(): void
    {
        $sut = $this->permissionsWithEnabled([OcxRequestContext::FEATURE_RESPONSE]);
        $sut->setApiTokenContext(new OcxRequestContext());

        self::assertFalse($sut->hasPermission(OcxRequestContext::FEATURE_STATEMENT));
        self::assertTrue($sut->hasPermission(OcxRequestContext::FEATURE_RESPONSE));
    }

    public function testOcxContextDeniesAddonPermissions(): void
    {
        $collection = new ResolvablePermissionCollection($this->getContainer()->get('validator'));
        $collection->configurePermission('feature_ocx_gate_test', 'label', 'description', false, [new PermissionCondition([], [], [])]);
        (new ReflectionProperty(Permissions::class, 'addonPermissionCollections'))->setValue($this->sut, ['ocx-gate-test' => $collection]);
        $identifier = PermissionIdentifier::forAddon('feature_ocx_gate_test', 'ocx-gate-test');

        $this->sut->setApiTokenContext(new OcxRequestContext());

        self::assertFalse($this->sut->isPermissionEnabled($identifier));
        $this->expectException(AccessDeniedException::class);
        $this->sut->requirePermission($identifier);
    }

    public function testWithoutContextNothingIsGated(): void
    {
        $this->sut->setApiTokenContext(null);

        self::assertTrue($this->sut->hasPermission(self::UNRELATED_PERMISSION));
    }

    public function testListenerHandsTheRequestContextToThePermissions(): void
    {
        $context = new OcxRequestContext();
        $request = new Request();
        $request->attributes->set(ApiTokenContextInterface::REQUEST_ATTRIBUTE, $context);

        (new ConfigureApiTokenContextListener($this->sut))->onKernelController($this->controllerEvent($request));

        self::assertSame($context, $this->sut->getApiTokenContext());
    }

    public function testListenerClearsTheContextOnRequestsWithoutOne(): void
    {
        $this->sut->setApiTokenContext(new OcxRequestContext());

        (new ConfigureApiTokenContextListener($this->sut))->onKernelController($this->controllerEvent(new Request()));

        self::assertNull($this->sut->getApiTokenContext());
    }

    /**
     * The real evaluator with a fixed permission set; `initPermissions()` would pull in roles and
     * the database.
     *
     * @param list<string> $enabledPermissions
     */
    private function permissionsWithEnabled(array $enabledPermissions): Permissions
    {
        $permissions = clone $this->getContainer()->get(Permissions::class);

        $all = [];
        foreach ([...OcxRequestContext::FEATURE_PERMISSIONS, self::UNRELATED_PERMISSION] as $name) {
            $permission = Permission::instanceFromArray($name, [
                'label'         => $name,
                'expose'        => false,
                'loginRequired' => false,
                'description'   => $name,
            ]);
            if (in_array($name, $enabledPermissions, true)) {
                $permission->enable();
            }
            $all[$name] = $permission;
        }

        $reflection = new ReflectionObject($permissions);
        $reflection->getProperty('permissions')->setValue($permissions, $all);
        $reflection->getProperty('user')->setValue($permissions, $this->createMock(User::class));

        return $permissions;
    }

    private function controllerEvent(Request $request): ControllerEvent
    {
        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
