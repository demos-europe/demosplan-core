<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Unit;

use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\TwoFactorRequirementResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class TwoFactorRequirementResolverTest extends TestCase
{
    protected ?TwoFactorRequirementResolver $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new TwoFactorRequirementResolver();
    }

    protected function tearDown(): void
    {
        $this->sut = null;

        parent::tearDown();
    }

    public function testRequiredForSingleRequiringOrga(): void
    {
        $user = $this->createUser([$this->createOrga('A', true)]);

        self::assertTrue($this->sut->isRequiredForUser($user));
    }

    public function testNotRequiredForSingleNonRequiringOrga(): void
    {
        $user = $this->createUser([$this->createOrga('A', false)]);

        self::assertFalse($this->sut->isRequiredForUser($user));
    }

    public function testRequiredIfOnlyOneOfMultipleOrgasRequires(): void
    {
        $user = $this->createUser([
            $this->createOrga('A', false),
            $this->createOrga('B', true),
            $this->createOrga('C', false),
        ]);

        self::assertTrue($this->sut->isRequiredForUser($user));
    }

    public function testNotRequiredWithoutOrgas(): void
    {
        $user = $this->createUser([]);

        self::assertFalse($this->sut->isRequiredForUser($user));
        self::assertSame([], $this->sut->getRequiringOrgaNames($user));
    }

    public function testHasSecondFactorViaTotp(): void
    {
        $user = $this->createUser([]);
        $user->setTotpEnabled(true);

        self::assertTrue($this->sut->hasSecondFactor($user));
    }

    public function testHasSecondFactorViaEmail(): void
    {
        $user = $this->createUser([]);
        $user->setAuthCodeEmailEnabled(true);

        self::assertTrue($this->sut->hasSecondFactor($user));
    }

    public function testHasNoSecondFactor(): void
    {
        $user = $this->createUser([]);

        self::assertFalse($this->sut->hasSecondFactor($user));
    }

    public function testGetRequiringOrgaNamesOnlyContainsRequiringOrgas(): void
    {
        $user = $this->createUser([
            $this->createOrga('A', true),
            $this->createOrga('B', false),
            $this->createOrga('C', true),
        ]);

        self::assertSame(['A', 'C'], $this->sut->getRequiringOrgaNames($user));
    }

    public function testSetupPendingSessionRoundTrip(): void
    {
        $session = new Session(new MockArraySessionStorage());

        self::assertFalse($this->sut->isSetupPending($session));

        $this->sut->markSetupPending($session);
        self::assertTrue($this->sut->isSetupPending($session));
        self::assertTrue($session->get(TwoFactorRequirementResolver::SESSION_KEY_SETUP_PENDING));

        $this->sut->clearSetupPending($session);
        self::assertFalse($this->sut->isSetupPending($session));
    }

    /**
     * @param list<Orga> $orgas
     */
    private function createUser(array $orgas): User
    {
        $user = new User();
        foreach ($orgas as $orga) {
            $user->addOrganisation($orga);
        }

        return $user;
    }

    private function createOrga(string $name, bool $twoFactorRequired): Orga
    {
        $orga = new Orga();
        $orga->setName($name);
        $orga->setTwoFactorRequired($twoFactorRequired);

        return $orga;
    }
}
