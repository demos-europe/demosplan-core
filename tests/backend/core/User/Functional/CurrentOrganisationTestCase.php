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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Base\FunctionalTestCase;

/**
 * Shared setUp/tearDown for CurrentOrganisationService and OrganisationSelectionController tests.
 */
abstract class CurrentOrganisationTestCase extends FunctionalTestCase
{
    protected ?CurrentOrganisationService $sut = null;
    protected ?User $testUser = null;
    protected ?Orga $testOrga = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(CurrentOrganisationService::class);
        $this->testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->testOrga = $this->fixtures->getReference(LoadUserData::TEST_ORGA_FP);

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
}
