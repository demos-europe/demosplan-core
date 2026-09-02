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
use demosplan\DemosPlanCoreBundle\Api\AssignableUser\AssignableUserAccessChecker;
use demosplan\DemosPlanCoreBundle\Api\Place\PlaceAccessChecker;
use demosplan\DemosPlanCoreBundle\Api\StatementSegment\AccessChecker;
use demosplan\DemosPlanCoreBundle\Api\StatementSegment\Facet\Resource as FacetResource;
use demosplan\DemosPlanCoreBundle\Api\Tag\AccessChecker as TagAccessChecker;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Logic\CustomField\SegmentCustomFieldUsageCounter;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webmozart\Assert\Assert;

/**
 * Computes counted options for one segment-list facet at a time (tags, assignee, place, or
 * a SEGMENT custom field), scoped by every other currently active filter - the Doctrine
 * equivalent of the facet-exclusion behaviour the retired `segments.facets.list` RPC provided
 * via Elasticsearch.
 *
 * Static facets (tags/assignee/place) are counted by fetching the already-filtered `Segment`
 * collection via API Platform's own {@see DoctrineCollectionProvider} - the same mechanism
 * {@see \demosplan\DemosPlanCoreBundle\Api\StatementSegment\Provider} uses for the segment list
 * itself - which applies whichever `#[ApiFilter]` {@see Resource} declares (`SearchFilter` on
 * `tags.id`/`assignee.id`/`place.id`/`parentStatementOfSegment.procedure.id`) automatically.
 * It also automatically picks up the globally-registered
 * {@see \demosplan\DemosPlanCoreBundle\Api\StatementSegment\Extension\SegmentDoctrineAccessExtension}
 * (registered against the `Segment` Doctrine entity, not any one API resource, so it applies here
 * too), meaning access-condition scoping needs no separate handling in this class. Counting itself
 * happens in PHP over the returned entities' `getTags()`/`getAssignee()`/`getPlace()`, rather than
 * a SQL `GROUP BY`. Facet exclusion (a filter never zeroes out its own count) is done by removing
 * the current facet's own key from the filters array before fetching.
 */
class Provider implements ProviderInterface
{
    private const STATIC_FACETS = ['tags', 'assignee', 'place'];
    private const UNASSIGNED_ID = 'unassigned';

    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly DoctrineCollectionProvider $doctrineCollectionProvider,
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
        private readonly SegmentCustomFieldUsageCounter $customFieldUsageCounter,
        private readonly PlaceAccessChecker $placeAccessChecker,
        private readonly PlaceRepository $placeRepository,
        private readonly TagAccessChecker $tagAccessChecker,
        private readonly TagRepository $tagRepository,
        private readonly AssignableUserAccessChecker $assignableUserAccessChecker,
        private readonly UserRepository $userRepository,
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
        $facet = $filters['facet'];
        $procedureId = $filters['parentStatementOfSegment.procedure.id'];

        if (in_array($facet, self::STATIC_FACETS, true)) {
            return $this->countStaticFacet($operation, $facet, $filters);
        }

