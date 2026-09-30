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
use ApiPlatform\Doctrine\Orm\Util\QueryBuilderHelper;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * Sorts segments by values the segments list displays but which are not plain columns of the
 * Segment entity, so API Platform's OrderFilter cannot express them:
 *
 * - `order[submitter]`: the first line shown in the "Einreicher*in" column, i.e. the parent statement's
 *   author name, falling back to its submit name and finally to its organisation name, since the
 *   column only shows the organisation for statements without any person name.
 * - `order[externId]`: natural order of the segment ID. IDs look like "M12-3", so plain string
 *   ordering would put "M10-1" before "M2-1". Ordering by length before value, first on the parent
 *   statement's ID and then on the segment's own ID, yields the order a user expects.
 *
 * Both keys are read from the same `order[...]` query parameter the OrderFilter uses, so the
 * frontend can treat them like any other sortable property.
 */
final class VirtualPropertyOrderFilter implements FilterInterface
{
    public const ORDER_PARAMETER_NAME = 'order';
    public const PROPERTY_SUBMITTER = 'submitter';
    public const PROPERTY_EXTERN_ID = 'externId';

    private const PARENT_STATEMENT_ASSOCIATION = 'parentStatementOfSegment';
    private const META_ASSOCIATION = 'meta';
    private const DIRECTIONS = ['asc', 'desc'];

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $orders = $context['filters'][self::ORDER_PARAMETER_NAME] ?? null;
        if (!is_array($orders)) {
            return;
        }

        foreach ($orders as $property => $value) {
            $direction = is_string($value) ? strtolower($value) : null;
            if (!in_array($direction, self::DIRECTIONS, true)) {
                continue;
            }

            if (self::PROPERTY_SUBMITTER === $property) {
                $this->orderBySubmitter($queryBuilder, $queryNameGenerator, $direction);
            }

            if (self::PROPERTY_EXTERN_ID === $property) {
                $this->orderByExternId($queryBuilder, $queryNameGenerator, $direction);
            }
        }
    }

    private function orderBySubmitter(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $direction): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $parentAlias = QueryBuilderHelper::addJoinOnce($queryBuilder, $queryNameGenerator, $rootAlias, self::PARENT_STATEMENT_ASSOCIATION, Join::LEFT_JOIN);
        $metaAlias = QueryBuilderHelper::addJoinOnce($queryBuilder, $queryNameGenerator, $parentAlias, self::META_ASSOCIATION, Join::LEFT_JOIN);

        // Same fallback chain as the first line of the "Einreicher*in" column.
        $submitterField = $queryNameGenerator->generateParameterName('submitterSort');
        $queryBuilder
            ->addSelect(sprintf(
                "COALESCE(NULLIF(%1\$s.authorName, ''), NULLIF(%1\$s.submitName, ''), %1\$s.orgaName) AS HIDDEN %2\$s",
                $metaAlias,
                $submitterField
            ))
            ->addOrderBy($submitterField, $direction);
    }

    private function orderByExternId(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $direction): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $parentAlias = QueryBuilderHelper::addJoinOnce($queryBuilder, $queryNameGenerator, $rootAlias, self::PARENT_STATEMENT_ASSOCIATION, Join::LEFT_JOIN);

        $parentLengthField = $queryNameGenerator->generateParameterName('parentExternIdLength');
        $segmentLengthField = $queryNameGenerator->generateParameterName('externIdLength');
        $queryBuilder
            ->addSelect(sprintf('LENGTH(%s.externId) AS HIDDEN %s', $parentAlias, $parentLengthField))
            ->addSelect(sprintf('LENGTH(%s.externId) AS HIDDEN %s', $rootAlias, $segmentLengthField))
            ->addOrderBy($parentLengthField, $direction)
            ->addOrderBy(sprintf('%s.externId', $parentAlias), $direction)
            ->addOrderBy($segmentLengthField, $direction)
            ->addOrderBy(sprintf('%s.externId', $rootAlias), $direction);
    }

    public function getDescription(string $resourceClass): array
    {
        $schema = ['type' => 'string', 'enum' => self::DIRECTIONS];

        return [
            sprintf('%s[%s]', self::ORDER_PARAMETER_NAME, self::PROPERTY_SUBMITTER) => [
                'property'    => self::PROPERTY_SUBMITTER,
                'type'        => 'string',
                'required'    => false,
                'schema'      => $schema,
                'description' => 'Sort by the submitter shown in the list: parent statement author name, falling back to submit name, then organisation name.',
            ],
            sprintf('%s[%s]', self::ORDER_PARAMETER_NAME, self::PROPERTY_EXTERN_ID) => [
                'property'    => self::PROPERTY_EXTERN_ID,
                'type'        => 'string',
                'required'    => false,
                'schema'      => $schema,
                'description' => 'Sort by segment ID in natural order (parent statement ID, then segment ID; each by length, then value).',
            ],
        ];
    }
}
