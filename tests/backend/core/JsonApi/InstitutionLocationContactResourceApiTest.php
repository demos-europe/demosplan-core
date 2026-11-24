<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi;

use demosplan\DemosPlanCoreBundle\Api\InstitutionLocationContact\Resource;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Address;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

class InstitutionLocationContactResourceApiTest extends AbstractApiTest
{
    private ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
    }

    protected function getAdditionalHeaders(string $jwtToken, ?Procedure $procedure): array
    {
        return [];
    }

    protected function getServerParameters(): array
    {
        return ['HTTP_ACCEPT' => 'application/vnd.api+json'];
    }

    /**
     * Same as the legacy resource type, which does not allow get: the contact information is only
     * readable as relationship of another resource.
     */
    public function testDirectRequestIsNotFound(): void
    {
        // Arrange
        $address = $this->createAddress();
        $this->enablePermissions(['field_organisation_phone', 'feature_institution_tag_read']);
        $this->client->loginUser($this->user, 'main');

        // Act
        $response = $this->sendRequest('/api/3.0/InstitutionLocationContact/'.$address->getId(), 'GET', $this->user, null);

        // Assert
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testFromEntityMapsAddressWithPhone(): void
    {
        // Arrange
        $address = $this->createAddress();

        // Act
        $resource = Resource::fromEntity($address, true);

        // Assert
        self::assertSame($address->getId(), $resource->id);
        self::assertSame('Teststrasse 1', $resource->street);
        self::assertSame('20095', $resource->postalcode);
        self::assertSame('Hamburg', $resource->city);
        self::assertSame('040 123456', $resource->phone);
    }

    public function testFromEntityLeavesPhoneEmptyWithoutPermission(): void
    {
        // Arrange
        $address = $this->createAddress();

        // Act
        $resource = Resource::fromEntity($address, false);

        // Assert
        self::assertSame('Teststrasse 1', $resource->street);
        self::assertNull($resource->phone);
    }

    public function testFromEntityMapsMissingAddressPartsToEmptyStrings(): void
    {
        // Arrange
        $address = new Address();
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($address);
        $entityManager->flush();

        // Act
        $resource = Resource::fromEntity($address, true);

        // Assert
        self::assertSame('', $resource->street);
        self::assertSame('', $resource->postalcode);
        self::assertSame('', $resource->city);
        self::assertNull($resource->phone);
    }

    private function createAddress(): Address
    {
        $address = new Address();
        $address->setStreet('Teststrasse 1');
        $address->setPostalcode('20095');
        $address->setCity('Hamburg');
        $address->setPhone('040 123456');
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($address);
        $entityManager->flush();

        return $address;
    }
}
