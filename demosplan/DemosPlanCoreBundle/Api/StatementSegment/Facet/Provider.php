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

use ApiPlatform\Doctrine\Orm\Extension\FilterExtension;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
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
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webmozart\Assert\Assert;

/**
 * Computes counted options for one segment-list facet at a time (tags, assignee, place, or
 * a SEGMENT custom field), scoped by every other currently active filter - the Doctrine
 * equivalent of the facet-exclusion behaviour the retired `segments.facets.list` RPC provided
 * via Elasticsearch.
 *
 * Static facets (tags/assignee/place) are scoped by delegating to API Platform's own
 * {@see FilterExtension}, which applies whichever `#[ApiFilter]` {@see Resource} declares
 * (`SearchFilter` on `tags.id`/`assignee.id`/`place.id`/`parentStatementOfSegment.procedure.id`)
 * - see {@see Resource} for why `SearchFilter`+`FilterExtension` rather than the newer
 * `ExactFilter`+`ParameterExtension` combination. Facet exclusion is done by removing the
 * current facet's own key from the filters array passed to `FilterExtension`, so its own
 * selection never zeroes out its own count.
 *
 * Access conditions reuse {@see SegmentRepository::generateAccessConditionQueryBuilder()}
 * directly (no need for the subquery bridge
 * {@see \demosplan\DemosPlanCoreBundle\Api\StatementSegment\Extension\SegmentDoctrineAccessExtension}
 * relies on, since that trick only exists to bolt access conditions onto a QueryBuilder API
 * Platform's own DoctrineCollectionProvider already built - here we build our own from scratch).
 */
class Provider implements ProviderInterface
{
    private const STATIC_FACETS = ['tags', 'assignee', 'place'];
    private const UNASSIGNED_ID = 'unassigned';

    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly SegmentRepository $segmentRepository,
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
        private readonly SegmentCustomFieldUsageCounter $customFieldUsageCounter,
        private readonly FilterExtension $filterExtension,
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
     * Builds the base "every segment visible to the current user, matching every active
     * filter except $excludedKey" QueryBuilder that both static and custom-field facets
     * count against, by delegating filter application to {@see FilterExtension} with the
     * excluded key removed from the filters array.
     *
     * Also applies `searchPhrase` (a plain substring match on `segment.text`) if present -
     * this is a simpler match than the retired RPC's multi-field Elasticsearch full-text
     * search (no relevance ranking/stemming), but gives the same "typing in the search box
     * narrows facet counts" behaviour.
     */
    private function baseQueryBuilder(Operation $operation, array $filters, ?string $excludedKey): QueryBuilder
    {
        $qb = $this->segmentRepository->generateAccessConditionQueryBuilder(
            $this->accessChecker->getAccessConditions()
        );

        if (null !== $excludedKey) {
            unset($filters[$excludedKey]);
        }

        $this->filterExtension->applyToCollection($qb, new QueryNameGenerator(), Segment::class, $operation, ['filters' => $filters]);

        $searchPhrase = $filters['searchPhrase'] ?? null;
        if (is_string($searchPhrase) && '' !== $searchPhrase) {
            $rootAlias = $qb->getRootAliases()[0];
            $qb->andWhere("$rootAlias.text LIKE :segmentFacetSearchPhrase")
                ->setParameter('segmentFacetSearchPhrase', '%'.$searchPhrase.'%');
        }

        return $qb;
    }

    /**
     * Every option usable in the procedure is returned, not just the ones with at least one
     * matching segment under the current filters - an option with zero matches still gets
     * `count: 0` rather than being silently absent, matching the retired RPC's behaviour
     * (its `FacetFactory` enumerated the full option set independently of the Elasticsearch
     * aggregation and merged counts in afterward). The counting query below only produces
     * `optionId => count` for options that DO have matches; {@see getFullOptionSet()} supplies
     * everything else, defaulted to 0.
     *
     * @return list<FacetResource>
     */
    private function countStaticFacet(Operation $operation, string $facet, array $filters): array
    {
        $qb = $this->baseQueryBuilder($operation, $filters, "{$facet}.id");
        $rootAlias = $qb->getRootAliases()[0];
        $selectedIds = (array) ($filters["{$facet}.id"] ?? []);

        $qb->join("$rootAlias.$facet", 'facetAssoc')
            ->select('facetAssoc.id AS optionId, COUNT(DISTINCT '.$rootAlias.'.id) AS optionCount')
            ->groupBy('facetAssoc.id');

        $counts = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $counts[$row['optionId']] = (int) $row['optionCount'];
        }

        $fullOptionSet = $this->getFullOptionSet($facet);

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

        if ('assignee' === $facet) {
            $resources[] = $this->countUnassigned($operation, $filters, $selectedIds);
        }

        return $resources;
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
     * Segments with no assignee are excluded by the `assignee` facet's `JOIN` above, so they
     * need a separate query - same base filters (assignee's own filter excluded, matching
     * {@see countStaticFacet}), just testing `assignee IS NULL` instead of joining. Mirrors
     * the retired RPC's `missingResourcesSum`, which the frontend renders as a synthetic
     * "not assigned" option.
     */
    private function countUnassigned(Operation $operation, array $filters, array $selectedIds): FacetResource
    {
        $qb = $this->baseQueryBuilder($operation, $filters, 'assignee.id');
        $rootAlias = $qb->getRootAliases()[0];

        $count = (int) $qb
            ->select("COUNT(DISTINCT $rootAlias.id)")
            ->andWhere("$rootAlias.assignee IS NULL")
            ->getQuery()
            ->getSingleScalarResult();

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
        $qb = $this->baseQueryBuilder($operation, $filters, null);
        $rootAlias = $qb->getRootAliases()[0];
        $segments = $qb->select($rootAlias)->getQuery()->getResult();
        Assert::allIsInstanceOf($segments, Segment::class);

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
