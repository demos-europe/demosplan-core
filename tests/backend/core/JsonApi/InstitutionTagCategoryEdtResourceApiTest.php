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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\InstitutionTagCategoryFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\InstitutionTagFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTagCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

/**
 * Covers the EDT based 2.0 API, which the page of the invited institutions and the tag management still use to read
 * the tag categories. The API Platform 3.0 resources are covered by {@link InstitutionTagCategoryResourceApiTest}.
 */
class InstitutionTagCategoryEdtResourceApiTest extends AbstractApiTest
{
    /**
     * The query of the page of the invited institutions.
     */
    private const ROUTE = '/api/2.0/InstitutionTagCategory'
        .'?fields[InstitutionTagCategory]=creationDate,name,tags'
        .'&fields[InstitutionTag]=creationDate,isUsed,name,category'
        .'&include=tags,tags.category';

    private ?Customer $currentCustomer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $institution = $this->getReference(LoadUserData::TEST_ORGA_PUBLIC_AGENCY);
        $this->currentCustomer = $institution->getStatusInCustomers()->first()->getCustomer();
        $this->getContainer()->get(GlobalConfigInterface::class)->setSubdomain($this->currentCustomer->getSubdomain());
    }

    public function testCategoriesAreListedOldestFirst(): void
    {
        // Arrange: created in a different order than their creation dates
        $newest = $this->createCategory('newest', new DateTime('2024-01-03 10:00:00'));
        $oldest = $this->createCategory('oldest', new DateTime('2024-01-01 10:00:00'));
        $middle = $this->createCategory('middle', new DateTime('2024-01-02 10:00:00'));

        // Act
        $content = $this->requestCategories();

        // Assert
        $ids = array_column($content['data'], 'id');
        $created = [$oldest->getId(), $middle->getId(), $newest->getId()];
        self::assertSame($created, array_values(array_intersect($ids, $created)));
    }

    public function testTagsOfACategoryAreListedOldestFirst(): void
    {
        // Arrange: created in a different order than their creation dates
        $category = $this->createCategory('category', new DateTime('2024-01-01 10:00:00'));
        $newest = $this->createTag($category, 'a', new DateTime('2024-01-03 10:00:00'));
        $oldest = $this->createTag($category, 'c', new DateTime('2024-01-01 10:00:00'));
        $middle = $this->createTag($category, 'b', new DateTime('2024-01-02 10:00:00'));

        // Act
        $content = $this->requestCategories();

        // Assert
        $row = current(array_filter($content['data'], static fn (array $d): bool => $d['id'] === $category->getId()));
        self::assertSame(
            [$oldest->getId(), $middle->getId(), $newest->getId()],
            array_column($row['relationships']['tags']['data'], 'id')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requestCategories(): array
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->enablePermissions(['feature_institution_tag_read']);

        $response = $this->sendRequest(self::ROUTE, 'GET', $user, null);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        return Json::decodeToArray($response->getContent());
    }

    private function createCategory(string $name, DateTime $creationDate): InstitutionTagCategory
    {
        $category = InstitutionTagCategoryFactory::createOne(['name' => $name, 'customer' => $this->currentCustomer])->_real();
        $this->writeCreationDate(InstitutionTagCategory::class, $category->getId(), $creationDate);

        return $category;
    }

    private function createTag(InstitutionTagCategory $category, string $label, DateTime $creationDate): InstitutionTag
    {
        $tag = InstitutionTagFactory::createOne(['label' => $label, 'category' => $category])->_real();
        $this->writeCreationDate(InstitutionTag::class, $tag->getId(), $creationDate);

        return $tag;
    }

    /**
     * The entities have no creationDate setter, so the date is written directly to the database. A real request starts
     * with an empty identity map, so the entities are loaded again from the database.
     */
    private function writeCreationDate(string $entityClass, string $id, DateTime $creationDate): void
    {
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->createQuery('UPDATE '.$entityClass.' e SET e.creationDate = :date WHERE e.id = :id')
            ->setParameter('date', $creationDate)
            ->setParameter('id', $id)
            ->execute();
        $entityManager->clear();
    }

    protected function getServerParameters(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ];
    }
}
