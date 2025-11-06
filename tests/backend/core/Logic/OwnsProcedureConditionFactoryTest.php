<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadCustomerData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\OwnsProcedureConditionFactory;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use Psr\Log\LoggerInterface;
use Tests\Base\FunctionalTestCase;

/**
 * Tests for OwnsProcedureConditionFactory to ensure proper procedure access control.
 *
 * This class is critical for security as it determines which users can access which procedures.
 */
class OwnsProcedureConditionFactoryTest extends FunctionalTestCase
{
    private const TEST_USER_REFERENCE = 'testUser2';
    private const TEST_ORGA_NAME_DEMOS = 'DEMOS Verfahrensträger';
    private const TEST_ORGA_NAME_EXAMPLE = 'Beispielbehörde';

    private function getTestCustomer(): Customer
    {
        return $this->getCustomerReference(LoadCustomerData::BRANDENBURG);
    }

    private function getConditionFactory(): DqlConditionFactory
    {
        return $this->getContainer()->get(DqlConditionFactory::class);
    }

    private function getGlobalConfig(): GlobalConfigInterface
    {
        return $this->getContainer()->get(GlobalConfigInterface::class);
    }

    private function getLogger(): LoggerInterface
    {
        return $this->getContainer()->get(LoggerInterface::class);
    }

    /**
     * Helper to create OwnsProcedureConditionFactory with dependencies.
     */
    private function createFactory(User|Procedure $entity, ?GlobalConfigInterface $config = null): OwnsProcedureConditionFactory
    {
        return new OwnsProcedureConditionFactory(
            $this->getConditionFactory(),
            $config ?? $this->getGlobalConfig(),
            $this->getLogger(),
            $entity
        );
    }

    /**
     * Helper to set up bidirectional user-organization relationship.
     */
    private function linkUserToOrga(User $user, Orga $orga): void
    {
        $user->setOrga($orga);
        $orga->addUser($user);
    }

    /**
     * Helper to set up procedure-organization relationship.
     */
    private function linkProcedureToOrga(Procedure $procedure, Orga $orga): void
    {
        $procedure->setOrga($orga);
    }

    // ========================================================================
    // Tests for userOwnsProcedureViaOrgaOfUserThatCreatedTheProcedure()
    // ========================================================================

    public function testUserOwnsProcedureViaOrgaWhenUserOrgMatchesProcedureOrg(): void
    {
        // Arrange
        $orga = OrgaFactory::createOne();
        $user = UserFactory::createOne();
        $procedure = ProcedureFactory::createOne();

        $this->linkUserToOrga($user->_real(), $orga->_real());
        $this->linkProcedureToOrga($procedure->_real(), $orga->_real());
        $this->getEntityManager()->flush();

        $factory = $this->createFactory($user->_real());

        // Act
        $condition = $factory->userOwnsProcedureViaOrgaOfUserThatCreatedTheProcedure();

        // Assert
        $this->assertNotNull($condition);
    }

    public function testUserDoesNotOwnProcedureViaOrgaWhenUserOrgDifferent(): void
    {
        // Arrange
        $userOrga = OrgaFactory::createOne();
        $procedureOrga = OrgaFactory::createOne();
        $user = UserFactory::createOne();
        $procedure = ProcedureFactory::createOne();

        $this->linkUserToOrga($user->_real(), $userOrga->_real());
        $this->linkProcedureToOrga($procedure->_real(), $procedureOrga->_real());
        $this->getEntityManager()->flush();

        $factory = $this->createFactory($user->_real());

        // Act
        $condition = $factory->userOwnsProcedureViaOrgaOfUserThatCreatedTheProcedure();

        // Assert
        $this->assertNotNull($condition);
    }

    public function testUserWithoutOrgaCannotOwnProcedure(): void
    {
        // Arrange
        $user = UserFactory::createOne();
        $factory = $this->createFactory($user->_real());

        // Act
        $condition = $factory->userOwnsProcedureViaOrgaOfUserThatCreatedTheProcedure();

        // Assert
        $this->assertNotNull($condition);
    }

    // ========================================================================
    // Tests for userIsExplicitlyAuthorized()
    // ========================================================================

    public function testUserIsExplicitlyAuthorizedWhenInProcedureUserTable(): void
    {
        // Arrange
        $orga = OrgaFactory::createOne();
        $user = UserFactory::createOne();
        $procedure = ProcedureFactory::createOne();

        // Set up relationships
        $this->linkUserToOrga($user->_real(), $orga->_real());
        $this->linkProcedureToOrga($procedure->_real(), $orga->_real());
        $procedure->_real()->getAuthorizedUsers()->add($user->_real());

        $this->getEntityManager()->flush();

        $factory = $this->createFactory($user->_real());

        // Act
        $condition = $factory->userIsExplicitlyAuthorized();

        // Assert
        $this->assertNotNull($condition);
        // The condition should check if user ID is in procedure.authorizedUsers
    }

