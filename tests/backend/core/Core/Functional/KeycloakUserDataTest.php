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

use demosplan\DemosPlanCoreBundle\ValueObject\KeycloakUserData;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Tests\Base\FunctionalTestCase;

class KeycloakUserDataTest extends FunctionalTestCase
{
    public const TEST_EMAIL = 'test@example.com';
    private ?KeycloakUserData $keycloakUserData;

    protected function setUp(): void
    {
        $this->keycloakUserData = new KeycloakUserData();
    }

    public function testUserInformationIsCorrectlyFilledFromResourceOwner(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'            => self::TEST_EMAIL,
                'givenName'        => 'John',
                'surname'          => 'Doe',
                'organisationId'   => '123',
                'organisationName' => 'Test Organisation',
                'sub'              => '456',
                'houseNumber'      => '10',
                'localityName'     => 'Test City',
                'postalCode'       => '12345',
                'street'           => 'Test Street',
            ]);

        $this->keycloakUserData->fill($resourceOwner);

        $this->assertEquals(
            self::TEST_EMAIL,
            $this->keycloakUserData->getEmailAddress()
        );
        $this->assertEquals('John', $this->keycloakUserData->getFirstName());
        $this->assertEquals('Doe', $this->keycloakUserData->getLastName());
        $this->assertEquals(
            '123',
            $this->keycloakUserData->getOrganisationId()
        );
        $this->assertEquals(
            'Test Organisation',
            $this->keycloakUserData->getOrganisationName()
        );
        $this->assertEquals('456', $this->keycloakUserData->getUserId());
        $this->assertEquals(self::TEST_EMAIL, $this->keycloakUserData->getUserName());
        $this->assertEquals('10', $this->keycloakUserData->getHouseNumber());
        $this->assertEquals('Test City', $this->keycloakUserData->getLocalityName());
        $this->assertEquals('12345', $this->keycloakUserData->getPostalCode());
        $this->assertEquals('Test Street', $this->keycloakUserData->getStreet());
    }

    public function testUserInformationIsCorrectlyFilledFromIncompleteResourceOwner(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'        => self::TEST_EMAIL,
                'givenName'    => 'John',
                'surname'      => 'Doe',
                'sub'          => '456',
                'houseNumber'  => '',
                'localityName' => '',
                'postalCode'   => '',
                'street'       => '',
            ]);

        $this->keycloakUserData->fill($resourceOwner);

        $this->assertEquals(
            self::TEST_EMAIL,
            $this->keycloakUserData->getEmailAddress()
        );
        $this->assertEquals('John', $this->keycloakUserData->getFirstName());
        $this->assertEquals('Doe', $this->keycloakUserData->getLastName());
        $this->assertEquals('', $this->keycloakUserData->getOrganisationId());
        $this->assertEquals('', $this->keycloakUserData->getOrganisationName());
        $this->assertEquals('456', $this->keycloakUserData->getUserId());
        $this->assertEquals(self::TEST_EMAIL, $this->keycloakUserData->getUserName());
    }

    public function testCheckMandatoryValuesExistThrowsExceptionWhenValuesAreMissing(): void
    {
        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('toArray')
            ->willReturn([
                'email'     => '',
                'givenName' => '',
                'surname'   => '',
                'sub'       => '',
            ]);

        $this->expectException(AuthenticationCredentialsNotFoundException::class);

        $this->keycloakUserData->fill($resourceOwner);
    }
}
