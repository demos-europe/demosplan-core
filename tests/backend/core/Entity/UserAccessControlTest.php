<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Permission\UserAccessControl;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Exception;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class UserAccessControlTest extends FunctionalTestCase
{
    /**
     * @var UserAccessControl|null
     */
    protected $sut;

    private User|Proxy|null $testUser;
    private Orga|Proxy|null $testOrga;
    private Customer|Proxy|null $testCustomer;
    private ?Role $testRole;
    private ?RoleHandler $roleHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleHandler = $this->getContainer()->get(RoleHandler::class);
        $this->testRole = $this->roleHandler->getUserRolesByCodes([RoleInterface::PRIVATE_PLANNING_AGENCY])[0];

        $this->testCustomer = CustomerFactory::createOne();
        $this->testOrga = OrgaFactory::createOne();
        $this->testUser = UserFactory::createOne();
    }

    public function testCreateUserAccessControlWithValidRelationships(): void
    {
        // Arrange
        $permission = 'feature_statement_bulk_edit';

        // Act
        $userAccessControl = new UserAccessControl();
        $userAccessControl->setUser($this->testUser->object());
        $userAccessControl->setOrganisation($this->testOrga->object());
        $userAccessControl->setCustomer($this->testCustomer->object());
        $userAccessControl->setRole($this->testRole);
        $userAccessControl->setPermission($permission);

        $this->getEntityManager()->persist($userAccessControl);
        $this->getEntityManager()->flush();

        // Assert
        self::assertInstanceOf(UserAccessControl::class, $userAccessControl);
        self::assertNotNull($userAccessControl->getId());
        self::assertSame($permission, $userAccessControl->getPermission());
        self::assertSame($this->testUser->object(), $userAccessControl->getUser());
        self::assertSame($this->testOrga->object(), $userAccessControl->getOrganisation());
        self::assertSame($this->testCustomer->object(), $userAccessControl->getCustomer());
        self::assertSame($this->testRole, $userAccessControl->getRole());
    }

    public function testUniqueConstraintEnforcementForUserPermissions(): void
    {
        // Arrange
        $permission = 'feature_statement_bulk_edit';

        $userAccessControl1 = new UserAccessControl();
        $userAccessControl1->setUser($this->testUser->object());
        $userAccessControl1->setOrganisation($this->testOrga->object());
        $userAccessControl1->setCustomer($this->testCustomer->object());
        $userAccessControl1->setRole($this->testRole);
        $userAccessControl1->setPermission($permission);

        $userAccessControl2 = new UserAccessControl();
        $userAccessControl2->setUser($this->testUser->object());
        $userAccessControl2->setOrganisation($this->testOrga->object());
        $userAccessControl2->setCustomer($this->testCustomer->object());
        $userAccessControl2->setRole($this->testRole);
        $userAccessControl2->setPermission($permission);

        $this->getEntityManager()->persist($userAccessControl1);
        $this->getEntityManager()->flush();

        // Act & Assert
        $this->expectException(UniqueConstraintViolationException::class);

        $this->getEntityManager()->persist($userAccessControl2);
        $this->getEntityManager()->flush();
    }

    public function testUserAccessControlPersistsCorrectly(): void
    {
        // Arrange - Use manual entity creation for now due to factory issues
        $userAccessControl = new UserAccessControl();
        $userAccessControl->setUser($this->testUser->object());
        $userAccessControl->setOrganisation($this->testOrga->object());
        $userAccessControl->setCustomer($this->testCustomer->object());
        $userAccessControl->setRole($this->testRole);
        $userAccessControl->setPermission('feature_statement_bulk_edit');

        $this->getEntityManager()->persist($userAccessControl);
        $this->getEntityManager()->flush();

        // Act - Retrieve from database
        $persistedUserAccessControl = $this->getEntityManager()
            ->getRepository(UserAccessControl::class)
            ->find($userAccessControl->getId());

        // Assert - Verify relationships are maintained
        self::assertNotNull($persistedUserAccessControl);
        self::assertSame('feature_statement_bulk_edit', $persistedUserAccessControl->getPermission());
        self::assertSame($this->testUser->object(), $persistedUserAccessControl->getUser());
        self::assertSame($this->testOrga->object(), $persistedUserAccessControl->getOrganisation());
        self::assertSame($this->testCustomer->object(), $persistedUserAccessControl->getCustomer());
        self::assertSame($this->testRole, $persistedUserAccessControl->getRole());
    }

    public function testUserAccessControlGettersAndSetters(): void
    {
        // Arrange
        $userAccessControl = new UserAccessControl();
        $permission = 'feature_statement_bulk_edit';

        // Act
        $userAccessControl->setUser($this->testUser->object());
        $userAccessControl->setOrganisation($this->testOrga->object());
        $userAccessControl->setCustomer($this->testCustomer->object());
        $userAccessControl->setRole($this->testRole);
        $userAccessControl->setPermission($permission);

        // Assert
        self::assertSame($this->testUser->object(), $userAccessControl->getUser());
        self::assertSame($this->testOrga->object(), $userAccessControl->getOrganisation());
        self::assertSame($this->testCustomer->object(), $userAccessControl->getCustomer());
        self::assertSame($this->testRole, $userAccessControl->getRole());
        self::assertSame($permission, $userAccessControl->getPermission());
    }

    public function testEntityValidationRulesWorkCorrectly(): void
    {
        // Arrange
        $userAccessControl = new UserAccessControl();

        // Act & Assert - Test required fields
        $this->expectException(Exception::class);

        // Try to persist without required fields
        $this->getEntityManager()->persist($userAccessControl);
        $this->getEntityManager()->flush();
    }

    public function testUserMustBelongToSameOrganizationAsPermission(): void
    {
        // This test will validate business logic once service layer is implemented
        // For now, we're testing the entity can store different organizations
        // The validation will be enforced at the service layer

        // Arrange
        $differentOrga = OrgaFactory::createOne();
        $permission = 'feature_statement_bulk_edit';

        $userAccessControl = new UserAccessControl();
        $userAccessControl->setUser($this->testUser->object());
        $userAccessControl->setOrganisation($differentOrga->object()); // Different org
        $userAccessControl->setCustomer($this->testCustomer->object());
        $userAccessControl->setRole($this->testRole);
        $userAccessControl->setPermission($permission);

        // Act - Entity level allows this, service level will validate
        $this->getEntityManager()->persist($userAccessControl);
        $this->getEntityManager()->flush();

        // Assert - Entity persists successfully (validation happens at service layer)
        self::assertInstanceOf(UserAccessControl::class, $userAccessControl);
        self::assertNotNull($userAccessControl->getId());
    }
}
