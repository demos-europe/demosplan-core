<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Export\Unit;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Exceptions\AddonOrgaNotFoundException;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobContextRestorer;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use Doctrine\ORM\EntityManagerInterface;
use Tests\Base\UnitTestCase;

class ExportJobContextRestorerTest extends UnitTestCase
{
    protected ?ExportJobContextRestorer $sut = null;

    private ?CurrentProcedureService $currentProcedureServiceMock = null;
    private ?CurrentUserService $currentUserServiceMock = null;
    private ?CustomerService $customerServiceMock = null;
    private ?EntityManagerInterface $entityManagerMock = null;
    private ?GlobalConfigInterface $globalConfigMock = null;
    private ?Permissions $permissionsMock = null;
    private ?ProcedureService $procedureServiceMock = null;
    private ?User $user = null;
    private ?Orga $orga = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentProcedureServiceMock = $this->createMock(CurrentProcedureService::class);
        $this->currentUserServiceMock = $this->createMock(CurrentUserService::class);
        $this->customerServiceMock = $this->createMock(CustomerService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->globalConfigMock = $this->createMock(GlobalConfigInterface::class);
        // Doubles the concrete class rather than PermissionsInterface: the SUT depends on the
        // interface, but setProcedurePermissions() is not published on it yet, so it cannot be
        // configured on an interface double. Permissions implements the interface, so this satisfies
        // the constructor either way.
        $this->permissionsMock = $this->createMock(Permissions::class);
        $this->procedureServiceMock = $this->createMock(ProcedureService::class);

        $this->user = new User();
        $this->entityManagerMock->method('find')->willReturnCallback(
            fn (string $class) => match ($class) {
                User::class => $this->user,
                Orga::class => $this->orga,
                default     => null,
            }
        );

        $this->sut = new ExportJobContextRestorer(
            $this->currentProcedureServiceMock,
            $this->currentUserServiceMock,
            $this->customerServiceMock,
            $this->entityManagerMock,
            $this->globalConfigMock,
            $this->permissionsMock,
            $this->procedureServiceMock
        );
    }

    public function testRestoreEnablesProcedureScopedPermissions(): void
    {
        // Arrange - without setProcedurePermissions() the export silently loses columns and comes
        // back anonymised, because those permissions are only granted per procedure
        $this->givenCustomer('subdomain-a');
        $this->givenProcedure();
        $this->permissionsMock->expects($this->once())->method('setProcedurePermissions');

        // Act
        $this->sut->restore('u1', 'c1', 'proc-1');
    }

    public function testRestoreSetsProcedureBeforeInitialisingPermissions(): void
    {
        // Arrange - procedure-dependent evaluation during initPermissions() needs the procedure
        $this->givenCustomer('subdomain-a');
        $this->givenProcedure();

        $callOrder = [];
        $permissions = $this->permissionsMock;
        $this->permissionsMock->method('setProcedure')
            ->willReturnCallback(static function () use (&$callOrder): void {
                $callOrder[] = 'setProcedure';
            });
        $this->permissionsMock->method('initPermissions')
            ->willReturnCallback(static function () use (&$callOrder, $permissions): Permissions {
                $callOrder[] = 'initPermissions';

                return $permissions;
            });
        $this->permissionsMock->method('setProcedurePermissions')
            ->willReturnCallback(static function () use (&$callOrder): void {
                $callOrder[] = 'setProcedurePermissions';
            });

        // Act
        $this->sut->restore('u1', 'c1', 'proc-1');

        // Assert
        self::assertSame(['setProcedure', 'initPermissions', 'setProcedurePermissions'], $callOrder);
    }

    public function testRestoreSwitchesSubdomainToTheActingUsersCustomer(): void
    {
        // Arrange - permissions from access_control are stored per customer, and CustomerService
        // resolves the customer from the global config's subdomain
        $this->givenCustomer('customer-b');
        $this->globalConfigMock->expects($this->once())->method('setSubdomain')->with('customer-b');

        // Act
        $this->sut->restore('u1', 'c1');
    }

    public function testRestoreAssignsCustomerToUserWithoutRelyingOnPostLoad(): void
    {
        // Arrange
        $customer = $this->givenCustomer('customer-b');

        // Act
        $this->sut->restore('u1', 'c1');

        // Assert
        self::assertSame($customer, $this->user->getCurrentCustomer());
    }

    public function testRestoreClearsTheProcedureWhenNoneIsGiven(): void
    {
        // Arrange - a worker handles many messages in one process, so leaving the previous message's
        // procedure in place would evaluate this export against the wrong one
        $this->givenCustomer('subdomain-a');
        $this->permissionsMock->expects($this->once())->method('setProcedure')->with(null);
        $this->permissionsMock->expects($this->never())->method('setProcedurePermissions');

        // Act
        $this->sut->restore('u1', 'c1');
    }

    public function testRestoreAssignsTheSelectedOrganisationToTheUser(): void
    {
        // Arrange - a multi-responsibility user picks one organisation per session; without it the
        // user falls back to their first organisation and fails the procedure access checks
        $this->givenCustomer('subdomain-a');
        $this->orga = new Orga();

        // Act
        $this->sut->restore('u1', 'c1', null, 'orga-1');

        // Assert
        self::assertSame($this->orga, $this->user->getCurrentOrganisation());
    }

    public function testRestoreClearsTheOrganisationWhenNoneIsGiven(): void
    {
        // Arrange - the user entity may still carry an organisation from an earlier message
        $this->givenCustomer('subdomain-a');
        $this->user->setCurrentOrganisation(new Orga());

        // Act
        $this->sut->restore('u1', 'c1');

        // Assert
        self::assertNull($this->user->getCurrentOrganisation());
    }

    public function testRestoreFailsWhenOrganisationCannotBeResolved(): void
    {
        // Arrange
        $this->givenCustomer('subdomain-a');
        $this->orga = null;

        // Assert
        $this->expectException(AddonOrgaNotFoundException::class);

        // Act
        $this->sut->restore('u1', 'c1', null, 'orga-gone');
    }

    public function testRestoreFailsWhenUserCannotBeResolved(): void
    {
        // Arrange
        $this->givenCustomer('subdomain-a');
        $this->user = null;

        // Assert
        $this->expectException(UserNotFoundException::class);

        // Act
        $this->sut->restore('u1', 'c1');
    }

    public function testRestoreFailsWhenProcedureCannotBeResolved(): void
    {
        // Arrange
        $this->givenCustomer('subdomain-a');
        $this->procedureServiceMock->method('getProcedure')->willReturn(null);

        // Assert
        $this->expectException(ProcedureNotFoundException::class);

        // Act
        $this->sut->restore('u1', 'c1', 'proc-1');
    }

    private function givenCustomer(string $subdomain): Customer
    {
        $customer = new Customer('Customer', $subdomain);
        $this->customerServiceMock->method('findCustomerById')->willReturn($customer);

        return $customer;
    }

    private function givenProcedure(): Procedure
    {
        $procedure = $this->createMock(Procedure::class);
        $this->procedureServiceMock->method('getProcedure')->willReturn($procedure);

        return $procedure;
    }
}
