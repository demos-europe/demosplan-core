<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\SortableInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Doctrine\Common\Collections\Collection;

class ReorderEntityListByInteger
{
    /**
     * @var int
     */
    private $newIndex;

    /**
     * @var Collection<int, SortableInterface>
     */
    private $allAffectedEntities;

    /**
     * @var int
     */
    private $oldIndex;

    /**
     * @param Collection<int, SortableInterface> $allAffectedEntities
     */
    public function __construct(
        int $newIndex,
        string $entityId,
        Collection $allAffectedEntities
    ) {
        $this->newIndex = $newIndex;
        $this->allAffectedEntities = $allAffectedEntities;
        $this->oldIndex = $this->getMovedEntity($entityId)->getSortIndex();
    }

    public function reorderEntityList(): void
    {
        // to be able to not violate a unique constraint on the sortIndex - all entities have to get a new index.
        // This is because the constraint gets checked on every single step of the update process instead of once after
        // all entities are updated. (If you swap index 5 and 6 - 5 camt get updated because 6 is already in use...)
        // To circumvent this all entities shift their sortIndex either up or down (+count($entities) or - lowestIndexOfEntity)
        /** @var SortableInterface $entityWithLowestIndex */
        $entityWithLowestIndex = $this->allAffectedEntities->first();
        $lowestIndex = $entityWithLowestIndex->getSortIndex();
        // adjust the newIndex received via fe in case the indices are shifted within the db
        if ($lowestIndex > $this->newIndex) {
            $this->newIndex += $lowestIndex;
        }
        if ($this->newIndex === $this->oldIndex) {
            throw new InvalidArgumentException('The requested Place already has the desired index - there is nothing to change');
        }

        $filteredEntitiesBetweenNewAndOldIndex = $this->getEntitiesBetweenIndices(
            $this->newIndex,
            $this->oldIndex,
            $this->allAffectedEntities
        );
        $this->changeAffectedIndexOfEntityList($filteredEntitiesBetweenNewAndOldIndex);
        $this->adjustIndexForUniqueness($this->allAffectedEntities);
    }

    private function getMovedEntity(string $entityId): SortableInterface
    {
        return $this->allAffectedEntities->filter(
            static function (SortableInterface $entity) use ($entityId) {
                return $entity->getId() === $entityId;
            }
        )->first();
    }

    /**
     * @return Collection<int, SortableInterface> the Entities between the two indexes
     **/
    private function getEntitiesBetweenIndices(int $newIndex, int $oldIndex, Collection $entities): Collection
    {
        $lowIndex = $newIndex < $oldIndex ? $newIndex : $oldIndex;
        $highIndex = $newIndex < $oldIndex ? $oldIndex : $newIndex;

        return $entities->filter(
            static function (SortableInterface $entity) use ($lowIndex, $highIndex): bool {
                $entityIndex = $entity->getSortIndex();

                return $entityIndex >= $lowIndex
                    && $entityIndex <= $highIndex;
            }
        );
    }

    /**
     * having the application in mind - movedUp checks if you drag an item from the list upwards
     * example:.
     *
     * dog (index 0)                                       ⬆     spider (index 0)
     * cat (index 1)                                       ⬆     dog (index 1)
     * spider (index 2)     you drag the spider to the top ⬆     cat (index 2)
     *
     * the spider changed its index from 2 (oldIndex) to 0 (newIndex) --> ::moveUp() will return true
     */
    private function movedUp(): bool
    {
        return $this->newIndex < $this->oldIndex;
    }

    /**
     * @param Collection<int, SortableInterface> $entities
     */
    private function changeAffectedIndexOfEntityList(Collection $entities): void
    {
        $addToIndex = $this->movedUp() ? 1 : -1;
        $entities->forAll(function (int $key, SortableInterface $entity) use ($addToIndex): bool {
            $this->changeAffectedIndexOfEntity($entity, $addToIndex);

            return true;
        });
    }

    private function changeAffectedIndexOfEntity(SortableInterface $entity, int $addToIndex): void
    {
        if ($this->oldIndex === $entity->getSortIndex()) {
            $entity->setSortIndex($this->newIndex);

            return;
        }

        $entity->setSortIndex($entity->getSortIndex() + $addToIndex);
    }

    /**
     * @param Collection<int, SortableInterface> $entities
     */
    private function adjustIndexForUniqueness(Collection $entities): void
    {
        // the collection is not sorted anymore since the old index got updated with the new one
        // already and the indices between got adjusted as well.
        // resort them in order to get the lowest currently used index.

        $lowestIndex = collect($entities)
            ->map(static function (SortableInterface $entity): int {
                return $entity->getSortIndex();
            })
            ->sort(static function (int $indexA, int $indexB): int {
                return $indexA - $indexB;
            })
            ->first();

        $entitiesCount = $entities->count();
        // If there is enough space between zero and the lowest currently used sort index
        // then we can move all items down towards zero.
        $moveIndicesTowardZero = $lowestIndex > $entitiesCount - 1;
        // if we have space between 0 and the lowest place index, then we use a negative modifier,
        // to move the current place indexes down to zero
        $indexModifier = $moveIndicesTowardZero
            ? -$lowestIndex
            : $entitiesCount;

        $entities->forAll(
            static function (int $key, SortableInterface $entity) use ($indexModifier): bool {
                $currentIndex = $entity->getSortIndex();
                $entity->setSortIndex($currentIndex + $indexModifier);

                return true;
            }
        );
    }
}
