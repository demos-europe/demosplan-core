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
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\InstitutionTagCategoryFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTagCategory;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionTagCategoryResourceType;
use Doctrine\ORM\EntityManagerInterface;
use Tests\Base\FunctionalTestCase;

class InstitutionTagCategoryResourceTypeTest extends FunctionalTestCase
{
    protected ?InstitutionTagCategoryResourceType $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(InstitutionTagCategoryResourceType::class);

        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($user);
        $this->enablePermissions(['feature_institution_tag_read']);
    }

    public function testCategoriesAreSortedOldestFirst(): void
    {
        // Arrange: created in a different order than their creation dates
        $customer = $this->makeCustomerCurrent();
        $newest = $this->createCategory($customer, 'newest', new DateTime('2024-01-03 10:00:00'));
        $oldest = $this->createCategory($customer, 'oldest', new DateTime('2024-01-01 10:00:00'));
        $middle = $this->createCategory($customer, 'middle', new DateTime('2024-01-02 10:00:00'));

        // Act
        $categories = $this->sut->getEntities([], []);

        // Assert
        self::assertSame(
            [$oldest->getId(), $middle->getId(), $newest->getId()],
            array_map(static fn (InstitutionTagCategory $category): ?string => $category->getId(), $categories)
        );
    }

    public function testCategoriesWithTheSameCreationDateAreSortedByName(): void
    {
        // Arrange
        $customer = $this->makeCustomerCurrent();
        $sameDate = new DateTime('2024-01-01 10:00:00');
        $b = $this->createCategory($customer, 'b', $sameDate);
        $c = $this->createCategory($customer, 'c', $sameDate);
        $a = $this->createCategory($customer, 'a', $sameDate);

        // Act
        $categories = $this->sut->getEntities([], []);

        // Assert
        self::assertSame(
            [$a->getId(), $b->getId(), $c->getId()],
            array_map(static fn (InstitutionTagCategory $category): ?string => $category->getId(), $categories)
        );
    }

    private function makeCustomerCurrent(): object
    {
        $customer = CustomerFactory::createOne(['subdomain' => 'current-customer-'.uniqid()])->_real();
        $this->getContainer()->get(GlobalConfigInterface::class)->setSubdomain($customer->getSubdomain());

        return $customer;
    }

    private function createCategory(object $customer, string $name, DateTime $creationDate): InstitutionTagCategory
    {
        $category = InstitutionTagCategoryFactory::createOne(['name' => $name, 'customer' => $customer])->_real();
        // The entity has no creationDate setter, so the date is written directly to the database.
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->createQuery('UPDATE '.InstitutionTagCategory::class.' c SET c.creationDate = :date WHERE c.id = :id')
            ->setParameter('date', $creationDate)
            ->setParameter('id', $category->getId())
            ->execute();
        $entityManager->clear();

        return $category;
    }
}
