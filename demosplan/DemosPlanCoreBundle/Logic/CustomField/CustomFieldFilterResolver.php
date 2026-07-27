<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\CustomField;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Statement\ElasticSearchService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Elastica\Query\AbstractQuery;
use Elastica\Query\MatchNone;

class CustomFieldFilterResolver
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ElasticSearchService $elasticSearchService,
    ) {
    }

    /**
     * Strips customField_* entries from $userFilters, resolves matching statement
     * IDs via Doctrine and returns the Elastica terms filter alongside the cleaned filters array.
     *
     * @param array<string, mixed> $userFilters
     *
     * @return array{0: AbstractQuery|null, 1: array<string, mixed>}
     */
    public function resolveCustomFieldFilter(string $procedureId, array $userFilters): array
    {
        $fieldFilters = $this->extractActiveCfFilters($userFilters);

        if ([] === $fieldFilters) {
            return [null, $userFilters];
        }

        $remainingFilters = collect($userFilters)->reject(
            static fn (mixed $value, string $key): bool => str_starts_with($key, 'customField_')
        );

        $isOriginalStatementView = 'IS NULL' === $remainingFilters->get('original');

        $qb = $this->entityManager->createQueryBuilder()
            ->select('s.id')
            ->from(Statement::class, 's')
            ->andWhere('s.procedure = :procedureId')
            ->andWhere('s.deleted = false')
            ->andWhere($isOriginalStatementView ? 's.original IS NULL' : 's.original IS NOT NULL')
            ->setParameter('procedureId', $procedureId);

        $this->applyFieldConstraints($qb, $fieldFilters, 'cf');

        $matchingIds = array_column($qb->getQuery()->getArrayResult(), 'id');

        if ([] === $matchingIds) {
            return [new MatchNone(), $remainingFilters->toArray()];
        }

        return [
            $this->elasticSearchService->getElasticaTermsInstance('id', $matchingIds),
            $remainingFilters->toArray(),
        ];
    }

    /**
     * Returns CF field filters with real (non-sentinel) values only.
     * Empty-string sentinels posted when a dropdown opens are stripped so they
     * do not produce ES filter clauses.
     *
     * @param array<string, mixed> $userFilters
     *
     * @return array<string, string[]> fieldId => selected option IDs (never empty arrays)
     */
    public function extractActiveCfFilters(array $userFilters): array
    {
        $prefix = 'customField_';
        $active = [];

        foreach ($userFilters as $key => $values) {
            if (str_starts_with($key, $prefix)) {
                $nonEmpty = array_values(
                    array_filter((array) $values, static fn (string $v): bool => '' !== $v)
                );
                if ([] !== $nonEmpty) {
                    $active[substr($key, strlen($prefix))] = $nonEmpty;
                }
            }
        }

        return $active;
    }

    /**
     * ANDs across fields, ORs across a field's selected values, one JSON_CONTAINS_CUSTOM_FIELD
     * clause per value. $paramPrefix keeps bound parameter names unique when a caller combines
     * this with its own query parameters (e.g. per-option SUM(CASE) columns).
     *
     * @param array<string, string[]> $fieldFilters fieldId => selected option IDs
     */
    public function applyFieldConstraints(QueryBuilder $qb, array $fieldFilters, string $paramPrefix): void
    {
        foreach (array_keys($fieldFilters) as $fieldIdx => $fieldId) {
            $orClauses = [];
            foreach ($fieldFilters[$fieldId] as $valIdx => $value) {
                $idParam = "{$paramPrefix}{$fieldIdx}id";
                $valParam = "{$paramPrefix}{$fieldIdx}v{$valIdx}";
                $orClauses[] = "JSON_CONTAINS_CUSTOM_FIELD(s.customFields, :{$idParam}, :{$valParam}) = 1";
                $qb->setParameter($idParam, $fieldId);
                $qb->setParameter($valParam, $value);
            }
            $qb->andWhere($qb->expr()->orX(...$orClauses));
        }
    }
}
