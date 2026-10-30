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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\TwoFactorRequirementResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Base\FunctionalTestCase;

class TwoFactorRequirementResolverTest extends FunctionalTestCase
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

    /**
     * @param array<string, bool> $orgas
     */
    #[DataProvider('requirementProvider')]
    public function testIsRequiredForUser(array $orgas, bool $expected): void
    {
        $user = $this->createUserInOrgas($orgas);

        self::assertSame($expected, $this->sut->isRequiredForUser($user));
    }

    public static function requirementProvider(): array
    {
        return [
            'single requiring orga'       => [['A' => true], true],
            'single non-requiring orga'   => [['A' => false], false],
            'one of several orgas'        => [['A' => false, 'B' => true, 'C' => false], true],
            'no orgas'                    => [[], false],
        ];
    }

    #[DataProvider('secondFactorProvider')]
    public function testHasSecondFactor(bool $totpEnabled, bool $emailAuthEnabled, bool $expected): void
    {
        $user = $this->createUserInOrgas([], $totpEnabled, $emailAuthEnabled);

        self::assertSame($expected, $this->sut->hasSecondFactor($user));
    }

    public static function secondFactorProvider(): array
    {
        return [
            'totp'      => [true, false, true],
            'email'     => [false, true, true],
            'no factor' => [false, false, false],
        ];
    }

    public function testGetRequiringOrgaNamesOnlyContainsRequiringOrgas(): void
    {
        $user = $this->createUserInOrgas(['A' => true, 'B' => false, 'C' => true]);

        $names = $this->sut->getRequiringOrgaNames($user);
        sort($names);

        self::assertSame(['A', 'C'], $names);
    }

    public function testGetRequiringOrgaNamesIsEmptyWithoutOrgas(): void
    {
        $user = $this->createUserInOrgas([]);

        self::assertSame([], $this->sut->getRequiringOrgaNames($user));
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
     * Persists a user in the given organisations and reloads it, so the organisations
     * are read from the database.
     *
     * @param array<string, bool> $orgas orga name => two-factor required
     */
    private function createUserInOrgas(array $orgas, bool $totpEnabled = false, bool $emailAuthEnabled = false): User
    {
        $user = UserFactory::createOne([
            'totpEnabled'          => $totpEnabled,
            'authCodeEmailEnabled' => $emailAuthEnabled,
        ]);

        foreach ($orgas as $name => $twoFactorRequired) {
            OrgaFactory::createOne([
                'name'              => $name,
                'deleted'           => false,
                'twoFactorRequired' => $twoFactorRequired,
                'users'             => [$user->_real()],
            ]);
        }

        $user->_refresh();

        return $user->_real();
    }
}
