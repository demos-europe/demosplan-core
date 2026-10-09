<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\StatementSegment\Facet;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider as DoctrineCollectionProvider;
use ApiPlatform\Doctrine\Orm\State\Options as DoctrineOptions;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\Api\StatementSegment\AccessChecker;
use demosplan\DemosPlanCoreBundle\Api\StatementSegment\Facet\Resource as FacetResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webmozart\Assert\Assert;

/**
 * Answers "how many segments have each option?" for one filter dropdown at a time.
 * It counts the segments that match all the other active filters, and options with no match still show up with a count of 0.
 * Today the filters are tags, assignee, place and custom fields; tags, assignee and place are listed in {@see StaticFacets}.
 * To add a new filter of the same kind, add one entry there; anything that works differently, like custom fields, gets its own class.
 */
class Provider implements ProviderInterface
{
    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly DoctrineCollectionProvider $doctrineCollectionProvider,
        private readonly StaticFacets $staticFacets,
        private readonly CustomFieldFacet $customFieldFacet,
    ) {
    }

    /**
     * @return list<FacetResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        Assert::same($operation->getClass(), FacetResource::class);

        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        $filters = $context['filters'] ?? [];
        $requestedFacet = $filters['facet'];
        $procedureId = $filters['parentStatementOfSegment.procedure.id'];

        if ($this->staticFacets->supports($requestedFacet)) {
            return $this->countStaticFacet($operation, $requestedFacet, $filters);
        }

        if ($this->customFieldFacet->supports($requestedFacet, $procedureId)) {
            // Custom fields are filtered by the `customField[<fieldId>][]` parameter, the same as in
            // the segment list. Like the static facets, this facet is counted without its own
            // selection, so picking an option never hides the field's other options.
            $segments = $this->fetchFilteredSegments(
                $this->withoutOwnCustomFieldSelection($operation, $requestedFacet),
                $filters,
                null
            );
            $selectedIds = $this->getSelectedCustomFields($operation)[$requestedFacet] ?? [];

            return $this->customFieldFacet->getResources($requestedFacet, $procedureId, $segments, $selectedIds);
        }

        throw new BadRequestHttpException(sprintf('Unknown facet "%s".', $requestedFacet));
    }

    /**
     * @return array<string, list<string>> customFieldId => selected optionIds
     */
    private function getSelectedCustomFields(Operation $operation): array
    {
        $value = $operation->getParameters()?->get('customField')?->getValue();

        return is_array($value) ? $value : [];
    }

    /**
     * Returns the operation with one custom field's own selection removed from the `customField`
     * parameter. The selections of all other custom fields stay.
     * The filter reads the parameter's value, not the `filters` context, so the value has to be replaced here.
     */
    private function withoutOwnCustomFieldSelection(Operation $operation, string $facet): Operation
    {
        $parameters = $operation->getParameters();
        $parameter = $parameters?->get('customField');
        $selected = $this->getSelectedCustomFields($operation);

        if (null === $parameters || null === $parameter || !array_key_exists($facet, $selected)) {
            return $operation;
        }

        unset($selected[$facet]);

        // Work on copies, so the shared operation metadata keeps the request's original value.
        $parametersCopy = clone $parameters;
        $parametersCopy->add('customField', (clone $parameter)->setValue([] === $selected ? null : $selected));

        return $operation->withParameters($parametersCopy);
    }

    /**
     * Loads the segments that match all the active filters, except the one named in $excludedKey.
     * The filters, the text search and the user's access rules are all applied automatically by API Platform
     * and its extensions, so we don't write any query here.
     *
     * @return list<Segment>
     */
    private function fetchFilteredSegments(Operation $operation, array $filters, ?string $excludedKey): array
    {
        if (null !== $excludedKey) {
            unset($filters[$excludedKey]);
        }

        $operation = $operation->withStateOptions(new DoctrineOptions(
            entityClass: Segment::class,
            handleLinks: static function (): void {
                // Required by API Platform's DoctrineOptions, or it throws - this resource has no links to handle.
            }
        ));

        $result = $this->doctrineCollectionProvider->provide($operation, [], ['filters' => $filters]);
        $segments = iterator_to_array($result, false);
        Assert::allIsInstanceOf($segments, Segment::class);

        return $segments;
    }

    /**
     * Counts how many segments have each option for one facet.
     * Options that no segment currently matches still show up with count 0, instead of
     * disappearing.
     *
     * @return list<FacetResource>
     */
    private function countStaticFacet(Operation $operation, string $requestedFacet, array $requestedFilters): array
    {
        $excludedFilterKey = "{$requestedFacet}.id";
        $segments = $this->fetchFilteredSegments($operation, $requestedFilters, $excludedFilterKey);
        $selectedIds = (array) ($requestedFilters[$excludedFilterKey] ?? []);

        $counts = $this->countOccurrences($segments, $requestedFacet);
        $fullOptionSet = $this->staticFacets->getFullOptionSet($requestedFacet);

        $resources = $this->buildFacetResources($fullOptionSet, $counts, $selectedIds);

        return [...$resources, ...$this->staticFacets->getExtraResources($requestedFacet, $segments, $selectedIds)];
    }

    /**
     * Combines the full list of options with their counts, defaulting to 0 for any option with
     * no matches, and marks which ones are currently selected.
     *
     * @param array<string, array{label: string, groupId: ?string, groupLabel: ?string}> $fullOptionSet
     * @param array<string, int>                                                         $counts
     * @param list<string>                                                               $selectedIds
     *
     * @return list<FacetResource>
     */
    private function buildFacetResources(array $fullOptionSet, array $counts, array $selectedIds): array
    {
        $resources = [];
        foreach ($fullOptionSet as $id => $option) {
            $count = $counts[$id] ?? 0;
            $isSelected = in_array($id, $selectedIds, true);

            $resources[] = FacetResource::create(
                $id,
                $option['label'],
                $count,
                $isSelected,
                null,
                $option['groupId'],
                $option['groupLabel'],
            );
        }

        return $resources;
    }

    /**
     * Counts how many segments have each option (e.g. how many segments have each tag).
     *
     * @param list<Segment> $segments
     *
     * @return array<string, int> optionId => count
     */
    private function countOccurrences(array $segments, string $requestedFacet): array
    {
        $counts = [];
        foreach ($segments as $segment) {
            foreach ($this->staticFacets->getValues($requestedFacet, $segment) as $value) {
                $counts[$value->getId()] = ($counts[$value->getId()] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
