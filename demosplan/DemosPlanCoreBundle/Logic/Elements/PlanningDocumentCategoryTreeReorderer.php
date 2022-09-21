<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Elements;

use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PlanningDocumentCategoryResourceType;
use demosplan\DemosPlanCoreBundle\ValueObject\CategoryReorderingData;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Contracts\FunctionInterface;
use InvalidArgumentException;

class PlanningDocumentCategoryTreeReorderer
{
    /**
     * @var EntityFetcher
     */
    private $entityFetcher;

    /**
     * @var PlanningDocumentCategoryResourceType
     */
    private $categoryResourceType;

    /**
     * @var DqlConditionFactory
     */
    private $conditionFactory;

    /**
     * @var SortMethodFactory
     */
    private $sortMethodFactory;

    public function __construct(
        DqlConditionFactory $conditionFactory,
        EntityFetcher $entityFetcher,
        PlanningDocumentCategoryResourceType $categoryResourceType,
        SortMethodFactory $sortMethodFactory
    ) {
        $this->entityFetcher = $entityFetcher;
        $this->categoryResourceType = $categoryResourceType;
        $this->conditionFactory = $conditionFactory;
        $this->sortMethodFactory = $sortMethodFactory;
    }

    // @improve T26005
    /**
     * @template T
     *
     * @param T                  $target
     * @param Collection<int, T> $list
     * @param bool               $updateIndices if set to true then an item (potentially) existing
     *                                          at the target index will be moved to the following
     *                                          index; if at that index another item is set that
     *                                          one will be moved too and so on
     */
    protected function insertAt(
        Collection $list,
        $target,
        int $targetIndex,
        bool $updateIndices
    ): void {
        if (!$updateIndices || !$list->containsKey($targetIndex)) {
            $newList = $list->toArray();
        } else {
            // make room for added item
            $newList = [];
            $previousIndex = -1;
            $stopIncrementing = false;
            foreach ($list as $index => $item) {
                $atTarget = $index === $targetIndex;
                $afterTarget = $index > $targetIndex;
                $atLeastOneIndexSkipped = $previousIndex + 1 < $index;

                $newIndex = $index;
                if (!$atTarget && $afterTarget && $atLeastOneIndexSkipped) {
                    // usable hole detected
                    $stopIncrementing = true;
                }

                if (($atTarget || $afterTarget) && !$stopIncrementing) {
                    $newIndex = $index + 1;
                }

                $newList[$newIndex] = $item;
                $previousIndex = $index;
            }
        }

        // set item at the right place
        $newList[$targetIndex] = $target;

        $list->clear();
        foreach ($newList as $index => $item) {
            $list->set($index, $item);
        }
    }

    /**
     * Changes the entities but does not flush anything to the database.
     */
    public function updateEntities(CategoryReorderingData $reorderingData): void
    {
        $this->updateOldNeighbors($reorderingData);
        $this->updateNewNeighbors($reorderingData);
    }

    /**
     * Fetches the {@link Elements} entities needed to apply the RPC action.
     *
     * This implementation will only allow access to target and parent categories that are available
     * to the current user by utilizing the {@link PlanningDocumentCategoryResourceType} and that
     * are set in the given procedure. Neighbors are **not** restricted based on the user because
     * they all need to be updated independent of their visibility.
     *
     * Target and (optionally) parent are fetched in the same request.
     */
    public function getReorderingData(
        string $idOfCategoryToMove,
        ?string $newParentId,
        ?int $newIndex,
        string $procedureId
    ): CategoryReorderingData {
        $categoryToMoveAndNewParentIds = [$idOfCategoryToMove];
        if (null !== $newParentId) {
            $categoryToMoveAndNewParentIds[] = $newParentId;
        }

        $categoryToMoveAndNewParent = $this->entityFetcher->listEntities(
            $this->categoryResourceType,
            [
                $this->getProcedureCondition($procedureId),
                $this->conditionFactory->propertyHasAnyOfValues(
                    $categoryToMoveAndNewParentIds,
                    ...$this->categoryResourceType->id
                ),
            ]
        );

        $categoryToMoveAndNewParent = array_column(
            array_map(static function (Elements $category): array {
                return [$category->getId(), $category];
            }, $categoryToMoveAndNewParent),
            1,
            0
        );

        /** @var Elements $categoryToMove */
        $categoryToMove = $categoryToMoveAndNewParent[$idOfCategoryToMove] ?? null;
        if (null === $categoryToMove) {
            throw new InvalidArgumentException("No category found for target ID '$idOfCategoryToMove'.");
        }

        /** @var Elements|null $newParent */
        $newParent = $categoryToMoveAndNewParent[$newParentId] ?? null;
        if (null !== $newParentId && null === $newParent) {
            throw new InvalidArgumentException("No category found for parent ID '$newParentId'.");
        }

        $newNeighbors = $this->getNeighbors($newParent, $procedureId);
        $previousParent = $categoryToMove->getParent();
        $previousNeighbors = $this->getNeighbors($previousParent, $procedureId);

        if (null === $newIndex) {
            $lastItem = $newNeighbors->last();
            $newIndex = false !== $lastItem
                ? $lastItem->getOrder() + 1
                : 0;
        }

        return new CategoryReorderingData(
            $categoryToMove,
            $newParent,
            $newNeighbors,
            $previousParent,
            $previousNeighbors,
            $newIndex
        );
    }

