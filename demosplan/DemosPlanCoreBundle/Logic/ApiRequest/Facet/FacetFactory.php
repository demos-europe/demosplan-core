<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Facet;

use DemosEurope\DemosplanAddon\Contracts\ResourceType\JsonApiResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PrefilledResourceTypeProvider;
use demosplan\DemosPlanCoreBundle\ValueObject\Filters\AggregationFilterGroup;
use demosplan\DemosPlanCoreBundle\ValueObject\Filters\AggregationFilterItem;
use demosplan\DemosPlanCoreBundle\ValueObject\Filters\AggregationFilterType;
use Enqueue\Util\UUID;
use Symfony\Contracts\Translation\TranslatorInterface;
use Illuminate\Support\Collection;
use Webmozart\Assert\Assert;

use function collect;

class FacetFactory
{
    public function __construct(
        private readonly PrefilledResourceTypeProvider $resourceTypeProvider,
        private readonly TranslatorInterface $translator
    ) {
    }

    /**
     * @param array<string, FacetInterface<object>>           $facetDefinitions
     * @param array<string,array<int,array<string,mixed>>>    $aggregationBuckets
     * @param array<string,int>                               $missingResourcesSums
     * @param array<string,array<string,array<string,mixed>>> $rawFilter
     *
     * @return array<int,AggregationFilterType> return an empty array if no facets exist
     */
    public function getFacets(array $facetDefinitions, array $aggregationBuckets, array $missingResourcesSums, array $rawFilter): array
    {
        return collect($facetDefinitions)
            ->map(function (FacetInterface $facetDefinition, string $bucketKey) use ($aggregationBuckets, $missingResourcesSums, $rawFilter): AggregationFilterType {
                $targetBucket = $aggregationBuckets[$bucketKey] ?? [];
                $missingResourcesSum = $missingResourcesSums[$bucketKey] ?? 0;
                $itemCounts = $this->createItemCountMapping($targetBucket);
                $aggregationItems = $this->createAggregationFilterRootItems($facetDefinition, $itemCounts, $rawFilter)->all();
                $aggregationGroups = ($facetDefinition instanceof GroupedFacetInterface)
                    ? $this->createAggregationFilterGroups($facetDefinition, $itemCounts, $rawFilter)->all()
                    : [];
                $facetNameTranslationKey = $facetDefinition->getFacetNameTranslationKey();
                $facetName = $this->translator->trans($facetNameTranslationKey);
                $uuid = UUID::generate();

                return new AggregationFilterType(
                    $uuid,
                    $facetName,
                    $bucketKey,
                    $aggregationItems,
                    $aggregationGroups,
                    $missingResourcesSum,
                    $facetDefinition->isItemToManyRelationship(),
                    $facetDefinition->isMissingResourcesSumVisible()
                );
            })->all();
    }

    /**
     * @param array<string,int>                               $itemCounts
     * @param array<string,array<string,array<string,mixed>>> $rawFilter
     *
     * @return Collection<int,AggregationFilterGroup>
     */
    private function createAggregationFilterGroups(GroupedFacetInterface $facetDefinition, array $itemCounts, array $rawFilter): Collection
    {
        $resourceType = $facetDefinition->getGroupsResourceType();
        $resourceType = $this->resourceTypeProvider->getTypeByIdentifier($resourceType);
        Assert::isInstanceOf($resourceType, JsonApiResourceTypeInterface::class);

        $groupsLoadConditions = $facetDefinition->getGroupsLoadConditions();

        // load the groups to be shown in the facet
        $groups = collect($resourceType->getEntities($groupsLoadConditions, []));

        // create mapping from items to their 'selected' state
        $flattedItems = $groups->flatMap(function (object $group) use ($facetDefinition): Collection {
            $itemResourceType = $this->resourceTypeProvider->getTypeByIdentifier($facetDefinition->getItemsResourceType());
            Assert::isInstanceOf($itemResourceType, JsonApiResourceTypeInterface::class);

            return collect($itemResourceType->listPrefilteredEntities($facetDefinition->getGroupItems($group), []));
        });

        $selections = $this->determineSelections($facetDefinition, $rawFilter, $flattedItems);

        return $groups->map(function (object $group) use ($itemCounts, $selections, $facetDefinition): AggregationFilterGroup {
            $aggregationFilterItems = $this->createAggregationFilterGroupItems($facetDefinition, $group, $itemCounts, $selections);
            $groupTitle = $facetDefinition->getGroupTitle($group);
            $groupIdentifier = $facetDefinition->getGroupIdentifier($group);
            $aggregationFilterItems = $aggregationFilterItems->values()->all();

            return new AggregationFilterGroup($groupIdentifier, $groupTitle, $aggregationFilterItems);
        });
    }

