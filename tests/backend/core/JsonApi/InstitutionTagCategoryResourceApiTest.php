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

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\InstitutionTagCategoryFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTagCategory;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

class InstitutionTagCategoryResourceApiTest extends AbstractApiTest
{
    private const ROUTE = '/api/3.0/InstitutionTagCategory';

    private ?User $user = null;
    private ?Customer $currentCustomer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $institution = $this->getReference(LoadUserData::TEST_ORGA_PUBLIC_AGENCY);
        $this->currentCustomer = $institution->getStatusInCustomers()->first()->getCustomer();
        $this->getContainer()->get(GlobalConfigInterface::class)->setSubdomain($this->currentCustomer->getSubdomain());
    }

    /**
     * /api/3.0/* routes sit behind the session based `main` firewall, see TagResourceApiTest.
     */
    private function loginForApiPlatform(): void
    {
        $this->client->loginUser($this->user, 'main');
    }

    protected function getAdditionalHeaders(string $jwtToken, ?Procedure $procedure): array
    {
        return [];
    }

    protected function getServerParameters(): array
    {
        return ['HTTP_ACCEPT' => 'application/vnd.api+json'];
    }

    public function testListReturnsCategoriesOldestFirst(): void
    {
        // Arrange: created in a different order than their creation dates
        $newest = $this->createCategory('newest', new DateTime('2024-01-03 10:00:00'));
        $oldest = $this->createCategory('oldest', new DateTime('2024-01-01 10:00:00'));
        $middle = $this->createCategory('middle', new DateTime('2024-01-02 10:00:00'));
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, null);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $ids = array_column(Json::decodeToArray($response->getContent())['data'], 'id');
        $created = [$oldest->getId(), $middle->getId(), $newest->getId()];
        self::assertSame($created, array_values(array_intersect($ids, $created)));
    }

    public function testListOrdersCategoriesWithTheSameCreationDateByName(): void
    {
        // Arrange
        $sameDate = new DateTime('2024-01-01 10:00:00');
        $b = $this->createCategory('b', $sameDate);
        $c = $this->createCategory('c', $sameDate);
        $a = $this->createCategory('a', $sameDate);
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, null);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $ids = array_column(Json::decodeToArray($response->getContent())['data'], 'id');
        $expected = [$a->getId(), $b->getId(), $c->getId()];
        self::assertSame($expected, array_values(array_intersect($ids, $expected)));
    }

    public function testListReturnsOnlyCategoriesOfTheCurrentCustomer(): void
    {
        // Arrange
        $own = $this->createCategory('own', new DateTime('2024-01-01 10:00:00'));
        $foreign = InstitutionTagCategoryFactory::createOne([
            'customer' => CustomerFactory::new(['subdomain' => 'other-customer-'.uniqid()]),
        ])->_real();
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        // Act
        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, null);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $ids = array_column(Json::decodeToArray($response->getContent())['data'], 'id');
        self::assertContains($own->getId(), $ids);
        self::assertNotContains($foreign->getId(), $ids);
    }

    /**
     * The request the frontend sends: sparse fieldsets for the category.
     */
    public function testListReturnsNameAndCreationDateWithSparseFieldsets(): void
    {
        // Arrange
        $creationDate = new DateTime('2024-01-02 10:00:00');
        $category = $this->createCategory('category', $creationDate);
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        // Act
        $response = $this->sendRequest(
            self::ROUTE.'?fields[InstitutionTagCategory]=name,creationDate',
            'GET',
            $this->user,
            null
        );

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $rows = array_column(Json::decodeToArray($response->getContent())['data'], 'attributes', 'id');
        self::assertSame(
            ['name' => 'category', 'creationDate' => $creationDate->format(DATE_ATOM)],
            $rows[$category->getId()]
        );
    }

    public function testGetReturnsCreationDate(): void
    {
        // Arrange
        $creationDate = new DateTime('2024-01-02 10:00:00');
        $category = $this->createCategory('category', $creationDate);
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        // Act
        $response = $this->sendRequest(self::ROUTE.'/'.$category->getId(), 'GET', $this->user, null);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $attributes = Json::decodeToArray($response->getContent())['data']['attributes'];
        self::assertSame('category', $attributes['name']);
        self::assertSame($creationDate->format(DATE_ATOM), $attributes['creationDate']);
    }

    public function testListIsDeniedWithoutTagPermission(): void
    {
        $this->loginForApiPlatform();

        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, null);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    private function createCategory(string $name, DateTime $creationDate): InstitutionTagCategory
    {
        $category = InstitutionTagCategoryFactory::createOne([
            'name'     => $name,
            'customer' => $this->currentCustomer,
        ])->_real();
        // The entity has no creationDate setter, so the date is written directly to the database.
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->createQuery('UPDATE '.InstitutionTagCategory::class.' c SET c.creationDate = :date WHERE c.id = :id')
            ->setParameter('date', $creationDate)
            ->setParameter('id', $category->getId())
            ->execute();
        // A real request starts with an empty identity map, so the category is loaded from the database with this date.
        $entityManager->clear();

        return $category;
    }
}
