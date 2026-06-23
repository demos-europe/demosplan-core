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
use Elastica\Query\AbstractQuery;
use Elastica\Query\MatchNone;
use Illuminate\Support\Collection;

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
        $prefix = 'customField_';

        /** @var Collection<string, mixed> $customFieldEntries */
        /** @var Collection<string, mixed> $remainingFilters */
        [$customFieldEntries, $remainingFilters] = collect($userFilters)->partition(
            static fn (mixed $value, string $key): bool => str_starts_with($key, $prefix)
        );

        if ($customFieldEntries->isEmpty()) {
            return [null, $userFilters];
        }

        $fieldFilters = $customFieldEntries
            ->mapWithKeys(
                static fn (mixed $value, string $key): array => [substr($key, strlen($prefix)) => $value]
            )
            ->toArray();

        $qb = $this->entityManager->createQueryBuilder()
            ->select('s.id')
            ->from(Statement::class, 's')
            ->andWhere('s.procedure = :procedureId')
            ->andWhere('s.deleted = false')
            ->andWhere('s.original IS NOT NULL')
            ->setParameter('procedureId', $procedureId);

        foreach (array_keys($fieldFilters) as $fieldIdx => $fieldId) {
            $orClauses = [];
            foreach ($fieldFilters[$fieldId] as $valIdx => $value) {
                $idParam = "cf{$fieldIdx}id";
                $valParam = "cf{$fieldIdx}v{$valIdx}";
                $orClauses[] = "JSON_CONTAINS_CUSTOM_FIELD(s.customFields, :{$idParam}, :{$valParam}) = 1";
                $qb->setParameter($idParam, $fieldId);
                $qb->setParameter($valParam, $value);
            }
            $qb->andWhere($qb->expr()->orX(...$orClauses));
        }

        $matchingIds = array_column($qb->getQuery()->getArrayResult(), 'id');

        if ([] === $matchingIds) {
            return [new MatchNone(), $remainingFilters->toArray()];
        }

        return [
            $this->elasticSearchService->getElasticaTermsInstance('id', $matchingIds),
            $remainingFilters->toArray(),
        ];
    }
}