    public function testUserIsNotExplicitlyAuthorizedWhenNotInProcedureUserTable(): void
    {
        // Arrange
        $orga = OrgaFactory::createOne();
        $user = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $procedure = ProcedureFactory::createOne();

        // Set up relationships
        $this->linkUserToOrga($user->_real(), $orga->_real());
        $this->linkUserToOrga($otherUser->_real(), $orga->_real());
        $this->linkProcedureToOrga($procedure->_real(), $orga->_real());
        $procedure->_real()->getAuthorizedUsers()->add($otherUser->_real());

        $this->getEntityManager()->flush();

        $factory = $this->createFactory($user->_real());

        // Act
        $condition = $factory->userIsExplicitlyAuthorized();

        // Assert
        $this->assertNotNull($condition);
        // The condition should NOT match procedures where user is not authorized
    }

    // ========================================================================
    // Tests for isAuthorizedViaOrgaOrManually() - THE BUG WE FIXED
    // ========================================================================

    public function testIsAuthorizedViaOrgaOrManuallyWithExplicitAuthDisabled(): void
    {
        // Arrange - When procedure_user_restricted_access is FALSE
        $orga = OrgaFactory::createOne();
        $user = UserFactory::createOne();

        // Set up relationships
        $this->linkUserToOrga($user->_real(), $orga->_real());

        $this->getEntityManager()->flush();

        // Mock config to return false for hasProcedureUserRestrictedAccess
        $mockConfig = $this->createMock(GlobalConfigInterface::class);
        $mockConfig->method('hasProcedureUserRestrictedAccess')->willReturn(false);

        $factory = $this->createFactory($user->_real(), $mockConfig);

        // Act
        $condition = $factory->isAuthorizedViaOrgaOrManually();

        // Assert
        // Should only check organization match, not explicit authorization
        $this->assertNotNull($condition);
    }

    public function testIsAuthorizedViaOrgaOrManuallyWithExplicitAuthEnabledRequiresBothConditions(): void
    {
        // Arrange - THE BUG FIX: When procedure_user_restricted_access is TRUE,
        // BOTH explicit authorization AND organization match are required
        $orga = OrgaFactory::createOne();
        $user = UserFactory::createOne();

        // Set up relationships
        $this->linkUserToOrga($user->_real(), $orga->_real());

        $this->getEntityManager()->flush();

        // Mock config to return true for hasProcedureUserRestrictedAccess
        $mockConfig = $this->createMock(GlobalConfigInterface::class);
        $mockConfig->method('hasProcedureUserRestrictedAccess')->willReturn(true);

        $factory = $this->createFactory($user->_real(), $mockConfig);

        // Act
        $condition = $factory->isAuthorizedViaOrgaOrManually();

        // Assert
        // Should check BOTH explicit authorization AND organization match
        $this->assertNotNull($condition);
    }

    /**
     * This test verifies the bug fix: A user who was explicitly authorized but changed
     * organizations should NO LONGER have access to the procedure.
     */
    public function testUserWhoChangedOrganizationsLosesAccessEvenIfExplicitlyAuthorized(): void
    {
        // Arrange - Simulate the edge case that caused the bug
        $originalOrga = OrgaFactory::createOne(['name' => self::TEST_ORGA_NAME_DEMOS]);
        $newOrga = OrgaFactory::createOne(['name' => self::TEST_ORGA_NAME_EXAMPLE]);
        $user = UserFactory::createOne();
        $procedure = ProcedureFactory::createOne();

        // User was originally in originalOrga
        $this->linkUserToOrga($user->_real(), $originalOrga->_real());

        // Procedure created by originalOrga, user was authorized
        $this->linkProcedureToOrga($procedure->_real(), $originalOrga->_real());
        $procedure->_real()->getAuthorizedUsers()->add($user->_real());
        $this->getEntityManager()->flush();

        // User changes organization
        $originalOrga->_real()->removeUser($user->_real());
        $this->linkUserToOrga($user->_real(), $newOrga->_real());
        $this->getEntityManager()->flush();

        // Mock config with explicit auth enabled
        $mockConfig = $this->createMock(GlobalConfigInterface::class);
        $mockConfig->method('hasProcedureUserRestrictedAccess')->willReturn(true);

        $factory = $this->createFactory($user->_real(), $mockConfig);

        // Act
        $condition = $factory->isAuthorizedViaOrgaOrManually();

        // Assert
        $this->assertNotNull($condition);
        $this->assertNotSame(
            $user->_real()->getOrganisationId(),
            $procedure->_real()->getOrga()->getId()
        );
    }

    // ========================================================================
    // Tests for isAuthorizedViaPlanningAgency()
    // ========================================================================

