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
use Doctrine\ORM\EntityManagerInterface;

class CustomFieldStatementCounter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Returns statement counts per option for a single custom field.
     * Optionally scoped to statements matching other active CF filters (facet awareness).
     *
     * @param string[]                $optionIds      option IDs of the field, as already known by the caller
     * @param array<string, string[]> $otherCfFilters fieldId => selectedOptionIds[]
     * @param string[]|null           $esFilteredIds  Statement IDs that matched the active regular ES
     *                                                filters. `null` means "don't scope" (count across
     *                                                all statements); an empty array means the active
     *                                                regular filters matched nothing, so every option
     *                                                count must be 0.
     *
     * @return array<string, int> optionId => count
     */
    public function countByField(
        string $procedureId,
        string $fieldId,
        array $optionIds,
        bool $isOriginalStatementView,
        array $otherCfFilters = [],
        ?array $esFilteredIds = null,
    ): array {
        if ([] === $optionIds) {
            return [];
        }

        if (null !== $esFilteredIds && [] === $esFilteredIds) {
            // Active regular filters matched no statements — every option necessarily has zero.
            return array_fill_keys($optionIds, 0);
        }

        return $this->countAllOptions(
            $procedureId,
            $fieldId,
            $optionIds,
            $isOriginalStatementView,
            $otherCfFilters,
            $esFilteredIds,
        );
    }

    /**
     * Counts, in a single query, how many statements match each of $optionIds for $fieldId —
     * one SUM(CASE WHEN ...) column per option instead of one COUNT query per option.
     *
     * @param string[]                $optionIds
     * @param array<string, string[]> $otherCfFilters
     * @param string[]|null           $esFilteredIds
     *
     * @return array<string, int> optionId => count
     */
    private function countAllOptions(
        string $procedureId,
        string $fieldId,
        array $optionIds,
        bool $isOriginalStatementView,
        array $otherCfFilters,
        ?array $esFilteredIds = null,
    ): array {
        $qb = $this->entityManager->createQueryBuilder()
            ->from(Statement::class, 's')
            ->andWhere('s.procedure = :procedureId')
            ->andWhere('s.deleted = false')
            ->andWhere($isOriginalStatementView ? 's.original IS NULL' : 's.original IS NOT NULL')
            ->setParameter('procedureId', $procedureId)
            ->setParameter('fieldId', $fieldId);

        $selectExpressions = [];
        foreach ($optionIds as $optIdx => $optionId) {
            $valParam = "optv{$optIdx}";
            $selectExpressions[] = "SUM(CASE WHEN JSON_CONTAINS_CUSTOM_FIELD(s.customFields, :fieldId, :{$valParam}) = 1 THEN 1 ELSE 0 END) AS cnt{$optIdx}";
            $qb->setParameter($valParam, $optionId);
        }
        $qb->select(implode(', ', $selectExpressions));

        if (null !== $esFilteredIds) {
            $qb->andWhere('s.id IN (:esFilteredIds)')
               ->setParameter('esFilteredIds', $esFilteredIds);
        }

        $idx = 0;
        foreach ($otherCfFilters as $otherFieldId => $selectedOptionIds) {
            $orClauses = [];
            foreach ($selectedOptionIds as $valIdx => $selectedOptionId) {
                $idParam = "ocf{$idx}id";
                $valParam = "ocf{$idx}v{$valIdx}";
                $orClauses[] = "JSON_CONTAINS_CUSTOM_FIELD(s.customFields, :{$idParam}, :{$valParam}) = 1";
                $qb->setParameter($idParam, $otherFieldId)
                   ->setParameter($valParam, $selectedOptionId);
            }
            $qb->andWhere($qb->expr()->orX(...$orClauses));
            ++$idx;
        }

        $row = $qb->getQuery()->getArrayResult()[0] ?? [];

        $counts = [];
        foreach ($optionIds as $optIdx => $optionId) {
            $counts[$optionId] = (int) ($row["cnt{$optIdx}"] ?? 0);
        }

        return $counts;
    }
}