        return $this->countCustomFieldFacet($operation, $facet, $procedureId, $filters);
    }

    /**
     * Fetches every segment matching every active filter except $excludedKey, via API
     * Platform's own Doctrine collection machinery - see the class docblock for why that
     * already covers both `#[ApiFilter]`-declared filtering and access-condition scoping.
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
     * Counts how many segments have each option for one facet (tags, assignee, or place).
     * Options that no segment currently matches still show up with count 0, instead of disappearing.
     * For "assignee", it also adds one extra "unassigned" option for segments with nobody assigned.
 *
     * @return list<FacetResource>
     */
    private function countStaticFacet(Operation $operation, string $requestedFacet, array $requestedFilters): array
    {
        $excludedFilterKey = "{$requestedFacet}.id";
        $segments = $this->fetchFilteredSegments($operation, $requestedFilters, $excludedFilterKey);
        $selectedIds = (array) ($requestedFilters[$excludedFilterKey] ?? []);

        $counts = [];
        foreach ($segments as $segment) {
            foreach ($this->getFacetValues($segment, $requestedFacet) as $value) {
                $counts[$value->getId()] = ($counts[$value->getId()] ?? 0) + 1;
            }
        }

        $fullOptionSet = $this->getFullOptionSet($requestedFacet);

        $resources = array_map(
            static fn (string $id, array $option): FacetResource => FacetResource::create(
                $id,
                $option['label'],
                $counts[$id] ?? 0,
                in_array($id, $selectedIds, true),
                null,
                $option['groupId'],
                $option['groupLabel'],
            ),
            array_keys($fullOptionSet),
            $fullOptionSet
        );

        if ('assignee' === $requestedFacet) {
            $resources[] = $this->countUnassigned($operation, $requestedFilters, $selectedIds);
        }

        return $resources;
    }

    /**
     * @return iterable<Tag|User|Place>
     */
    private function getFacetValues(Segment $segment, string $facet): iterable
    {
        return match ($facet) {
            'tags'     => $segment->getTags(),
            'assignee' => null !== $segment->getAssignee() ? [$segment->getAssignee()] : [],
            // Segment::getPlace() is typed to return PlaceInterface (never null), but the
            // underlying `place_id` column is genuinely nullable (Segment.php:55) - the
            // type-hint doesn't reflect the DB reality here, so this null check is real, not
            // dead code, despite what static analysis of the declared type alone would suggest.
            // @phpstan-ignore notIdentical.alwaysTrue
            'place' => null !== $segment->getPlace() ? [$segment->getPlace()] : [],
            default => [],
        };
    }

    /**
     * Enumerates every option that exists for the current procedure, regardless of whether
     * any segment currently references it - reuses the same access-condition logic the
     * corresponding read-only API resources already apply, so "which tags/places/users belong
     * to this procedure" can't drift from those endpoints' own definitions.
     *
     * @return array<string, array{label: string, groupId: ?string, groupLabel: ?string}>
     */
    private function getFullOptionSet(string $facet): array
    {
        return match ($facet) {
            'place' => $this->buildOptionSet(
                $this->placeRepository->getEntities($this->placeAccessChecker->getAccessConditions(), []),
                static fn (Place $place): array => [
                    'label'      => $place->getName(),
                    'groupId'    => null,
                    'groupLabel' => null,
                ],
            ),
            'tags' => $this->buildOptionSet(
                $this->tagRepository->getEntities($this->tagAccessChecker->getAccessConditions(), []),
                static fn (Tag $tag): array => [
                    'label'      => $tag->getTitle(),
                    'groupId'    => $tag->getTopic()->getId(),
                    'groupLabel' => $tag->getTopic()->getTitle(),
                ],
            ),
            'assignee' => $this->buildOptionSet(
                $this->userRepository->getEntities($this->assignableUserAccessChecker->getAccessConditions(), []),
                static fn (User $user): array => [
                    'label'      => $user->getFullname(),
                    'groupId'    => null,
                    'groupLabel' => null,
                ],
            ),
            default => [],
        };
    }

    /**
     * @param list<object>                                                                  $entities
     * @param callable(object): array{label: string, groupId: ?string, groupLabel: ?string} $describe
     *
     * @return array<string, array{label: string, groupId: ?string, groupLabel: ?string}>
     */
    private function buildOptionSet(array $entities, callable $describe): array
    {
        $optionSet = [];
        foreach ($entities as $entity) {
            $optionSet[$entity->getId()] = $describe($entity);
        }

        return $optionSet;
    }

    /**
     * Segments with no assignee are excluded by {@see getFacetValues()} for the `assignee`
     * facet, so they need a separate tally - same active filters (assignee's own filter
     * excluded, matching {@see countStaticFacet}), just counting `getAssignee() === null`
     * instead. Mirrors the retired RPC's `missingResourcesSum`, which the frontend renders as
     * a synthetic "not assigned" option.
     */
    private function countUnassigned(Operation $operation, array $filters, array $selectedIds): FacetResource
    {
        $segments = $this->fetchFilteredSegments($operation, $filters, 'assignee.id');
        $count = count(array_filter($segments, static fn (Segment $segment): bool => null === $segment->getAssignee()));

        return FacetResource::create(self::UNASSIGNED_ID, '', $count, in_array(self::UNASSIGNED_ID, $selectedIds, true));
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