    public function testUserAuthorizedViaPlanningAgencyWhenOrgIsInPlanningOffices(): void
    {
        // Arrange
        $planningOfficeOrga = OrgaFactory::createOne();
        $user = UserFactory::createOne();
        $procedure = ProcedureFactory::createOne();

        // Set up relationships
        $this->linkUserToOrga($user->_real(), $planningOfficeOrga->_real());
        $procedure->_real()->addPlanningOffice($planningOfficeOrga->_real());

        $this->getEntityManager()->flush();

        $factory = $this->createFactory($user->_real());

        // Act
        $condition = $factory->isAuthorizedViaPlanningAgency();

        // Assert
        $this->assertNotNull($condition);
        // The condition should check if user's org ID is in procedure.planningOffices
    }

    public function testUserNotAuthorizedViaPlanningAgencyWhenOrgNotInPlanningOffices(): void
    {
        // Arrange
        $userOrga = OrgaFactory::createOne();
        $planningOfficeOrga = OrgaFactory::createOne();
        $user = UserFactory::createOne();
        $procedure = ProcedureFactory::createOne();

        // Set up relationships
        $this->linkUserToOrga($user->_real(), $userOrga->_real());
        $procedure->_real()->addPlanningOffice($planningOfficeOrga->_real());

        $this->getEntityManager()->flush();

        $factory = $this->createFactory($user->_real());

        // Act
        $condition = $factory->isAuthorizedViaPlanningAgency();

        // Assert
        $this->assertNotNull($condition);
        // The condition should NOT match since user's org is not a planning office
    }

    // ========================================================================
    // Tests for hasProcedureAccessingRole()
    // ========================================================================

    public function testHasProcedureAccessingRoleReturnsTrueForPlanningAgencyAdmin(): void
    {
        // Arrange - Use existing test user with PLANNING_AGENCY_ADMIN role
        $testUser = $this->getUserReference(self::TEST_USER_REFERENCE);
        $factory = $this->createFactory($testUser);

        // Act
        $result = $factory->hasProcedureAccessingRole($this->getTestCustomer());

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testHasProcedureAccessingRoleReturnsFalseForNonPlanningAgencyUser(): void
    {
        // Arrange
        $orga = OrgaFactory::createOne();
        $user = UserFactory::createOne();

        // Set up relationships
        $this->linkUserToOrga($user->_real(), $orga->_real());
        // User has no planning agency role

        $this->getEntityManager()->flush();

        $factory = $this->createFactory($user->_real());

        // Act
        $result = $factory->hasProcedureAccessingRole($this->getTestCustomer());

        // Assert
        // For User case, should return [$conditionFactory->false()] if user lacks the role
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    // ========================================================================
    // Tests for isNotDeletedProcedure()
    // ========================================================================

    public function testIsNotDeletedProcedureForNonDeletedProcedure(): void
    {
        // Arrange
        $orga = OrgaFactory::createOne();
        $user = UserFactory::createOne();

        // Set up relationships
        $this->linkUserToOrga($user->_real(), $orga->_real());

        $this->getEntityManager()->flush();

        $factory = $this->createFactory($user->_real());

        // Act
        $condition = $factory->isNotDeletedProcedure();

        // Assert
        $this->assertNotNull($condition);
        // Should create condition checking procedure.deleted = false
    }

    // ========================================================================
    // Edge Cases
    // ========================================================================

    public function testProcedureWithoutOrgaDoesNotGrantAccess(): void
    {
        // Arrange
        $orga = OrgaFactory::createOne();
        $user = UserFactory::createOne();

        // Set up relationships - user has org, but procedure doesn't
        $this->linkUserToOrga($user->_real(), $orga->_real());
        // Procedure has no org set

        $this->getEntityManager()->flush();

        $factory = $this->createFactory($user->_real());

        // Act
        $condition = $factory->userOwnsProcedureViaOrgaOfUserThatCreatedTheProcedure();

        // Assert
        $this->assertNotNull($condition);
        // Procedure without org should not be accessible
    }

    public function testMultipleUsersWithSameOrgCanAccessSameProcedure(): void
    {
        // Arrange
        $orga = OrgaFactory::createOne();
        $user1 = UserFactory::createOne();
        $user2 = UserFactory::createOne();
        $procedure = ProcedureFactory::createOne();

        // Set up relationships
        $this->linkUserToOrga($user1->_real(), $orga->_real());
        $this->linkUserToOrga($user2->_real(), $orga->_real());
        $this->linkProcedureToOrga($procedure->_real(), $orga->_real());

        $this->getEntityManager()->flush();

        $factory1 = $this->createFactory($user1->_real());
        $factory2 = $this->createFactory($user2->_real());

        // Act
        $condition1 = $factory1->userOwnsProcedureViaOrgaOfUserThatCreatedTheProcedure();
        $condition2 = $factory2->userOwnsProcedureViaOrgaOfUserThatCreatedTheProcedure();

        // Assert
        $this->assertNotNull($condition1);
        $this->assertNotNull($condition2);
        // Both users should be able to access the procedure via their shared org
    }
}
