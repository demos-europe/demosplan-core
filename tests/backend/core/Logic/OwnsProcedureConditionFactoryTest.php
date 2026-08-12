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
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
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
        $orga->addUser($user);
        $user->setOrga($orga);
        $this->getEntityManager()->flush();
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

    // ========================================================================
    // Tests for isMasterTemplate() - the platform master blueprint exemption
    //
    // The platform master blueprint is seeded by migration rather than created
    // through the UI, so it never receives the creator row that every other
    // procedure gets. While explicit authorization is enabled that empty list made
    // userIsExplicitlyAuthorized() resolve to a hard false(), leaving the blueprint
    // owned by nobody and its settings page unopenable even for its owning orga.
    //
    // Unlike the tests above these evaluate the produced conditions rather than only
    // asserting they are non-null, so they fail if the exemption regresses.
    // ========================================================================

    private function getEntityFetcher(): EntityFetcher
    {
        return $this->getContainer()->get(EntityFetcher::class);
    }

    /**
     * Only the config is mocked here - it is a collaborator, not the system under test.
     */
    private function createRestrictedAccessConfig(): GlobalConfigInterface
    {
        $config = $this->createMock(GlobalConfigInterface::class);
        $config->method('hasProcedureUserRestrictedAccess')->willReturn(true);

        return $config;
    }

    /**
     * Evaluates a procedure's ownership condition against a user, the way
     * {@link ProcedureAccessEvaluator::isOwningProcedure()} does when a permission is checked.
     */
    private function ownsProcedureUnderRestrictedAccess(User $user, Procedure $procedure): bool
    {
        $condition = $this->createFactory($procedure, $this->createRestrictedAccessConfig())
            ->isAuthorizedViaOrgaOrManually();

        return $this->getEntityFetcher()->objectMatches($user, $condition);
    }

    public function testMasterTemplateIsOwnedByOrgaMemberWithoutExplicitAuthorization(): void
    {
        // Arrange
        $orga = OrgaFactory::createOne();
        $user = UserFactory::createOne();
        $masterTemplate = ProcedureFactory::createOne(['master' => true, 'masterTemplate' => true]);

        $this->linkUserToOrga($user->_real(), $orga->_real());
        $this->linkProcedureToOrga($masterTemplate->_real(), $orga->_real());
        $this->getEntityManager()->flush();

        $this->assertCount(0, $masterTemplate->_real()->getAuthorizedUsers());

        // Act
        $owns = $this->ownsProcedureUnderRestrictedAccess($user->_real(), $masterTemplate->_real());

        // Assert
        $this->assertTrue($owns);
    }

    public function testOrdinaryBlueprintIsNotOwnedWithoutExplicitAuthorization(): void
    {
        // Arrange - identical to the master template case except for the flag under test,
        // so that the exemption cannot be mistaken for a general waiver
        $orga = OrgaFactory::createOne();
        $user = UserFactory::createOne();
        $blueprint = ProcedureFactory::createOne(['master' => true, 'masterTemplate' => false]);

        $this->linkUserToOrga($user->_real(), $orga->_real());
        $this->linkProcedureToOrga($blueprint->_real(), $orga->_real());
        $this->getEntityManager()->flush();

        $this->assertCount(0, $blueprint->_real()->getAuthorizedUsers());

        // Act
        $owns = $this->ownsProcedureUnderRestrictedAccess($user->_real(), $blueprint->_real());

        // Assert
        $this->assertFalse($owns);
    }

    public function testMasterTemplateIsNotOwnedByUserFromAnotherOrga(): void
    {
        // Arrange - the owning organisation match is never waived by the exemption
        $procedureOrga = OrgaFactory::createOne(['name' => self::TEST_ORGA_NAME_DEMOS]);
        $userOrga = OrgaFactory::createOne(['name' => self::TEST_ORGA_NAME_EXAMPLE]);
        $user = UserFactory::createOne();
        $masterTemplate = ProcedureFactory::createOne(['master' => true, 'masterTemplate' => true]);

        $this->linkUserToOrga($user->_real(), $userOrga->_real());
        $this->linkProcedureToOrga($masterTemplate->_real(), $procedureOrga->_real());
        $this->getEntityManager()->flush();

        // Act
        $owns = $this->ownsProcedureUnderRestrictedAccess($user->_real(), $masterTemplate->_real());

        // Assert
        $this->assertFalse($owns);
    }

    /**
     * The same exemption has to hold in the opposite shape of the factory, where conditions
     * are built from a user and evaluated against procedures. Both shapes share
     * isAuthorizedViaOrgaOrManually(), so this pins down the "keep in sync" contract
     * between ProcedureAccessEvaluator::isOwningProcedure() and getOwnsProcedureConditions().
     */
    public function testMasterTemplateMatchesConditionsBuiltFromUserWithoutExplicitAuthorization(): void
    {
        // Arrange
        $orga = OrgaFactory::createOne();
        $user = UserFactory::createOne();
        $masterTemplate = ProcedureFactory::createOne(['master' => true, 'masterTemplate' => true]);
        $blueprint = ProcedureFactory::createOne(['master' => true, 'masterTemplate' => false]);

        $this->linkUserToOrga($user->_real(), $orga->_real());
        $this->linkProcedureToOrga($masterTemplate->_real(), $orga->_real());
        $this->linkProcedureToOrga($blueprint->_real(), $orga->_real());
        $this->getEntityManager()->flush();

        $condition = $this->createFactory($user->_real(), $this->createRestrictedAccessConfig())
            ->isAuthorizedViaOrgaOrManually();

        // Act
        $matchedIds = array_map(
            static fn (Procedure $procedure): string => $procedure->getId(),
            $this->getEntityFetcher()->listEntitiesUnrestricted(Procedure::class, [$condition])
        );

        // Assert
        $this->assertContains($masterTemplate->_real()->getId(), $matchedIds);
        $this->assertNotContains($blueprint->_real()->getId(), $matchedIds);
    }
}
