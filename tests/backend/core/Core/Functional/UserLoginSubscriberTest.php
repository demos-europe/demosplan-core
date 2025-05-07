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
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventSubscriber\UserLoginSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Tests\Base\FunctionalTestCase;
use Tests\Base\MockMethodDefinition;

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

    public function setUp(): void
    {
        parent::setUp();

        $this->testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $this->userService = self::getContainer()->get(UserService::class);
        $this->tokenStorage = self::getContainer()->get('security.token_storage');
        $this->sut = new UserLoginSubscriber($this->userService);
        $this->logIn($this->testUser);
    }

    public function testLoginUserLastLogin()
    {
        $tokenMockMethods = [
            new MockMethodDefinition('getUser', $this->testUser),
        ];
        $token = $this->getMock(TokenInterface::class, $tokenMockMethods);
        $event = new AuthenticationSuccessEvent($token);
        $formerLogin = new Carbon($this->testUser->getLastLogin());
        $this->sut->onLogin($event);
        self::assertNotNull($this->testUser->getLastLogin());
        $afterLogin = new Carbon($this->testUser->getLastLogin());
        self::assertTrue($afterLogin->greaterThan($formerLogin));
    }
}
