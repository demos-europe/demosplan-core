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

use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadCustomerData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Logic\KeycloakUserDataMapper;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\ValueObject\KeycloakUserData;
use Psr\Log\NullLogger;
use Stevenmaguire\OAuth2\Client\Provider\KeycloakResourceOwner;
use Tests\Base\FunctionalTestCase;
use Tests\Base\MockMethodDefinition;

class KeycloakUserDataMapperTest extends FunctionalTestCase
{
    private ?KeycloakUserDataMapper $keycloakUserDataMapper;

    /**
     * @var User
     */
    protected $citizenUser;

    protected function setUp(): void
    {
        parent::setUp();

        $customerMockMethods = [
            new MockMethodDefinition('getCurrentCustomer', $this->fixtures->getReference(LoadCustomerData::BRANDENBURG)),
        ];
        $customerService = $this->getMock(CustomerService::class, $customerMockMethods);

        $eventDispatcher = $this->getContainer()->get(EventDispatcherPostInterface::class);

        $this->citizenUser = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN);

        $orgaService = $this->getContainer()->get(OrgaService::class);

        $roleHandler = $this->getContainer()->get(RoleHandler::class);

        $userService = $this->getContainer()->get(UserService::class);

        $this->keycloakUserDataMapper = new KeycloakUserDataMapper($customerService, $eventDispatcher, new NullLogger(), $orgaService, $roleHandler, $userService);
    }

    public function testCreateNewPublicUser(): void
    {
        $attributes = [
            'localityName'     => 'Test City',
            'houseNumber'      => '10',
            'givenName'        => 'John',
            'email'            => 'test@example.com',
            'surname'          => 'Doe',
            'organisationName' => '',
            'street'           => 'Test Street',
        ];
        $resourceOwner = new KeycloakResourceOwner($attributes);
        $userData = new KeycloakUserData();
        $userData->fill($resourceOwner);

        $user = $this->keycloakUserDataMapper->mapUserData($userData);
        self::assertInstanceOf(User::class, $user);
        $anonymousUser = new AnonymousUser();
        self::assertEquals($anonymousUser->getOrga()->getId(), $user->getOrga()->getId());
        self::assertEquals($attributes['email'], $user->getLogin());
    }

    public function testCreateNewOrgaUser(): void
    {
        $attributes = [
            'email'              => 'test@example.com',
            'givenName'          => 'John',
            'surname'            => 'Doe',
            'organisationId'     => '123',
            'organisationName'   => 'Test Organisation',
            'sub'                => '456',
            'preferred_username' => 'johndoe',
            'houseNumber'        => '10',
            'localityName'       => 'Test City',
            'postalCode'         => '12345',
            'street'             => 'Test Street',
        ];
        $resourceOwner = new KeycloakResourceOwner($attributes);
        $userData = new KeycloakUserData();
        $userData->fill($resourceOwner);

        $user = $this->keycloakUserDataMapper->mapUserData($userData);
        self::assertInstanceOf(User::class, $user);
        $anonymousUser = new AnonymousUser();
        self::assertNotEquals($anonymousUser->getOrga()->getId(), $user->getOrga()->getId());
        self::assertEquals($attributes['email'], $user->getLogin());
    }

    public function testCreateNewUserFromServicekonto(): void
    {
        $attributes = [
            'email'       => 'Sarah@connell.de',
            'givenName'   => 'Sarah',
            'bPK'         => 'VjEuQkI6OmRlLmFrZGIuYnBrLnNzb0Bsb2dpbi1zdGFnZS5iYXVsZWl0cGxhbnVuZy1vbmxpbmUuZGU6OmxvUlF1c25qTXQ0bVN4eUFtNExQZkZPbTdjd1JiWjRQc3FFNTltcERWbnc6OjIwMjItMDktMjNUMTU6MTQ6NTM=',
            'surname'     => 'Connell',
            'ID'          => 'loRQusnjMt4mSxyAm4LPfFOm7cwRbZ4PsqE59mpDVnw',
            'sessionIndex'=> '54204e12-af28-4b20-806b-e21fced6ad8a::30065ff8-40b7-42b7-82c4-80c26d993959',
        ];
        $resourceOwner = new KeycloakResourceOwner($attributes);
        $userData = new KeycloakUserData();
        $userData->fill($resourceOwner);

        $user = $this->keycloakUserDataMapper->mapUserData($userData);
        self::assertInstanceOf(User::class, $user);
        $anonymousUser = new AnonymousUser();
        self::assertEquals($anonymousUser->getOrga()->getId(), $user->getOrga()->getId());
        self::assertEquals($attributes['email'], $user->getLogin());
        self::assertEquals($attributes['email'], $user->getEmail());
        self::assertEquals($attributes['givenName'], $user->getFirstname());
        self::assertEquals($attributes['surname'], $user->getLastname());
    }

    public function testCreateNewOrgaNoUser(): void
    {
        $attributes = $this->getOrgaLoginAttributes();
        $resourceOwner = new KeycloakResourceOwner($attributes);
        $userData = new KeycloakUserData();
        $userData->fill($resourceOwner);

        $user = $this->keycloakUserDataMapper->mapUserData($userData);
        self::assertInstanceOf(User::class, $user);
        $orga = $user->getOrga();
        self::assertEquals($attributes['organisationName'], $orga->getName());
        self::assertEquals($attributes['houseNumber'], $orga->getHouseNumber());
        self::assertEquals($attributes['localityName'], $orga->getCity());
        self::assertEquals($attributes['postalCode'], $orga->getPostalcode());
        self::assertEquals($attributes['street'], $orga->getStreet());
        self::assertEquals(UserInterface::DEFAULT_ORGA_USER_NAME, $user->getLastname());
        self::assertEquals($attributes['email'], $user->getLogin());
        self::assertTrue($user->isProvidedByIdentityProvider());
    }

    public function testUpdateOrga(): void
    {
        $attributes = $this->getOrgaLoginAttributes();
        $resourceOwner = new KeycloakResourceOwner($attributes);
        $userData = new KeycloakUserData();
        $userData->fill($resourceOwner);

        $user = $this->keycloakUserDataMapper->mapUserData($userData);
        self::assertInstanceOf(User::class, $user);

        $attributes['localityName'] = 'Neustadt';
        $resourceOwner = new KeycloakResourceOwner($attributes);
        $userData = new KeycloakUserData();
        $userData->fill($resourceOwner);
        $user = $this->keycloakUserDataMapper->mapUserData($userData);

        $orga = $user->getOrga();
        self::assertEquals($attributes['organisationName'], $orga->getName());
        self::assertEquals($attributes['houseNumber'], $orga->getHouseNumber());
        self::assertEquals($attributes['localityName'], $orga->getCity());
        self::assertEquals($attributes['postalCode'], $orga->getPostalcode());
        self::assertEquals($attributes['street'], $orga->getStreet());
        self::assertEquals($attributes['organisationId'], $orga->getGatewayName());
        self::assertEquals($attributes['email'], $user->getLogin());
    }

    private function getOrgaLoginAttributes(): array
    {
        return [
            'country'                   => 'de',
            'givenName'                 => 'Organisation',
            'surname'                   => 'Administration',
            'houseNumber'               => '14',
            'localityName'              => 'Erlangen',
            'email'                     => 'mail.needs@tobes.et',
            'orgaMail'                  => 'orgaMailneeds@tobes.et',
            'organisationName'          => 'Franken Plus GmbH & Co. KGaA',
            'organisationId'            => 'du-886227c04d14045c16c42d706427d8392fd64417',
            'orgaType'                  => 'NNatPers',
            'postalCode'                => '91058',
            'street'                    => 'Frauenweiherstr.',
        ];
    }
}
