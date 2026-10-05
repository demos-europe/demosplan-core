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
 * `order[externId]`: sorts segments by their ID in natural order.
 *
 * Segment IDs look like "M12-3", so a plain string sort would put "M10-1" before "M2-1" and
 * "M1-10" before "M1-2". Ordering by length before value, first on the parent statement's ID and
 * then on the segment's own ID, yields the order a user expects.
 */
final class ExternIdNaturalOrderFilter implements FilterInterface
{
    public const ORDER_PARAMETER_NAME = 'order';
    public const PROPERTY = 'externId';

    private const DIRECTIONS = ['asc', 'desc'];

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $value = $context['filters'][self::ORDER_PARAMETER_NAME][self::PROPERTY] ?? null;
        $direction = is_string($value) ? strtolower($value) : null;
        if (!in_array($direction, self::DIRECTIONS, true)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $parentAlias = QueryBuilderHelper::addJoinOnce($queryBuilder, $queryNameGenerator, $rootAlias, 'parentStatementOfSegment', Join::LEFT_JOIN);
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
        return [
            sprintf('%s[%s]', self::ORDER_PARAMETER_NAME, self::PROPERTY) => [
                'property'    => self::PROPERTY,
                'type'        => 'string',
                'required'    => false,
                'schema'      => ['type' => 'string', 'enum' => self::DIRECTIONS],
                'description' => 'Sort by segment ID in natural order (parent statement ID, then segment ID; each by length, then value).',
            ],
        ];
    }
}
