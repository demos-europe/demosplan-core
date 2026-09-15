<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\StatementSegment\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use demosplan\DemosPlanCoreBundle\Api\StatementSegment\AccessChecker;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\SearchParams;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;
use Doctrine\ORM\QueryBuilder;

/**
 * Applies the "search across selected fields" box (see CustomSearch.vue) to
 * StatementSegment collection queries via Elasticsearch, not a SQL LIKE scan.
 *
 * A SQL OR-across-fields alternative (LOWER(x) LIKE LOWER(:term)) benchmarked
 * at ~230ms for 25,000 segments and ~850ms for 100,000, doubled again by API
 * Platform's automatic pagination COUNT query -- and 25k-segment procedures
 * already exist in production. Elasticsearch stayed at 1-18ms for the same
 * corpus sizes regardless of how many documents matched, because it looks
 * terms up in an inverted index instead of scanning column content.
 *
 * This reuses the existing multi-field search infrastructure
 * ({@see JsonApiEsService::getEsFilteredResult()}, already used by the legacy
 * `segment.load.id` RPC in
 * {@see \demosplan\DemosPlanCoreBundle\Logic\Segment\RpcBulkEditor\RpcSegmentIdLoader})
 * to get matching segment IDs, then applies them to the shared QueryBuilder
 * the same way {@see SegmentDoctrineAccessExtension} applies its own ID list:
 * a plain `id IN (...)` restriction. Unlike that extension, the IDs here come
 * from Elasticsearch rather than a Doctrine subquery, so they can only be
 * embedded as a bound array, not a DQL subquery.
 *
 * The `StatementSegmentResourceType` (EDT) dependency exists purely to
 * satisfy {@see JsonApiEsService}'s type signature (search config, scopes, ES
 * index) -- the same "keep using EDT where it's still the source of truth
 * for this data" trade-off {@see AccessChecker} already makes.
 *
 * Note: `$resourceClass` is the Doctrine entity class (`Segment::class`), not
 * the API resource DTO class.
 */
final class SegmentTextSearchExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly SegmentRepository $segmentRepository,
        private readonly JsonApiEsService $jsonApiEsService,
        private readonly StatementSegmentResourceType $segmentResourceType,
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (Segment::class !== $resourceClass) {
            return;
        }

        $searchTerm = trim((string) ($context['filters']['search']['value'] ?? ''));
        if ('' === $searchTerm) {
            return;
        }

        $searchParamsArray = ['value' => $searchTerm];
        $requestedFields = (array) ($context['filters']['search']['fieldsToSearch'] ?? []);
        if ([] !== $requestedFields) {
            $searchParamsArray['fieldsToSearch'] = array_values($requestedFields);
        }
        $searchParams = SearchParams::createOptional($searchParamsArray);

        if (null === $searchParams) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        // The "universe" this search may match within: the same access-controlled
        // segment IDs SegmentDoctrineAccessExtension restricts the main query to.
        // Passed to Elasticsearch as a should-filter on `id` so permission scoping
        // and text search happen in a single ES query.
        $allowedIds = $this->segmentRepository->getEntityIdentifiers(
            $this->accessChecker->getAccessConditions(),
            [],
            'id'
        );

        if ([] === $allowedIds) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $elasticsearchResult = $this->jsonApiEsService->getEsFilteredResult(
            $this->segmentResourceType,
            $allowedIds,
            $searchParams,
            true,
            null
        );
        $matchedIds = array_column($this->jsonApiEsService->toLegacyResultES($elasticsearchResult), 'id');

        if ([] === $matchedIds) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $queryBuilder
            ->andWhere($queryBuilder->expr()->in("$rootAlias.id", ':searchMatchedIds'))
            ->setParameter('searchMatchedIds', $matchedIds);
    }
}