    /**
     * Create mapping from items to aggregation count.
     *
     * @param array<array<string,mixed>> $bucket
     *
     * @return array<string,int>
     */
    private function createItemCountMapping(array $bucket): array
    {
        return collect($bucket)->mapWithKeys(static fn (array $item): array => [$item['value'] => $item['count']])->all();
    }

    /**
     * @template I of object
     *
     * @param FacetInterface<I>                               $facetDefinition
     * @param array<string,array<string,array<string,mixed>>> $rawFilter
     * @param Collection<int,I>                               $items
     *
     * @return array<string,bool>
     */
    private function determineSelections(FacetInterface $facetDefinition, array $rawFilter, Collection $items): array
    {
        return $items->unique(static fn (object $item): string => $facetDefinition->getItemIdentifier($item))->mapWithKeys(function (object $item) use ($facetDefinition, $rawFilter): array {
            $itemId = $facetDefinition->getItemIdentifier($item);
            $selected = $this->isItemSelected($itemId, $rawFilter);

            return [$itemId => $selected];
        })->all();
    }

    /**
     * @param array<string,array<string,array<string,mixed>>> $rawFilter
     */
    // @improve: T20647
    private function isItemSelected(string $itemId, array $rawFilter): bool
    {
        foreach ($rawFilter as $key => $conditionOrGroup) {
            if (array_key_exists('condition', $conditionOrGroup)) {
                $condition = $conditionOrGroup['condition'];
                $operator = $condition['operator'] ?? '=';
                // @improve: T20649
                $usedInContainsOperator = 'ARRAY_CONTAINS_VALUE' === $operator || 'CONTAINS' === $operator || '=' === $operator;
                if ($usedInContainsOperator && $condition['value'] === $itemId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param FacetInterface<object>                          $facetDefinition
     * @param array<string,int>                               $itemCount
     * @param array<string,array<string,array<string,mixed>>> $rawFilter
     *
     * @return Collection<string,AggregationFilterItem>
     */
    private function createAggregationFilterRootItems(FacetInterface $facetDefinition, array $itemCount, array $rawFilter): Collection
    {
        $itemsResourceType = $this->resourceTypeProvider->getTypeByIdentifier($facetDefinition->getItemsResourceType());
        Assert::isInstanceOf($itemsResourceType, JsonApiResourceTypeInterface::class);

        $items = $itemsResourceType->getEntities(
            $facetDefinition->getRootItemsLoadConditions(),
            $facetDefinition->getItemsSortMethods()
        );
        $selections = $this->determineSelections($facetDefinition, $rawFilter, collect($items));

        return $this->createAggregationFilterItems($facetDefinition, $items, $itemCount, $selections);
    }

    /**
     * @template TGroup of object
     *
     * @param GroupedFacetInterface<object, TGroup> $facetDefinition
     * @param TGroup                                $group
     * @param array<string,int>                     $itemCounts      mapping from the item IDs to the corresponding
     *                                                               facet count
     * @param array<string,bool>                    $selections      mapping from the item IDs to the boolean if the
     *                                                               corresponding aggregation item is selected in
     *                                                               the UI
     *
     * @return Collection<string,AggregationFilterItem>
     */
    private function createAggregationFilterGroupItems(FacetInterface $facetDefinition, object $group, array $itemCounts, array $selections): Collection
    {
        $itemResourceType = $this->resourceTypeProvider->getTypeByIdentifier($facetDefinition->getItemsResourceType());
        Assert::isInstanceOf($itemResourceType, JsonApiResourceTypeInterface::class);
        $items = $itemResourceType->listPrefilteredEntities($facetDefinition->getGroupItems($group), []);

        return $this->createAggregationFilterItems($facetDefinition, $items, $itemCounts, $selections);
    }

    /**
     * @template TItem of object
     *
     * @param FacetInterface<TItem> $facetDefinition
     * @param array<int,TItem>      $items
     * @param array<string,int>     $itemCounts      mapping from the item IDs to the corresponding
     *                                               facet count
     * @param array<string,bool>    $selections      mapping from the item IDs to the boolean if the
     *                                               corresponding aggregation item is selected in
     *                                               the UI
     *
     * @return Collection<string,AggregationFilterItem>
     */
    private function createAggregationFilterItems(FacetInterface $facetDefinition, array $items, array $itemCounts, array $selections): Collection
    {
        return collect($items)->map(static function (object $item) use ($facetDefinition, $itemCounts, $selections): AggregationFilterItem {
            $itemId = $facetDefinition->getItemIdentifier($item);
            $itemTitle = $facetDefinition->getItemTitle($item);
            $itemDescription = $facetDefinition->getItemDescription($item);
            $count = $itemCounts[$itemId] ?? 0;
            $selected = $selections[$itemId] ?? false;

            return new AggregationFilterItem($itemId, $itemTitle, $itemDescription, $count, $selected);
        });
    }
}
