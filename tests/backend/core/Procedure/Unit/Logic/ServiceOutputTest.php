<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Unit\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Twig\Environment;

/**
 * Tests for ServiceOutput logic
 * 
 * @group UnitTest
 */
class ServiceOutputTest extends TestCase
{
    /**
     * @var ServiceOutput
     */
    private $serviceOutput;

    /**
     * @var MockObject|ProcedureService
     */
    private $procedureService;

    /**
     * @var MockObject|CurrentUserService
     */
    private $currentUserService;

    /**
     * @var MockObject|PermissionsInterface
     */
    private $permissions;

    /**
     * @var MockObject|User
     */
    private $user;

    /**
     * @var MockObject|Procedure
     */
    private $procedure;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for all dependencies
        $this->procedureService = $this->createMock(ProcedureService::class);
        $this->currentUserService = $this->createMock(CurrentUserService::class);
        $this->permissions = $this->createMock(PermissionsInterface::class);
        $this->user = $this->createMock(User::class);
        $this->procedure = $this->createMock(Procedure::class);

        // Other necessary mocks
        $contentService = $this->createMock(ContentService::class);
        $customerService = $this->createMock(CustomerService::class);
        $draftStatementService = $this->createMock(DraftStatementService::class);
        $twig = $this->createMock(Environment::class);
        $config = $this->createMock(GlobalConfigInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $orgaService = $this->createMock(OrgaService::class);
        $statementService = $this->createMock(StatementService::class);
        $serviceImporter = $this->createMock(ServiceImporter::class);
        $userService = $this->createMock(UserService::class);

        // Create the service under test
        $this->serviceOutput = new ServiceOutput(
            $contentService,
            $this->currentUserService,
            $customerService,
            $draftStatementService,
            $twig,
            $config,
            $logger,
            $orgaService,
            $this->permissions,
            $this->procedureService,
            $serviceImporter,
            $statementService,
            $userService
        );
    }

    /**
     * Test getProcedureBySlug when user has permissions (hasPermissionsetRead returns true)
     */
    public function testGetProcedureBySlugWithPermissionsetRead(): void
    {
        $slug = 'test-procedure-slug';

        // Setup mocks
        $this->procedureService->expects($this->once())
            ->method('getProcedureBySlug')
            ->with($slug)
            ->willReturn($this->procedure);

        $this->permissions->expects($this->once())
            ->method('setProcedure')
            ->with($this->procedure);

        $this->user->expects($this->once())
            ->method('isPublicUser')
            ->willReturn(false);

        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_institution_participation')
            ->willReturn(true);

        $this->permissions->expects($this->once())
            ->method('hasPermissionsetRead')
            ->with(Permissions::PROCEDURE_PERMISSION_SCOPE_INTERNAL)
            ->willReturn(true);

        // The ownsProcedure method should not be called in this test case
        $this->permissions->expects($this->never())
            ->method('ownsProcedure');

        // Call the method
        $result = $this->serviceOutput->getProcedureBySlug($slug, $this->user);

        // Assert that the correct procedure is returned
        $this->assertSame($this->procedure, $result);
    }

    /**
     * Test getProcedureBySlug when user doesn't have permissions but owns the procedure
     */
    public function testGetProcedureBySlugWithOwnsProcedure(): void
    {
        $slug = 'test-procedure-slug';

        // Setup mocks
        $this->procedureService->expects($this->once())
            ->method('getProcedureBySlug')
            ->with($slug)
            ->willReturn($this->procedure);

        $this->permissions->expects($this->once())
            ->method('setProcedure')
            ->with($this->procedure);

        $this->user->expects($this->once())
            ->method('isPublicUser')
            ->willReturn(false);

        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_institution_participation')
            ->willReturn(true);

        // This is the key part: no read permission, but owns procedure
        $this->permissions->expects($this->once())
            ->method('hasPermissionsetRead')
            ->with(Permissions::PROCEDURE_PERMISSION_SCOPE_INTERNAL)
            ->willReturn(false);

        $this->permissions->expects($this->once())
            ->method('ownsProcedure')
            ->willReturn(true);

        // Call the method
        $result = $this->serviceOutput->getProcedureBySlug($slug, $this->user);

        // Assert that the correct procedure is returned
        $this->assertSame($this->procedure, $result);
    }

    /**
     * Test getProcedureBySlug when user doesn't have permissions and doesn't own the procedure
     */
    public function testGetProcedureBySlugWithoutPermissions(): void
    {
        $slug = 'test-procedure-slug';

        // Setup mocks
        $this->procedureService->expects($this->once())
            ->method('getProcedureBySlug')
            ->with($slug)
            ->willReturn($this->procedure);

        $this->permissions->expects($this->once())
            ->method('setProcedure')
            ->with($this->procedure);

        $this->user->expects($this->once())
            ->method('isPublicUser')
            ->willReturn(false);

        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_institution_participation')
            ->willReturn(true);

        // This is the key part: no read permission and doesn't own procedure
        $this->permissions->expects($this->once())
            ->method('hasPermissionsetRead')
            ->with(Permissions::PROCEDURE_PERMISSION_SCOPE_INTERNAL)
            ->willReturn(false);

        $this->permissions->expects($this->once())
            ->method('ownsProcedure')
            ->willReturn(false);

        // Call the method
        $result = $this->serviceOutput->getProcedureBySlug($slug, $this->user);

        // Assert that null is returned
        $this->assertNull($result);
    }

    /**
     * Test getProcedureBySlug with public user
     */
    public function testGetProcedureBySlugWithPublicUser(): void
    {
        $slug = 'test-procedure-slug';

        // Setup mocks
        $this->procedureService->expects($this->once())
            ->method('getProcedureBySlug')
            ->with($slug)
            ->willReturn($this->procedure);

        $this->permissions->expects($this->once())
            ->method('setProcedure')
            ->with($this->procedure);

        $this->user->expects($this->once())
            ->method('isPublicUser')
            ->willReturn(true);

        // When it's a public user, we should check EXTERNAL scope
        $this->permissions->expects($this->once())
            ->method('hasPermissionsetRead')
            ->with(Permissions::PROCEDURE_PERMISSION_SCOPE_EXTERNAL)
            ->willReturn(true);

        // Call the method
        $result = $this->serviceOutput->getProcedureBySlug($slug, $this->user);

        // Assert that the correct procedure is returned
        $this->assertSame($this->procedure, $result);
    }
}