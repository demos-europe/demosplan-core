<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\StatementSegment\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Matches assignee IN (ids) OR assignee IS NULL; use only when both a specific
 * assignee and "unassigned" are needed together, since ApiPlatform ANDs
 * independently declared filters rather than ORing them.
 */
final class AssigneeOrUnassignedFilter implements FilterInterface
{
    private const UNASSIGNED_SENTINEL = '';

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $parameter = $context['parameter'] ?? null;
        $value = $parameter?->getValue();

        if (!is_array($value) || [] === $value) {
            return;
        }

        $wantsUnassigned = in_array(self::UNASSIGNED_SENTINEL, $value, true);
        $ids = array_values(array_filter(
            $value,
            static fn (mixed $id): bool => self::UNASSIGNED_SENTINEL !== $id
        ));

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $conditions = [];

        if ($wantsUnassigned) {
            $conditions[] = $queryBuilder->expr()->isNull("$rootAlias.assignee");
        }

        if ([] !== $ids) {
            $parameterName = $queryNameGenerator->generateParameterName('assigneeOrUnassignedIds');
            $conditions[] = $queryBuilder->expr()->in("$rootAlias.assignee", ":$parameterName");
            $queryBuilder->setParameter($parameterName, $ids);
        }

        if ([] === $conditions) {
            return;
        }

        $queryBuilder->andWhere($queryBuilder->expr()->orX(...$conditions));
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'assigneeOrUnassigned' => [
                'property'      => 'assignee',
                'type'          => 'string',
                'is_collection' => true,
                'description'   => 'Filter by one or more assignee ids; include an empty entry to also match segments with no assignee.',
            ],
        ];
    }
}
