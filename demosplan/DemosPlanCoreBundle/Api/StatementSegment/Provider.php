<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\StatementSegment;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider as DoctrineCollectionProvider;
use ApiPlatform\Doctrine\Orm\State\Options as DoctrineOptions;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Api\Common\MappingPaginator;
use demosplan\DemosPlanCoreBundle\Api\StatementSegment\Resource as StatementSegmentResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\Statement\RecommendationVersionService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class Provider implements ProviderInterface
{
    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly SegmentRepository $segmentRepository,
        private readonly DoctrineCollectionProvider $doctrineCollectionProvider,
        private readonly StatementService $statementService,
        private readonly RecommendationVersionService $recommendationVersionService,
        private readonly CurrentUserInterface $currentUser,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), StatementSegmentResource::class);

        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection($operation, $uriVariables, $context);
        }

        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        return null;
    }

    private function provideSingle(string $id): ?StatementSegmentResource
    {
        try {
            $segment = $this->segmentRepository->getEntityByIdentifier(
                $id,
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        $currentVersionNumbers = $this->currentUser->hasPermission('feature_enable_recommendation_versions')
            ? $this->recommendationVersionService->getCurrentVersionNumbersForSegments([$segment])
            : [];

        return StatementSegmentResource::fromEntity(
            $segment,
            $this->statementService->getProcessingStatus($segment->getParentStatementOfSegment()),
            $currentVersionNumbers[$segment->getId()] ?? null,
        );
    }

    /**
     * Delegates to API Platform's own Doctrine ORM collection provider so that its
     * filter/extension mechanism (access control via
     * {@see Extension\SegmentDoctrineAccessExtension},
     * sorting via the declared OrderFilter on {@see StatementSegmentResource}) applies.
     *
     * Pagination is on by default and client-controlled via `page`/`itemsPerPage` (capped at
     * paginationMaximumItemsPerPage); pass `pagination=false` to get all matching segments in
     * one unbounded response instead.
     *
     * @return PaginatorInterface<StatementSegmentResource>|list<StatementSegmentResource>
     */
    private function provideCollection(Operation $operation, array $uriVariables, array $context): PaginatorInterface|array
    {
        // handleLinks has to be set or API Platform throws an error, but we don't need it to do anything here.
        $operation = $operation->withStateOptions(new DoctrineOptions(
            entityClass: Segment::class,
            handleLinks: static function (): void {
                // Intentionally empty: API Platform requires handleLinks to be set,
                // but no link handling is needed here.
            }
        ));

        $context = $this->addPaginationFilters($context);
        $result = $this->doctrineCollectionProvider->provide($operation, $uriVariables, $context);

        /*
         * Materialized here (rather than left lazy) so the segment ids are available for the
         * batched recommendation-version lookup below. For the PaginatorInterface case this does
         * NOT cost an extra query: AbstractPaginator::getIterator() caches its iterator on first
         * access, so MappingPaginator iterating $result again further down reuses this same,
         * already-fetched result instead of re-querying.
         */
        $segments = $result instanceof PaginatorInterface || !is_array($result)
            ? iterator_to_array($result)
            : $result;

        $currentVersionNumbers = $this->currentUser->hasPermission('feature_enable_recommendation_versions')
            ? $this->recommendationVersionService->getCurrentVersionNumbersForSegments($segments)
            : [];

        // Deduplicated by id: many segments share the same parent statement, and
        // getProcessingStatuses() would otherwise redo the same per-statement work
        // once per segment instead of once per distinct statement.
        $parentStatements = [];
        foreach ($segments as $segment) {
            $parentStatement = $segment->getParentStatementOfSegment();
            $parentStatements[$parentStatement->getId()] = $parentStatement;
        }
        $processingStatuses = $this->statementService->getProcessingStatuses(array_values($parentStatements));

        $map = fn (Segment $segment): StatementSegmentResource => StatementSegmentResource::fromEntity(
            $segment,
            $processingStatuses[$segment->getParentStatementOfSegment()->getId()] ?? null,
            $currentVersionNumbers[$segment->getId()] ?? null,
        );

        if ($result instanceof PaginatorInterface) {
            return new MappingPaginator($result, $map);
        }

        return array_map($map, $segments);
    }

    /**
     * Tops up `$context['filters']` with the plain `page`/`itemsPerPage`/`pagination` query
     * params, which is where API Platform's pagination reads them from.
     *
     * API Platform's own JsonApiProvider is meant to hoist these off the query string, but for
     * this operation they were observed not to arrive, so they are read from the request as a
     * fallback. Values already present are never overwritten, so this is a no-op whenever the
     * built-in hoisting does work.
     */
    private function addPaginationFilters(array $context): array
    {
        $request = $context['request'] ?? null;
        // Guard rather than skip: silently dropping the params would return every segment
        // unbounded instead of the requested page, which is hard to spot from the response.
        Assert::isInstanceOf($request, Request::class, 'Cannot read pagination parameters: no request in the provider context.');

        foreach (['page', 'itemsPerPage', 'pagination'] as $parameterName) {
            if ($request->query->has($parameterName) && !isset($context['filters'][$parameterName])) {
                $context['filters'][$parameterName] = $request->query->get($parameterName);
            }
        }

        return $context;
    }
}