    /**
     * Returns `true` if an update is necessary or `false` if the hierarchy was the
     * correct one already.
     */
    public function isChangeNecessary(CategoryReorderingData $reorderingData): bool
    {
        $moveTarget = $reorderingData->getMoveTarget();
        $newParent = $reorderingData->getNewParent();
        $previousParent = $moveTarget->getParent();

        // if both parents are null (root layer) we don't need to update the hierarchy
        $bothParentsNull = $newParent === $previousParent && null === $previousParent;
        // if both parents have the same ID we don't need to update the hierarchy either
        $bothParentsSameId =
            null !== $newParent
            && null !== $previousParent
            && $newParent->getId() === $previousParent->getId();

        $hierarchyChanged = !$bothParentsNull && !$bothParentsSameId;
        $orderChanged = $reorderingData->getPreviousIndex() !== $reorderingData->getNewIndex();

        return $hierarchyChanged || $orderChanged;
    }

    /**
     * @return Collection<int, Elements> sorted by {@link Elements::$order} which is also used as
     *                                   key
     */
    private function getNeighbors(?Elements $parent, string $procedureId): Collection
    {
        $sortMethods = [
            $this->sortMethodFactory->propertyAscending(
                ...$this->categoryResourceType->order
            ),
        ];

        if (null !== $parent) {
            $neighbors = $this->entityFetcher->listPrefilteredEntitiesUnrestricted(
                $parent->getChildren()->toArray(),
                [],
                $sortMethods
            );
        } else {
            $rootCondition = [
                $this->getProcedureCondition($procedureId),
                $this->conditionFactory->propertyIsNull(...$this->categoryResourceType->parent),
            ];
            $neighbors = $this->entityFetcher->listEntitiesUnrestricted(
                $this->categoryResourceType->getEntityClass(),
                $rootCondition,
                $sortMethods
            );
        }

        $neighbors = collect($neighbors)->mapWithKeys(static function (Elements $neighbor): array {
            return [$neighbor->getOrder() => $neighbor];
        })->all();

        return new ArrayCollection($neighbors);
    }

    /**
     * @param string $procedureId
     *
     * @return FunctionInterface<bool>
     */
    private function getProcedureCondition(string $procedureId): FunctionInterface
    {
        return $this->conditionFactory->propertyHasValue(
            $procedureId,
            ...$this->categoryResourceType->procedure->id
        );
    }

    /**
     * @param Collection<int, Elements> $collection
     */
    private function reIndexCollection(Collection $collection): void
    {
        if (0 === $collection->count()) {
            return;
        }

        // We want to change the collection itself, hence we first get a copy of its content to
        // work on.
        $tmpList = $collection->toArray();
        // We can expect the items in the input collection to correspond to the correct index key.
        // However, it may still be unsorted, i.e. `[4 => ..., 7 => ..., 1 => ...]`, hence we sort
        // it first to get `[1 => ..., 4 => ..., 7 => ...]`.
        ksort($tmpList);
        // Clearing the list to refill it afterwards
        $collection->clear();
        // We want a clean index, so we drop the old one to get `[0 => ..., 1 => ..., 2 => ...]`.
        $tmpList = array_values($tmpList);
        // Now we can use the clean list to fill the collection and update the category order
        foreach ($tmpList as $index => $category) {
            $collection->set($index, $category);
            $category->setOrder($index);
        }
    }

    /**
     * Remove target from old neighbors and re-index them. Also update the old parent.
     */
    private function updateOldNeighbors(CategoryReorderingData $reorderingData): void
    {
        $previousNeighbors = $reorderingData->getPreviousNeighbors();
        $previousNeighbors->removeElement($reorderingData->getMoveTarget());
        $this->reIndexCollection($previousNeighbors);
        $previousParent = $reorderingData->getPreviousParent();
        if (null !== $previousParent) {
            $previousParent->setChildren($previousNeighbors);
        }
    }

    private function updateNewNeighbors(CategoryReorderingData $reorderingData): void
    {
        $target = $reorderingData->getMoveTarget();
        $newNeighbors = $reorderingData->getNewNeighbors();
        // in case the parent category didn't change we remove the category-to-be-moved from its old index
        $newNeighbors->removeElement($target);

        // insert category-to-be-moved in list and move conflicting and following items accordingly
        $this->insertAt(
            $newNeighbors,
            $target,
            $reorderingData->getNewIndex(),
            true
        );

        // we assume that no duplicates are in the list, otherwise this won't work reliably
        $this->reIndexCollection($newNeighbors);
        $newParent = $reorderingData->getNewParent();
        $target->setParent($newParent);
        if (null !== $newParent) {
            $newParent->setChildren($newNeighbors);
        }
    }
}
