<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Entity;

use DateTime;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\InstitutionTagCategoryFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\InstitutionTagFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTagCategory;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionProperty;
use Tests\Base\FunctionalTestCase;

class InstitutionTagCategoryTagOrderTest extends FunctionalTestCase
{
    public function testTagsAreLoadedInCreationOrder(): void
    {
        // Arrange: create the tags in a different order than their creation dates
        $category = InstitutionTagCategoryFactory::createOne();
        $categoryId = $category->getId();
        $this->createTag($category, 'third', new DateTime('2024-01-03 10:00:00'));
        $this->createTag($category, 'first', new DateTime('2024-01-01 10:00:00'));
        $this->createTag($category, 'second', new DateTime('2024-01-02 10:00:00'));

        // Act: reload the category from the database
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->find(InstitutionTagCategory::class, $categoryId);
        $labels = array_map(
            static fn ($tag): string => $tag->getLabel(),
            $this->getTags($reloaded)
        );

        // Assert
        self::assertSame(['first', 'second', 'third'], $labels);
    }

    public function testTagsWithSameCreationDateAreOrderedByLabel(): void
    {
        // Arrange
        $category = InstitutionTagCategoryFactory::createOne();
        $categoryId = $category->getId();
        $sameDate = new DateTime('2024-01-01 10:00:00');
        foreach (['b', 'c', 'a'] as $label) {
            $this->createTag($category, $label, $sameDate);
        }

        // Act
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->find(InstitutionTagCategory::class, $categoryId);
        $labels = array_map(
            static fn ($tag): string => $tag->getLabel(),
            $this->getTags($reloaded)
        );

        // Assert
        self::assertSame(['a', 'b', 'c'], $labels);
    }

    /**
     * InstitutionTag has no creationDate setter, so the date is written directly to the database.
     */
    private function createTag(object $category, string $label, DateTime $creationDate): void
    {
        $tag = InstitutionTagFactory::createOne(['label' => $label, 'category' => $category]);
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->createQuery(
            'UPDATE '.InstitutionTag::class.' t SET t.creationDate = :date WHERE t.id = :id'
        )
            ->setParameter('date', $creationDate)
            ->setParameter('id', $tag->getId())
            ->execute();
    }

    private function getTags(InstitutionTagCategory $category): array
    {
        $property = new ReflectionProperty(InstitutionTagCategory::class, 'tags');

        return $property->getValue($category)->toArray();
    }
}
