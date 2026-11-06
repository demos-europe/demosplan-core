<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventSubscriber\TwoFactorSetupRequirementSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\User\TwoFactorRequirementResolver;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use Scheb\TwoFactorBundle\Security\Http\Authenticator\TwoFactorAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Tests\Base\FunctionalTestCase;

class TwoFactorSetupRequirementSubscriberTest extends FunctionalTestCase
{
    protected ?TwoFactorSetupRequirementSubscriber $sut = null;
    protected ?TwoFactorRequirementResolver $resolver = null;
    protected ?Session $session = null;
    protected ?TestHandler $logHandler = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new TwoFactorRequirementResolver();
        $this->sut = new TwoFactorSetupRequirementSubscriber($this->resolver);
        $this->logHandler = new TestHandler();
        $this->sut->setLogger(new Logger('test', [$this->logHandler]));
        $this->session = new Session(new MockArraySessionStorage());
    }

    protected function tearDown(): void
    {
        $this->sut = null;
        $this->resolver = null;
        $this->session = null;
        $this->logHandler = null;

        parent::tearDown();
    }

    public function testSubscribesToLoginSuccessEvent(): void
    {
        self::assertArrayHasKey(LoginSuccessEvent::class, TwoFactorSetupRequirementSubscriber::getSubscribedEvents());
    }

    /**
     * @param list<bool> $orgaRequirements
     */
    #[DataProvider('loginProvider')]
    public function testFlagIsSetOnlyIfRequiredAndNoFactor(
        array $orgaRequirements,
        bool $totpEnabled,
        bool $emailAuthEnabled,
        bool $expectedFlag,
    ): void {
        $user = $this->createUser($orgaRequirements);
        $user->setTotpEnabled($totpEnabled);
        $user->setAuthCodeEmailEnabled($emailAuthEnabled);

        $this->sut->onLogin($this->createEvent($user, $this->createRequest()));

        self::assertSame($expectedFlag, $this->resolver->isSetupPending($this->session));
        self::assertSame($expectedFlag, $this->logHandler->hasInfoThatContains('Two-factor setup required at login'));
    }

    public static function loginProvider(): array
    {
        return [
            'orga requires, no factor'     => [[true], false, false, true],
            'orga requires, totp enabled'  => [[true], true, false, false],
            'orga requires, email enabled' => [[true], false, true, false],
            'orga does not require'        => [[false], false, false, false],
            'one of several orgas'         => [[false, true], false, false, true],
        ];
    }

    public function testJwtTokenIsSkipped(): void
    {
        $user = $this->createUser([true]);
        $token = new JWTUserToken([], $user, 'raw-jwt');

        $this->sut->onLogin($this->createEvent($user, $this->createRequest(), null, $token));

        self::assertFalse($this->resolver->isSetupPending($this->session));
    }

    public function testFunctionalUserIsSkipped(): void
    {
        $user = new FunctionalUser();
        $user->addOrganisation($this->createOrga(true));

        $this->sut->onLogin($this->createEvent($user, $this->createRequest()));

        self::assertFalse($this->resolver->isSetupPending($this->session));
    }

    public function testSchebTwoFactorAuthenticatorIsSkipped(): void
    {
        $user = $this->createUser([true]);
        $authenticator = self::createStub(TwoFactorAuthenticator::class);

        $this->sut->onLogin($this->createEvent($user, $this->createRequest(), $authenticator));

        self::assertFalse($this->resolver->isSetupPending($this->session));
    }

    public function testRequestWithoutSessionDoesNotThrow(): void
    {
        $user = $this->createUser([true]);

        $this->sut->onLogin($this->createEvent($user, $this->createRequest(false)));

        self::assertFalse($this->resolver->isSetupPending($this->session));
    }

    private function createRequest(bool $withSession = true): Request
    {
        $request = new Request();
        if ($withSession) {
            $request->setSession($this->session);
        }

        return $request;
    }

    private function createEvent(
        User $user,
        Request $request,
        ?AuthenticatorInterface $authenticator = null,
        ?TokenInterface $token = null,
    ): LoginSuccessEvent {
        return new LoginSuccessEvent(
            $authenticator ?? $this->createAuthenticatorStub(),
            new SelfValidatingPassport(new UserBadge('login', static fn () => $user)),
            $token ?? new UsernamePasswordToken($user, 'main', []),
            $request,
            null,
            'main'
        );
    }

    private function createAuthenticatorStub(): AuthenticatorInterface
    {
        return self::createStub(AuthenticatorInterface::class);
    }

    /**
     * Persists the user in one organisation per given requirement flag and reloads it,
     * so the organisations are read from the database.
     *
     * @param list<bool> $orgaRequirements
     */
    private function createUser(array $orgaRequirements): User
    {
        $user = UserFactory::createOne([
            'totpEnabled'          => false,
            'authCodeEmailEnabled' => false,
        ]);

        foreach ($orgaRequirements as $twoFactorRequired) {
            OrgaFactory::createOne([
                'deleted'           => false,
                'twoFactorRequired' => $twoFactorRequired,
                'users'             => [$user->_real()],
            ]);
        }

        $user->_refresh();

        return $user->_real();
    }

    private function createOrga(bool $twoFactorRequired): Orga
    {
        return OrgaFactory::createOne([
            'deleted'           => false,
            'twoFactorRequired' => $twoFactorRequired,
        ])->_real();
    }
}
