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

use Carbon\Carbon;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\AccountDeletionTracking;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventSubscriber\UserLoginSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\AccountDeletionTrackingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Tests\Base\FunctionalTestCase;

class UserLoginSubscriberTest extends FunctionalTestCase
{
    /**
     * @var User
     */
    protected $testUser;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var UserService
     */
    protected $userService;

    /**
     * @var UserLoginSubscriber
     */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $this->userService = self::getContainer()->get(UserService::class);
        $this->tokenStorage = self::getContainer()->get('security.token_storage');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AccountDeletionTrackingRepository $trackingRepository */
        $trackingRepository = $entityManager->getRepository(AccountDeletionTracking::class);
        $this->sut = new UserLoginSubscriber(
            $this->userService,
            $trackingRepository,
            $entityManager
        );
        $this->logIn($this->testUser);
    }

    public function testLoginUserLastLogin()
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($this->testUser);
        $event = new LoginSuccessEvent(
            $this->createMock(AuthenticatorInterface::class),
            $this->createMock(Passport::class),
            $token,
            $this->createMock(Request::class),
            null,
            'main'
        );
        $formerLogin = new Carbon($this->testUser->getLastLogin());
        $this->sut->onLogin($event);
        self::assertNotNull($this->testUser->getLastLogin());
        $afterLogin = new Carbon($this->testUser->getLastLogin());
        self::assertTrue($afterLogin->greaterThan($formerLogin));
    }

    public function testLoginRemovesAccountDeletionTracking(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $trackingRepository = $entityManager->getRepository(AccountDeletionTracking::class);

        $tracking = new AccountDeletionTracking($this->testUser);
        $entityManager->persist($tracking);
        $entityManager->flush();
        self::assertNotNull($trackingRepository->findOneBy(['user' => $this->testUser]));

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($this->testUser);
        $event = new LoginSuccessEvent(
            $this->createMock(AuthenticatorInterface::class),
            $this->createMock(Passport::class),
            $token,
            $this->createMock(Request::class),
            null,
            'main'
        );

        $this->sut->onLogin($event);

        self::assertNull($trackingRepository->findOneBy(['user' => $this->testUser]));
    }
}
