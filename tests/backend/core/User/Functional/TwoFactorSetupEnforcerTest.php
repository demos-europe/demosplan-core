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

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\TwoFactorRequirementResolver;
use demosplan\DemosPlanCoreBundle\Logic\User\TwoFactorSetupEnforcer;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Base\FunctionalTestCase;

class TwoFactorSetupEnforcerTest extends FunctionalTestCase
{
    private const FEATURE_ORGA_REQUIREMENT = 'feature_orga_two_factor_required';
    private const FEATURE_2FA = 'feature_2fa';

    protected ?TwoFactorSetupEnforcer $sut = null;
    protected ?TwoFactorRequirementResolver $resolver = null;
    protected ?Session $session = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new TwoFactorRequirementResolver();
        $this->session = new Session(new MockArraySessionStorage());

        // The enforcer reads the session of the current request
        $request = new Request();
        $request->setSession($this->session);
        $requestStack = self::getContainer()->get(RequestStack::class);
        $requestStack->push($request);

        $this->sut = $this->createEnforcer($requestStack);
    }

    protected function tearDown(): void
    {
        $this->sut = null;
        $this->resolver = null;
        $this->session = null;

        parent::tearDown();
    }

    /**
     * @param array<string, bool> $orgas               orga name => requires two-factor
     * @param list<string>        $disabledPermissions
     */
    #[DataProvider('conditionProvider')]
    public function testIsEnforced(
        bool $expected,
        array $orgas = ['A' => true],
        bool $totpEnabled = false,
        bool $emailAuthEnabled = false,
        bool $setupPending = true,
        array $disabledPermissions = [],
    ): void {
        $this->arrangeUser($orgas, $totpEnabled, $emailAuthEnabled, $setupPending, $disabledPermissions);

        self::assertSame($expected, $this->sut->isEnforced());
    }

    public static function conditionProvider(): array
    {
        return [
            'everything holds'                       => [true],
            'one of two orgas requires it'           => [true, ['A' => false, 'B' => true]],
            'orga requirement feature missing'       => [false, ['A' => true], false, false, true, [self::FEATURE_ORGA_REQUIREMENT]],
            'feature 2fa missing'                    => [false, ['A' => true], false, false, true, [self::FEATURE_2FA]],
            'orga does not require it'               => [false, ['A' => false]],
            'no orga at all'                         => [false, []],
            'no session flag'                        => [false, ['A' => true], false, false, false],
            'totp enabled'                           => [false, ['A' => true], true, false],
            'email enabled'                          => [false, ['A' => true], false, true],
        ];
    }

    public function testGetSetupStateListsRequiringOrgasOnly(): void
    {
        $this->arrangeUser(['A' => true, 'B' => false, 'C' => true]);

        $state = $this->sut->getSetupState();

        self::assertTrue($state['required']);
        sort($state['orgaNames']);
        self::assertSame(['A', 'C'], $state['orgaNames']);
    }

    public function testGetSetupStateIsEmptyWhenNotEnforced(): void
    {
        $this->arrangeUser(['A' => true], setupPending: false);

        self::assertSame(['required' => false, 'orgaNames' => [], 'disableLocked' => false], $this->sut->getSetupState());
    }

    #[DataProvider('completionProvider')]
    public function testCompleteSetupIfDone(
        bool $expected,
        bool $setupPending,
        bool $totpEnabled,
        bool $emailAuthEnabled,
    ): void {
        $this->arrangeUser(['A' => true], $totpEnabled, $emailAuthEnabled, $setupPending);

        self::assertSame($expected, $this->sut->completeSetupIfDone());
        // The flag is only removed when the setup is completed
        self::assertSame($setupPending && !$expected, $this->resolver->isSetupPending($this->session));
    }

    public static function completionProvider(): array
    {
        return [
            'pending and totp set up'  => [true, true, true, false],
            'pending and email set up' => [true, true, false, true],
            'pending, still no factor' => [false, true, false, false],
            'not pending with factor'  => [false, false, true, false],
        ];
    }

    #[DataProvider('disableInputProvider')]
    public function testProfileTemplateHidesDisableInputOfLastFactor(
        bool $totpEnabled,
        bool $emailAuthEnabled,
        bool $orgaRequires,
        bool $expectLocked,
    ): void {
        $user = $this->arrangeUser(['A' => $orgaRequires], $totpEnabled, $emailAuthEnabled, false);
        $user->setTotpSecret('SECRET');

        $html = self::getContainer()->get('twig')->render(
            '@DemosPlanCore/DemosPlanUser/portal_profile.html.twig',
            ['templateVars' => ['twoFactorSetup' => $this->sut->getSetupState()], 'user' => $user, 'hasIdentityprovider' => false, 'projectName' => 'test']
        );

        $hint = 'Der letzte eingerichtete Faktor kann deshalb nicht deaktiviert werden';
        self::assertSame($expectLocked, str_contains($html, $hint));
        self::assertSame($totpEnabled && !$expectLocked, str_contains($html, 'name="disableTwoFactorCode"'));
        self::assertSame($emailAuthEnabled && !$expectLocked, str_contains($html, 'name="disableTwoFactorCodeEmail"'));
    }

    public static function disableInputProvider(): array
    {
        return [
            'only totp, required'  => [true, false, true, true],
            'only email, required' => [false, true, true, true],
            'both, required'       => [true, true, true, false],
            'only totp, optional'  => [true, false, false, false],
            'only email, optional' => [false, true, false, false],
        ];
    }

    #[DataProvider('renderProvider')]
    public function testProfileTemplateRendersHintOnlyWhenRequired(bool $required): void
    {
        $user = $this->arrangeUser(['<b>Tatort</b>' => true], setupPending: $required);
        $state = $this->sut->getSetupState();

        $html = self::getContainer()->get('twig')->render(
            '@DemosPlanCore/DemosPlanUser/portal_profile.html.twig',
            ['templateVars' => ['twoFactorSetup' => $state], 'user' => $user, 'hasIdentityprovider' => false, 'projectName' => 'test']
        );

        self::assertSame($required, str_contains($html, 'verlangt eine Zwei-Faktor-Authentifizierung'));
        self::assertStringNotContainsString('<b>Tatort</b>', $html);
    }

    /**
     * @param array<string, bool> $orgas
     * @param list<string>        $disabledPermissions
     */
    #[DataProvider('requirementProvider')]
    public function testIsRequirementActiveAndDisableLocked(
        bool $expectedActive,
        bool $expectedLocked,
        array $orgas,
        bool $totpEnabled,
        bool $emailAuthEnabled,
        array $disabledPermissions = [],
    ): void {
        // The session flag is irrelevant for the requirement
        $this->arrangeUser($orgas, $totpEnabled, $emailAuthEnabled, false, $disabledPermissions);

        self::assertSame($expectedActive, $this->sut->isRequirementActive());
        self::assertSame($expectedLocked, $this->sut->getSetupState()['disableLocked']);
    }

    public static function requirementProvider(): array
    {
        return [
            'only totp'                  => [true, true, ['A' => true], true, false],
            'only email'                 => [true, true, ['A' => true], false, true],
            'both factors'               => [true, false, ['A' => true], true, true],
            'no factor'                  => [true, false, ['A' => true], false, false],
            'orga does not require it'   => [false, false, ['A' => false], true, false],
            'orga feature missing'       => [false, false, ['A' => true], true, false, [self::FEATURE_ORGA_REQUIREMENT]],
            'feature 2fa missing'        => [false, false, ['A' => true], true, false, [self::FEATURE_2FA]],
        ];
    }

    public function testIsDisableLockedForIgnoresOtherUsers(): void
    {
        $this->arrangeUser(['A' => true], true, false);
        $other = UserFactory::createOne(['totpEnabled' => true, 'authCodeEmailEnabled' => false])->_real();

        self::assertFalse($this->sut->isDisableLockedFor($other, 'totp'));
    }

    public function testNothingIsEnforcedWithoutRequest(): void
    {
        $this->arrangeUser(['A' => true]);
        $sut = $this->createEnforcer(new RequestStack());

        self::assertFalse($sut->isEnforced());
        self::assertFalse($sut->completeSetupIfDone());
        self::assertSame(['required' => false, 'orgaNames' => [], 'disableLocked' => false], $sut->getSetupState());
    }

    public static function renderProvider(): array
    {
        return [
            'pending'     => [true],
            'not pending' => [false],
        ];
    }

    private function createEnforcer(RequestStack $requestStack): TwoFactorSetupEnforcer
    {
        return new TwoFactorSetupEnforcer(
            self::getContainer()->get(CurrentUserInterface::class),
            $this->resolver,
            $requestStack
        );
    }

    /**
     * Logs in a user with one organisation per entry, so the organisations are read from the database.
     *
     * @param array<string, bool> $orgas               orga name => requires two-factor
     * @param list<string>        $disabledPermissions
     */
    private function arrangeUser(
        array $orgas,
        bool $totpEnabled = false,
        bool $emailAuthEnabled = false,
        bool $setupPending = true,
        array $disabledPermissions = [],
    ): User {
        $user = UserFactory::createOne([
            'totpEnabled'          => $totpEnabled,
            'authCodeEmailEnabled' => $emailAuthEnabled,
        ]);
        foreach ($orgas as $name => $twoFactorRequired) {
            OrgaFactory::createOne([
                'name'              => (string) $name,
                'deleted'           => false,
                'twoFactorRequired' => $twoFactorRequired,
                'users'             => [$user->_real()],
            ]);
        }
        $user->_refresh();

        $this->logIn($user->_real());
        $this->enablePermissions([self::FEATURE_ORGA_REQUIREMENT, self::FEATURE_2FA]);
        if ([] !== $disabledPermissions) {
            $this->disablePermissions($disabledPermissions);
        }
        if ($setupPending) {
            $this->resolver->markSetupPending($this->session);
        }

        return $user->_real();
    }
}
