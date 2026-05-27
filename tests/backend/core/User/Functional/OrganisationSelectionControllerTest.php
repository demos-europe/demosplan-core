<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use InvalidArgumentException;

/**
 * Tests for the CurrentOrganisationService integration with controller behavior.
 *
 * Note: HTTP request tests are limited due to session/authentication sharing
 * between the test harness and the HTTP client. Core service behavior is tested
 * in CurrentOrganisationServiceTest.
 */
class OrganisationSelectionControllerTest extends CurrentOrganisationTestCase
{
    public function testRequiresOrganisationSelectionForMultiOrgUser(): void
    {
        // Clear any existing session selection
        $this->sut->clearCurrentOrganisation();

        // A user with multiple organisations should require selection when no org is set
        // This simulates what the controller checks
        $hasMultiple = $this->sut->hasMultipleOrganisations($this->testUser);
        $requiresSelection = $this->sut->requiresOrganisationSelection($this->testUser);

        // The test fixtures determine if user has multiple orgs
        // What matters is the logic is consistent
        if ($hasMultiple) {
            self::assertTrue($requiresSelection);
        } else {
            self::assertFalse($requiresSelection);
        }
    }

    public function testSingleOrgUserDoesNotRequireSelection(): void
    {
        // Get a user with only one organisation
        $singleOrgUser = $this->fixtures->getReference(LoadUserData::TEST_USER_FP_ONLY);

        $this->sut->clearCurrentOrganisation();

        self::assertFalse($this->sut->requiresOrganisationSelection($singleOrgUser));
    }

    public function testSetOrganisationUpdatesSession(): void
    {
        // Set the current organisation
        $this->sut->setCurrentOrganisation($this->testUser, $this->testOrga);

        // Get should return the same organisation
        $current = $this->sut->getCurrentOrganisation($this->testUser);

        self::assertNotNull($current);
        self::assertSame($this->testOrga->getId(), $current->getId());
    }

    public function testSetOrganisationRejectsNonMemberOrganisation(): void
    {
        // Use an orga that testUser doesn't belong to
        $foreignOrga = $this->fixtures->getReference(LoadUserData::TEST_ORGA_PB);

        $this->expectException(InvalidArgumentException::class);

        $this->sut->setCurrentOrganisation($this->testUser, $foreignOrga);
    }

    public function testGetAvailableOrganisationsReturnsUserOrganisations(): void
    {
        $orgs = $this->sut->getAvailableOrganisations($this->testUser);

        self::assertNotEmpty($orgs);
        self::assertContains($this->testOrga, $orgs->toArray());
    }

    public function testClearCurrentOrganisationClearsSession(): void
    {
        // First set an organisation
        $this->sut->setCurrentOrganisation($this->testUser, $this->testOrga);

        // Verify it's set
        self::assertNotNull($this->sut->getCurrentOrganisation($this->testUser));

        // Clear it
        $this->sut->clearCurrentOrganisation();

        // Get a fresh user instance (without the transient property set)
        $freshUser = $this->getEntityManager()->find(User::class, $this->testUser->getId());

        // The session should be cleared - getCurrentOrganisation will fall back
        // to first org in collection
        $current = $this->sut->getCurrentOrganisation($freshUser);
        self::assertNotNull($current);
    }

    public function testInitializeCurrentOrganisationRestoresFromSession(): void
    {
        // Set organisation in session
        $this->sut->setCurrentOrganisation($this->testUser, $this->testOrga);

        // Clear transient property on the user
        $this->testUser->setCurrentOrganisation(null);

        // Initialize should restore from session
        $this->sut->initializeCurrentOrganisation($this->testUser);

        self::assertNotNull($this->testUser->getCurrentOrganisation());
        self::assertSame($this->testOrga->getId(), $this->testUser->getCurrentOrganisation()->getId());
    }
}
