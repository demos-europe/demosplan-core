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
 * Instead of running those conditions as their own executed query and feeding the
 * resulting IDs back in as bound values (two round-trips), this builds a second,
 * never-executed QueryBuilder for the same conditions via
 * {@see SegmentRepository::generateAccessConditionQueryBuilder()} and embeds its DQL
 * as a subquery: `<rootAlias>.id IN (<subquery DQL>)`. Only the outer QueryBuilder is
 * ever executed, so the database sees one query with a nested SELECT -- the EDT rule
 * is still reused verbatim, just without materializing it separately first.
 *
 * Note: EDT's QueryBuilderPreparer numbers the subquery's bound parameters
 * positionally from 0 (`?0`, `?1`, ...) on every call. That's only safe to merge onto
 * the outer QueryBuilder because API Platform's own filters on this resource
 * (SearchFilter/ExistsFilter/OrderFilter) bind named, not positional, parameters. If a
 * future filter here starts using positional parameters, this merge would need
 * renumbering to avoid a collision.
 *
 * Important: the $resourceClass parameter API Platform passes here is the *Doctrine
 * entity* class (Segment::class, from Provider's DoctrineOptions(entityClass: ...)),
 * not the ApiResource DTO class (StatementSegment\Resource). An earlier version of
 * this guard compared against the DTO class, which never matched -- this extension's
 * body never ran, on any request, regardless of anything else in this file.
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
        if (Segment::class !== $resourceClass) {
            return;
        }

        $subQueryBuilder = $this->segmentRepository->generateAccessConditionQueryBuilder(
            $this->accessChecker->getAccessConditions()
        );
        $subAlias = $subQueryBuilder->getRootAliases()[0];
        $subQueryBuilder->select("$subAlias.id");

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere($queryBuilder->expr()->in("$rootAlias.id", $subQueryBuilder->getDQL()));

        foreach ($subQueryBuilder->getParameters() as $parameter) {
            $queryBuilder->setParameter($parameter->getName(), $parameter->getValue(), $parameter->getType());
        }
    }
}
