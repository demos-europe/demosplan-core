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
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentOrganisationService;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Base\FunctionalTestCase;

class CurrentOrganisationServiceTest extends FunctionalTestCase
{
    protected ?CurrentOrganisationService $sut = null;
    protected ?User $testUser = null;
    protected ?Orga $testOrga = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(CurrentOrganisationService::class);
        $this->testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        // Use testOrgaFP since testUser belongs to it
        $this->testOrga = $this->fixtures->getReference(LoadUserData::TEST_ORGA_FP);

        // Set up a mock session
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        self::getContainer()->get('request_stack')->push($request);
    }

    protected function tearDown(): void
    {
        $this->sut = null;
        $this->testUser = null;
        $this->testOrga = null;

        parent::tearDown();
    }

    public function testSetAndGetCurrentOrganisation(): void
    {
        // Set the current organisation
        $this->sut->setCurrentOrganisation($this->testUser, $this->testOrga);

        // Get should return the same organisation
        $current = $this->sut->getCurrentOrganisation($this->testUser);

        self::assertNotNull($current);
        self::assertSame($this->testOrga->getId(), $current->getId());
    }

    public function testSetCurrentOrganisationThrowsExceptionForNonMemberOrganisation(): void
    {
        // Use an existing orga that the user doesn't belong to
        // testOrgaPB is a different orga that testUser doesn't belong to
        $foreignOrga = $this->fixtures->getReference(LoadUserData::TEST_ORGA_PB);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/does not belong to organisation/');

        $this->sut->setCurrentOrganisation($this->testUser, $foreignOrga);
    }

    public function testClearCurrentOrganisation(): void
    {
        // First set an organisation
        $this->sut->setCurrentOrganisation($this->testUser, $this->testOrga);

        // Verify it's set
        self::assertNotNull($this->sut->getCurrentOrganisation($this->testUser));

        // Clear it
        $this->sut->clearCurrentOrganisation();

        // Create a fresh user instance without transient property to test session clearing
        $freshUser = $this->getEntityManager()->find(User::class, $this->testUser->getId());

        // The session should be cleared, so getCurrentOrganisation should fall back
        $current = $this->sut->getCurrentOrganisation($freshUser);

        // Should return the first organisation in the collection as fallback
        self::assertNotNull($current);
    }

    public function testGetCurrentOrganisationFallsBackToFirstOrganisation(): void
    {
        // Without setting anything, should return the first organisation
        $current = $this->sut->getCurrentOrganisation($this->testUser);

        self::assertNotNull($current);
        // Should be one of the user's organisations
        self::assertTrue($this->testUser->getOrganisations()->contains($current));
    }

    public function testInitializeCurrentOrganisation(): void
    {
        // Set organisation in session
        $this->sut->setCurrentOrganisation($this->testUser, $this->testOrga);

        // Clear transient property
        $this->testUser->setCurrentOrganisation(null);

        // Initialize should restore from session
        $this->sut->initializeCurrentOrganisation($this->testUser);

        self::assertNotNull($this->testUser->getCurrentOrganisation());
        self::assertSame($this->testOrga->getId(), $this->testUser->getCurrentOrganisation()->getId());
    }

    public function testHasMultipleOrganisationsReturnsFalseForSingleOrgUser(): void
    {
        // testUser belongs to testOrgaFP only initially
        // Without modification, this tests single org behavior
        self::assertFalse($this->sut->hasMultipleOrganisations($this->testUser));
    }

    public function testRequiresOrganisationSelectionReturnsFalseForSingleOrgUser(): void
    {
        // For a user with only one org, selection should never be required
        // Use a user fixture that has only one organisation
        $singleOrgUser = $this->fixtures->getReference(LoadUserData::TEST_USER_FP_ONLY);

        // Clear any session to ensure clean state
        $this->sut->clearCurrentOrganisation();

        self::assertFalse($this->sut->requiresOrganisationSelection($singleOrgUser));
    }

    public function testGetAvailableOrganisations(): void
    {
        $orgs = $this->sut->getAvailableOrganisations($this->testUser);

        self::assertNotEmpty($orgs);
        self::assertContains($this->testOrga, $orgs->toArray());
    }

    public function testFindOrganisationByGwId(): void
    {
        // Use existing fixture - testOrgaFP has a gwId set
        $found = $this->sut->findOrganisationByGwId($this->testOrga->getGwId());

        self::assertNotNull($found);
        self::assertSame($this->testOrga->getGwId(), $found->getGwId());
    }

    public function testFindOrganisationByGwIdReturnsNullForUnknown(): void
    {
        $found = $this->sut->findOrganisationByGwId('nonexistent-gwid-'.uniqid());

        self::assertNull($found);
    }
}
