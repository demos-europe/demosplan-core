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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use Doctrine\ORM\QueryBuilder;

/**
 * Appends the segment id as a final, always-unique sort key.
 *
 * The declared {@see \demosplan\DemosPlanCoreBundle\Api\StatementSegment\Resource}
 * OrderFilter properties (e.g. `parentStatementOfSegment.original.internId`) are not
 * unique per segment: many segments share the same parent/original statement, so
 * ordering by them alone leaves large groups of tied rows. SQL does not define the
 * relative order of tied rows, and MySQL is free to resolve those ties differently
 * depending on the query plan it picks -- which, in practice, differs between an
 * unbounded fetch and a LIMIT/OFFSET page of the same query. Two consequences follow:
 * pages fetched separately do not stitch back together into the order the unbounded
 * fetch shows, and a row whose tie is resolved differently across two page queries can
 * be duplicated across pages or dropped from both.
 *
 * Adding this deterministic tiebreaker after the client-requested ordering (via
 * addOrderBy, which appends rather than replaces) fixes both: rows within a tie group
 * are always ordered the same way regardless of query plan or LIMIT/OFFSET.
 */
final class SegmentDeterministicOrderExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (Segment::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->addOrderBy("$rootAlias.id", 'ASC');
    }
}
