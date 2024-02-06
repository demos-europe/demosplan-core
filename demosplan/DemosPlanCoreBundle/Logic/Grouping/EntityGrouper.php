<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Grouping;

use DemosEurope\DemosplanAddon\Contracts\Entities\EntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\ArraySorterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

use function array_shift;
use function count;
use function is_countable;
use function key;

/**
 * @template T of \demosplan\DemosPlanCoreBundle\Entity\CoreEntity
 */
abstract class EntityGrouper
{
    final public const MISSING_GROUP_KEY = 'missing';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * @param array<int|string,T> $entities
     * @param string[]            $groupingFields
     * @param string[][]          $stopGroupingForKeys
     *
     * @return EntityGroupInterface<T>
     */
    public function createGroupStructureFromEntities(array $entities, array $groupingFields, array $stopGroupingForKeys = []): EntityGroupInterface
    {
        $group = $this->createEntityGroupInstance();

        $this->fillEntitiesIntoGroupStructure(
            $entities,
            $groupingFields,
            $group,
            $stopGroupingForKeys
        );

        return $group;
    }

    /**
     * @param EntityGroupInterface<T> $group
     * @param ArraySorterInterface<T> $sorter
     */
    public function sortEntriesAtAllLayers(EntityGroupInterface $group, ArraySorterInterface $sorter): void
    {
        $unsortedEntries = $group->getEntries();
        $sortedEntries = $sorter->sortArray($unsortedEntries);
        $group->setEntries($sortedEntries);
        foreach ($group->getSubgroups() as $subgroup) {
            $this->sortEntriesAtAllLayers($subgroup, $sorter);
        }
    }

    /**
     * @param EntityGroupInterface<T>                       $group
     * @param ArraySorterInterface<EntityGroupInterface<T>> $sorter
     */
    public function sortSubgroupsAtAllLayers(EntityGroupInterface $group, ArraySorterInterface $sorter): void
    {
        $unsortedSubgroups = $group->getSubgroups();
        $sortedSubgroups = $sorter->sortArray($unsortedSubgroups);
        $group->setSubgroups($sortedSubgroups);
        foreach ($sortedSubgroups as $subgroup) {
            $this->sortSubgroupsAtAllLayers($subgroup, $sorter);
        }
    }

    /**
     * @param EntityGroupInterface<T>                       $group
     * @param ArraySorterInterface<EntityGroupInterface<T>> $sorter
     * @param int<0, max>                                   $depth  `0` denotes the immediate subgroups of the given group
     */
    public function sortSubgroupsAtDepth(EntityGroupInterface $group, ArraySorterInterface $sorter, int $depth): void
    {
        Assert::greaterThanEq($depth, 0);

        if (0 < $depth) {
            foreach ($group->getSubgroups() as $subgroup) {
                $this->sortSubgroupsAtDepth($subgroup, $sorter, $depth - 1);
            }
        } else {
            $unsortedSubgroups = $group->getSubgroups();
            $sortedSubgroups = $sorter->sortArray($unsortedSubgroups);
            $group->setSubgroups($sortedSubgroups);
        }
    }

