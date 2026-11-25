<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Ocx\Functional;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxWorkerContext;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Security\Ocx\OcxRequestContext;
use Tests\Base\FunctionalTestCase;

class OcxWorkerContextTest extends FunctionalTestCase
{
    /**
     * Enabled for the fixture planner after the restore, not listed by the OCX context.
     */
    private const ROLE_PERMISSION = 'feature_statements_final_email';

    private ?OcxWorkerContext $sut = null;
    private ?Permissions $permissions = null;
    private ?User $user = null;
    private ?Procedure $procedure = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(OcxWorkerContext::class);
        $permissions = $this->getContainer()->get(PermissionsInterface::class);
        self::assertInstanceOf(Permissions::class, $permissions);
        $this->permissions = $permissions;
        $this->user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
    }

    protected function tearDown(): void
    {
        $this->permissions?->setApiTokenContext(null);

        parent::tearDown();
    }

    public function testEnterRestoresTheSenderAndProcedure(): void
    {
        $this->enter();

        self::assertSame($this->user->getId(), $this->getContainer()->get(CurrentUserService::class)->getUser()->getId());
        self::assertSame($this->procedure->getId(), $this->getContainer()->get(CurrentProcedureService::class)->getProcedure()?->getId());
        self::assertSame($this->user->getOrga()?->getId(), $this->getContainer()->get(CurrentUserService::class)->getUser()->getOrga()?->getId());
    }

    public function testEnterGatesPermissionsLikeTheIntakeRequest(): void
    {
        $this->enter();

        self::assertInstanceOf(OcxRequestContext::class, $this->permissions->getApiTokenContext());
        self::assertFalse(
            $this->permissions->hasPermission(self::ROLE_PERMISSION),
            'a permission of the sender role must not ride in on the worker'
        );
    }

    public function testLeaveLiftsTheGateForLaterMessages(): void
    {
        $this->enter();
        $this->sut->leave();

        self::assertNull($this->permissions->getApiTokenContext());
        self::assertTrue($this->permissions->hasPermission(self::ROLE_PERMISSION));
    }

    private function enter(): void
    {
        $this->sut->enter(
            $this->user->getId(),
            $this->procedure->getCustomer()->getId(),
            $this->procedure->getId(),
            $this->user->getOrga()?->getId(),
        );
    }
}
