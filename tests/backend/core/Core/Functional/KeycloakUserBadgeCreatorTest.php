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

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\KeycloakUserDataMapper;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator\KeycloakUserBadgeCreator;
use demosplan\DemosPlanCoreBundle\ValueObject\KeycloakUserData;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\Base\FunctionalTestCase;
use Tests\Base\MockMethodDefinition;

class KeycloakUserBadgeCreatorTest extends FunctionalTestCase
{
    protected ?KeycloakUserBadgeCreator $keycloakUserBadgeCreator;

    protected function setUp(): void
    {
        $mockMethods = [
            new MockMethodDefinition('getId', 'abcde'),
        ];
        $user = $this->getMock(User::class, $mockMethods);
        $mockMethods = [
            new MockMethodDefinition('mapUserData', $user),
        ];
        $keycloakUserDataMapper = $this->getMock(KeycloakUserDataMapper::class, $mockMethods);

        $this->keycloakUserBadgeCreator = new KeycloakUserBadgeCreator(
            $this->getEntityManagerMock(),
            $this->createMock(KeycloakUserData::class),
            $this->createMock(LoggerInterface::class),
            $keycloakUserDataMapper
        );
    }

    public function testAuthenticate()
    {
        $sessionMethods = [
            new MockMethodDefinition('set', null),
        ];
        $sessionMock = $this->getMock(Session::class, $sessionMethods);
        $mockMethods = [
            new MockMethodDefinition('getSession', $sessionMock),
        ];

        $request = $this->getMock(Request::class, $mockMethods);

        $userBadge = $this->keycloakUserBadgeCreator->createKeycloakUserBadge(
            'userIdentifier',
            $this->createMock(ResourceOwnerInterface::class),
            $request
        );
        $this->assertTrue($userBadge->isResolved());
        $this->assertEquals('userIdentifier', $userBadge->getUserIdentifier());
        $this->assertInstanceOf(User::class, $userBadge->getUser());
    }
}