    /**
     * Sort the given groups and their subgroups by the given {@link ArraySorterInterface}s.
     *
     * @param array<int|string,EntityGroupInterface<T>>                            $entityGroups The array of groups to be sorted. The given
     *                                                                                           array will be returned in the order applied by
     *                                                                                           the first sorter given in the sorter array. The
     *                                                                                           contained subgroups will be sorted as well
     *                                                                                           according to the remaining sorters given in the
     *                                                                                           sorter array.
     * @param array<int|string,ArraySorterInterface<EntityGroupInterface<T>>|null> $sorters      The sorters to use for sorting. Each element is
     *                                                                                           used for a specific layer. So the first sorter
     *                                                                                           is applied to the given array of
     *                                                                                           {@link EntityGroupInterface}s. The second sorter is
     *                                                                                           applied to the subgroups of the
     *                                                                                           {@link EntityGroupInterface}s given. The third sorter is
     *                                                                                           applied to the subgroups of the subgroups and
     *                                                                                           so on. If an element in the array is null the
     *                                                                                           sorting will be skipped for that layer. If
     *                                                                                           there are less sorters than layers the
     *                                                                                           remaining layers will be skipped as well.
     *
     * @return array<int|string,EntityGroupInterface<T>>
     */
    public function sortGroups(array $entityGroups, array $sorters): array
    {
        // if no sorters were given (or are left), then return the unsorted array
        if (0 === count($sorters)) {
            return $entityGroups;
        }
        // if there is a non-null sorter, use it on the current layer
        $sorter = array_shift($sorters);
        if (null !== $sorter) {
            $entityGroups = $sorter->sortArray($entityGroups);
        }
        // (try to) sort the subgroups as well
        foreach ($entityGroups as $entityGroup) {
            $unsortedSubgroups = $entityGroup->getSubgroups();
            $sortedSubgroups = $this->sortGroups($unsortedSubgroups, $sorters);
            $entityGroup->setSubgroups($sortedSubgroups);
        }

        // return the (potentially) sorted groups
        return $entityGroups;
    }

    /**
     * Iterates through the given entities and places each into the given {@link EntityGroupInterface}
     * depending on criteria given in $groupingFields. For more details see {@link fillEntityIntoGroupStructure}.
     *
     * @param CoreEntity[]            $entities
     * @param string[]                $groupingFields
     * @param string[][]              $stopGroupingForKeys
     * @param EntityGroupInterface<T> $group
     */
    public function fillEntitiesIntoGroupStructure(array $entities, array $groupingFields, EntityGroupInterface $group, array $stopGroupingForKeys = []): void
    {
        foreach ($entities as $entityKey => $entity) {
            $this->fillEntityIntoGroupStructure(
                $group,
                $entities[$entityKey],
                $groupingFields,
                $stopGroupingForKeys
            );
        }
    }

