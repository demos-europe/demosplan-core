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
use demosplan\DemosPlanCoreBundle\Api\StatementSegment\Resource as StatementSegmentResource;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * Restricts Doctrine-backed StatementSegment collection reads to procedures the
 * current user is allowed to access.
 *
 * This extension operates on a plain Doctrine QueryBuilder built and shared by API
 * Platform's own Doctrine ORM CollectionProvider, but the access rule is authored in
 * EDT condition objects ({@see AccessChecker::getAccessConditions()}). Those can't be
 * applied directly to that shared QueryBuilder: the class that turns EDT conditions
 * into DQL (EDT\DqlQuerying\Utilities\QueryBuilderPreparer) is marked @internal and
 * assumes it owns the entire query -- it sets its own alias, SELECT, FROM, and WHERE
 * (overwriting, not merging with, whatever other extensions already added).
 *
 * Instead this reuses AccessChecker::getAccessConditions() completely unmodified via
 * SegmentRepository::getEntityIdentifiers(), which runs those EDT conditions in its
 * own isolated, disposable query and returns a plain array of allowed segment IDs.
 * That ID list is safe to apply to API Platform's query afterward as a simple
 * `id IN (...)` restriction, with no alias/parameter/join conflicts. The trade-off is
 * one extra database round-trip per collection request -- the price of reusing the
 * EDT-authored rule verbatim instead of duplicating or re-deriving it.
 */
final class SegmentDoctrineAccessExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly SegmentRepository $segmentRepository,
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (StatementSegmentResource::class !== $resourceClass) {
            return;
        }

        $allowedIds = $this->segmentRepository->getEntityIdentifiers(
            $this->accessChecker->getAccessConditions(),
            [],
            'id'
        );

        if ([] === $allowedIds) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere($queryBuilder->expr()->in("$rootAlias.id", ':allowedSegmentIds'))
            ->setParameter('allowedSegmentIds', $allowedIds);
    }
}
