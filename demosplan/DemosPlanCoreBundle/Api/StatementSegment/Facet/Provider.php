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
use demosplan\DemosPlanCoreBundle\Logic\CustomField\SegmentCustomFieldUsageCounter;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webmozart\Assert\Assert;

/**
 * Answers "how many segments have each option?" for one filter dropdown at a time (tags,
 * assignee, place, or a custom field).
 * It fetches segments that match the current filters, counts how many have each option, then
 * fills in any missing option with a count of 0 so nothing just disappears.
 * This replaces the old `segments.facets.list` RPC, which did the same job using Elasticsearch.
 *
 * Static facets (tags/assignee/place) each have their own {@see StaticFacetInterface}
 * implementation (dispatched by {@see StaticFacetFactory}) - this class stays generic and never
 * mentions tags/assignee/place by name, so adding a new static facet means adding a new class,
 * not editing this one.
 */
class Provider implements ProviderInterface
{
    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly DoctrineCollectionProvider $doctrineCollectionProvider,
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
        private readonly SegmentCustomFieldUsageCounter $customFieldUsageCounter,
        private readonly StaticFacetFactory $staticFacetFactory,
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

        if ($this->staticFacetFactory->supports($requestedFacet)) {
            $facet = $this->staticFacetFactory->create($requestedFacet);

            return $this->countStaticFacet($operation, $facet, $requestedFacet, $filters);
        }

        return $this->countCustomFieldFacet($operation, $requestedFacet, $procedureId, $filters);
    }

    /**
     * Fetches every segment matching every active filter except $excludedKey, via API
     * Platform's own Doctrine collection machinery - it already applies whichever `#[ApiFilter]`
     * {@see Resource} declares (`SearchFilter` on
     * `tags.id`/`assignee.id`/`place.id`/`parentStatementOfSegment.procedure.id`), and it
     * automatically picks up the globally-registered
     * {@see \demosplan\DemosPlanCoreBundle\Api\StatementSegment\Extension\SegmentDoctrineAccessExtension}
     * (registered against the `Segment` Doctrine entity, not any one API resource, so it applies
     * here too) - meaning access-condition scoping needs no separate handling in this class.
     *
     * `searchPhrase` is applied afterward in PHP (a plain substring match on `segment.text`)
     * since it isn't a declared `#[ApiFilter]` - simpler than the retired RPC's multi-field
     * Elasticsearch full-text search (no relevance ranking/stemming), but gives the same
     * "typing in the search box narrows facet counts" behaviour.
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
        $segments = is_array($result) ? $result : iterator_to_array($result);
        Assert::allIsInstanceOf($segments, Segment::class);

        $searchPhrase = $filters['searchPhrase'] ?? null;
        if (is_string($searchPhrase) && '' !== $searchPhrase) {
            $segments = array_values(array_filter(
                $segments,
                static fn (Segment $segment): bool => false !== mb_stripos($segment->getText(), $searchPhrase)
            ));
        }

        return $segments;
    }

    /**
     * Counts how many segments have each option for one facet.
     * Options that no segment currently matches still show up with count 0, instead of
     * disappearing.
     *
     * @return list<FacetResource>
     */
    private function countStaticFacet(Operation $operation, StaticFacetInterface $facet, string $requestedFacet, array $requestedFilters): array
    {
        $excludedFilterKey = "{$requestedFacet}.id";
        $segments = $this->fetchFilteredSegments($operation, $requestedFilters, $excludedFilterKey);
        $selectedIds = (array) ($requestedFilters[$excludedFilterKey] ?? []);

        $counts = $this->countOccurrences($segments, $facet);
        $fullOptionSet = $facet->getFullOptionSet();

        $resources = $this->buildFacetResources($fullOptionSet, $counts, $selectedIds);

        return [...$resources, ...$facet->getExtraResources($segments, $selectedIds)];
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
    private function countOccurrences(array $segments, StaticFacetInterface $facet): array
    {
        $counts = [];
        foreach ($segments as $segment) {
            foreach ($facet->getValues($segment) as $value) {
                $counts[$value->getId()] = ($counts[$value->getId()] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @return list<FacetResource>
     */
    private function countCustomFieldFacet(Operation $operation, string $customFieldId, string $procedureId, array $filters): array
    {
        $configs = $this->customFieldConfigurationRepository->findCustomFieldConfigurationByCriteria(
            CustomFieldSupportedEntity::procedure->value,
            $procedureId,
            CustomFieldSupportedEntity::segment->value,
            $customFieldId,
        );

        if (null === $configs || [] === $configs) {
            throw new BadRequestHttpException(sprintf('No SEGMENT custom field with id "%s" found for this procedure.', $customFieldId));
        }

        $options = $configs[0]->getConfiguration()->getOptions();
        if ([] === $options) {
            return [];
        }

        // Custom fields aren't declared #[ApiFilter] properties (they're per-procedure/
        // dynamic) - nothing to exclude here, only the static facets' own filters need that.
        $segments = $this->fetchFilteredSegments($operation, $filters, null);

        $counts = $this->customFieldUsageCounter->countOptionUsage($segments, $customFieldId);
        $selectedIds = (array) ($filters["customField_{$customFieldId}"] ?? []);

        return array_values(array_filter(array_map(
            static fn ($option) => 0 < ($counts[$option->getId()] ?? 0)
                ? FacetResource::create($option->getId(), $option->getLabel(), $counts[$option->getId()], in_array($option->getId(), $selectedIds, true))
                : null,
            $options
        )));
    }
}