    /**
     * Places a single entity inside the given {@link EntityGroupInterface}.
     *
     * @param EntityGroupInterface<T> $group               The group the given entity is placed in. The entity may be placed directly in the group or in one of its subgroups (at any depth).
     * @param T                       $entity              the entity to be placed inside the given {@link EntityGroupInterface}
     * @param string[]                $entityFieldsToUse   Controls the resulting group structure.
     *                                                     Each element in this array represents a layer of the resulting tree.
     *                                                     For example if the array is empty the given entity will be placed
     *                                                     directly as entry in the given {@link EntityGroupInterface}. If there is one element
     *                                                     in the array the given {@link EntityGroupInterface} will contain one or multiple subgroups
     *                                                     which itself however will not contain additional subgroups.
     *                                                     The key of an element determines the field of the entity to use to determine the target
     *                                                     group. For example if the element has the key 'getTagId' there must be a getter in the
     *                                                     entity with the name 'getTagId'. From the value of that entity field (eg. '3') a group is created
     *                                                     (if it does not already exist) and the entity is placed as entry in that group.
     *                                                     If the field exists but its value is null the internal key {@link MISSING_GROUP_KEY} will be used instead.
     *                                                     The value of the element in the entityFieldsToUse array will be used
     *                                                     similarly to retrieve a value from the entity. However that value will be used
     *                                                     as the title of the group instead of its ID. For example if the value of the element
     *                                                     is 'getTagName' there must be a getter in the entity with the name 'getTagName'. The value of that
     *                                                     entity field (eg. 'Work in Progress') will be used as the name of the group if it does not already
     *                                                     exist. That means the first entity added to a group will determine its title.
     *                                                     If the value of the field is null the value of the translation key 'filter.noAssignment' will be used instead.
     * @param string[][]              $stopGroupingForKeys Use this to stop the grouping for an entity early and
     *                                                     not dive into subgroups. The keys in this array define the key used to read a value from
     *                                                     an entity. Each value in the array for each key defines a key for which a group will be created,
     *                                                     however that group will not be further divided into subgroups, even if defined by $entityFieldsToUse.
     *
     * @return int number of times the given entity was added to a group
     */
    protected function fillEntityIntoGroupStructure(
        EntityGroupInterface $group,
        CoreEntity&EntityInterface $entity,
        array $entityFieldsToUse,
        array $stopGroupingForKeys = []
    ): int
    {
        if (0 === count($entityFieldsToUse)) {
            // if we do not have any fields to use as keys from the entity
            // then we just add the entity to the given array
            $group->setEntry($entity->getId(), $entity);

            return 1;
        }

        $missingTitle = $this->translator->trans('filter.noAssignment');
        $entityKey = array_key_first($entityFieldsToUse);
        $groupTitleKey = array_shift($entityFieldsToUse);
        // this entity value will be used to get the grouping key(s)
        $entityValue = $entity->$entityKey();

        $nonDividableGroupKeys = $stopGroupingForKeys[$entityKey] ?? [];

        // use the entity value to group the entity in the $groupStructure
        // if it is an iterable then group the entity in the groupStructure once for every element
        if (is_iterable($entityValue) && is_countable($entityValue)) {
            $totalCount = 0;
            if (0 === count($entityValue)) {
                $groupKey = self::MISSING_GROUP_KEY;
                $stopDividingSubgroup = in_array($groupKey, $nonDividableGroupKeys, true);
                $subgroup = $group->getSubgroup($groupKey);
                if (null === $subgroup) {
                    $subgroup = $this->createEntityGroupInstance($missingTitle);
                    $group->setSubgroup($groupKey, $subgroup);
                }
                $totalCount += $this->fillEntityIntoGroupStructure(
                    $subgroup,
                    $entity,
                    $stopDividingSubgroup ? [] : $entityFieldsToUse,
                    $stopGroupingForKeys
                );
            }
            foreach ($entityValue as $v) {
                // TODO: implementation not finished and currently fostered for specific use cases
                if ($v instanceof CoreEntity) {
                    $groupKey = $v->getId();
                    $stopDividingSubgroup = in_array($groupKey, $nonDividableGroupKeys, true);
                    $subgroup = $group->getSubgroup($groupKey);
                    if (null === $subgroup) {
                        $groupTitle = $v->getTitle();
                        $subgroup = $this->createEntityGroupInstance($groupTitle);
                        $group->setSubgroup($groupKey, $subgroup);
                    }
                    $totalCount += $this->fillEntityIntoGroupStructure(
                        $subgroup,
                        $entity,
                        $stopDividingSubgroup ? [] : $entityFieldsToUse,
                        $stopGroupingForKeys
                    );
                } else { // single value like string
                    throw new NotYetImplementedException('an array of non-array values is not supported yet');
                }
            }

            return $totalCount;
        }

        $groupKey = null === $entityValue || '' === $entityValue ? self::MISSING_GROUP_KEY : (string) $entityValue;
        $stopDividingSubgroup = in_array($groupKey, $nonDividableGroupKeys, true);
        $subgroup = $group->getSubgroup($groupKey);
        if (null === $subgroup) {
            $groupTitle = self::MISSING_GROUP_KEY === $groupKey ? $missingTitle : (string) $entity->$groupTitleKey();
            $subgroup = $this->createEntityGroupInstance($groupTitle);
            $group->setSubgroup($groupKey, $subgroup);
        }

        return $this->fillEntityIntoGroupStructure(
            $subgroup,
            $entity,
            $stopDividingSubgroup ? [] : $entityFieldsToUse,
            $stopGroupingForKeys
        );
    }

    /**
     * @return EntityGroupInterface<T>
     */
    abstract protected function createEntityGroupInstance(string $title = ''): EntityGroupInterface;
}
