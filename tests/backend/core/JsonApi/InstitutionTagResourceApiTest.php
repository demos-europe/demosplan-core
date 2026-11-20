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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\InstitutionTagFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

class InstitutionTagResourceApiTest extends AbstractApiTest
{
    private const ROUTE = '/api/3.0/InstitutionTag';

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

    public function testGetReturnsCreationDateAndIsUsedFalseForUnassignedTag(): void
    {
        // Arrange
        $creationDate = new DateTime('2024-01-02 10:00:00');
        $tag = $this->createTag('unassigned', $creationDate);
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        // Act
        $response = $this->sendRequest(self::ROUTE.'/'.$tag->getId(), 'GET', $this->user, null);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $attributes = Json::decodeToArray($response->getContent())['data']['attributes'];
        self::assertSame($creationDate->format(DATE_ATOM), $attributes['creationDate']);
        self::assertFalse($attributes['isUsed']);
    }

    public function testGetReturnsIsUsedTrueForTagAssignedToAnInstitution(): void
    {
        // Arrange
        $tag = $this->createTag('assigned', new DateTime('2024-01-02 10:00:00'));
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $institution = $entityManager->find(Orga::class, $this->getReference(LoadUserData::TEST_ORGA_PUBLIC_AGENCY)->getId());
        $institution->addAssignedTag($entityManager->find(InstitutionTag::class, $tag->getId()));
        $entityManager->flush();
        // A real request starts with an empty identity map, so the relations are loaded from the database.
        $entityManager->clear();
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        // Act
        $response = $this->sendRequest(self::ROUTE.'/'.$tag->getId(), 'GET', $this->user, null);

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertTrue(Json::decodeToArray($response->getContent())['data']['attributes']['isUsed']);
    }

    public function testGetRestrictsAttributesToTheRequestedFields(): void
    {
        // Arrange
        $tag = $this->createTag('sparse', new DateTime('2024-01-02 10:00:00'));
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        // Act
        $response = $this->sendRequest(
            self::ROUTE.'/'.$tag->getId().'?fields[InstitutionTag]=name,creationDate,isUsed',
            'GET',
            $this->user,
            null
        );

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            ['name', 'creationDate', 'isUsed'],
            array_keys(Json::decodeToArray($response->getContent())['data']['attributes'])
        );
    }

    public function testListReturnsTagsOldestFirst(): void
    {
        // Arrange: created in a different order than their creation dates
        $newest = $this->createTag('newest', new DateTime('2024-01-03 10:00:00'));
        $oldest = $this->createTag('oldest', new DateTime('2024-01-01 10:00:00'));
        $middle = $this->createTag('middle', new DateTime('2024-01-02 10:00:00'));
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

    public function testListOrdersTagsWithTheSameCreationDateByLabel(): void
    {
        // Arrange
        $sameDate = new DateTime('2024-01-01 10:00:00');
        $b = $this->createTag('b', $sameDate);
        $c = $this->createTag('c', $sameDate);
        $a = $this->createTag('a', $sameDate);
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

    public function testListReturnsOnlyTagsOfTheCurrentCustomer(): void
    {
        // Arrange
        $own = $this->createTag('own', new DateTime('2024-01-01 10:00:00'));
        $foreign = InstitutionTagFactory::createOne([
            'category' => InstitutionTagCategoryFactory::new([
                'customer' => CustomerFactory::new(['subdomain' => 'other-customer-'.uniqid()]),
            ]),
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
     * The request the frontend sends: the category is included, with sparse fieldsets keyed by
     * relationship path for the included resource.
     */
    public function testListIncludesTheCategoryWithSparseFieldsets(): void
    {
        // Arrange
        $tag = $this->createTag('tag', new DateTime('2024-01-01 10:00:00'));
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        // Act
        $response = $this->sendRequest(
            self::ROUTE.'?include=category&fields[InstitutionTag]=name,category&fields[category]=name',
            'GET',
            $this->user,
            null
        );

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = Json::decodeToArray($response->getContent());
        $row = current(array_filter($content['data'], static fn (array $d): bool => $d['id'] === $tag->getId()));
        self::assertSame(['name'], array_keys($row['attributes']));
        self::assertSame($tag->getCategory()->getId(), $row['relationships']['category']['data']['id']);
        $included = array_column($content['included'], 'attributes', 'id');
        self::assertSame(['name' => $tag->getCategory()->getName()], $included[$tag->getCategory()->getId()]);
    }

    /**
     * The add-institution page lists the tags without `include` and groups them by the id of their category.
     */
    public function testListReturnsTheCategoryIdOfTheTagsWithoutInclude(): void
    {
        // Arrange
        $tag = $this->createTag('tag', new DateTime('2024-01-01 10:00:00'));
        $this->enablePermissions(['feature_institution_tag_read']);
        $this->loginForApiPlatform();

        // Act
        $response = $this->sendRequest(
            self::ROUTE.'?fields[InstitutionTag]=name,creationDate,category',
            'GET',
            $this->user,
            null
        );

        // Assert
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = Json::decodeToArray($response->getContent());
        $row = current(array_filter($content['data'], static fn (array $d): bool => $d['id'] === $tag->getId()));
        self::assertSame($tag->getCategory()->getId(), $row['relationships']['category']['data']['id']);
        self::assertSame('tag', $row['attributes']['name']);
        self::assertEmpty($content['included'] ?? []);
    }

    public function testListIsDeniedWithoutTagPermission(): void
    {
        $this->loginForApiPlatform();

        $response = $this->sendRequest(self::ROUTE, 'GET', $this->user, null);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    private function createTag(string $label, DateTime $creationDate): InstitutionTag
    {
        $tag = InstitutionTagFactory::createOne([
            'label'    => $label,
            'category' => InstitutionTagCategoryFactory::new(['customer' => $this->currentCustomer]),
        ])->_real();
        // The entity has no creationDate setter, so the date is written directly to the database.
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->createQuery('UPDATE '.InstitutionTag::class.' t SET t.creationDate = :date WHERE t.id = :id')
            ->setParameter('date', $creationDate)
            ->setParameter('id', $tag->getId())
            ->execute();
        // A real request starts with an empty identity map, so the tag is loaded from the database with this date.
        $entityManager->clear();

        return $tag;
    }
}
