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
 * Restricts StatementSegment collection queries to procedures accessible to the
 * current user.
 *
 * Access rules come from EDT conditions ({@see AccessChecker::getAccessConditions()}),
 * but those cannot be applied directly to API Platform's shared QueryBuilder without
 * overriding parts of the existing query. So we build a second QueryBuilder via
 * {@see SegmentRepository::generateAccessConditionQueryBuilder()}, select segment IDs,
 * and apply it as a subquery: `<rootAlias>.id IN (<subquery DQL>)`.
 *
 * Subquery parameters are merged into the outer query. This is safe as long as other
 * filters here continue using named parameters; positional parameter usage could
 * require renumbering to avoid collisions.
 *
 * Note: `$resourceClass` is the Doctrine entity class (`Segment::class`), not the
 * API resource DTO class.
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
