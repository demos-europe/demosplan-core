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
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use Doctrine\ORM\EntityManagerInterface;

class CustomFieldStatementCounter
{
    public function __construct(
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Returns statement counts per option for a single custom field.
     * Optionally scoped to statements matching other active CF filters (facet awareness).
     *
     * @param array<string, string[]> $otherCfFilters fieldId => selectedOptionIds[]
     * @param string[]|null           $esFilteredIds  Statement IDs that matched the active regular ES
     *                                                 filters. `null` means "don't scope" (count across
     *                                                 all statements); an empty array means the active
     *                                                 regular filters matched nothing, so every option
     *                                                 count must be 0.
     *
     * @return array<string, int> optionId => count
     */
    public function countByField(
        string $procedureId,
        string $fieldId,
        bool $isOriginalStatementView,
        array $otherCfFilters = [],
        ?array $esFilteredIds = null,
    ): array {
        $configs = $this->customFieldConfigurationRepository->findCustomFieldConfigurationByCriteria(
            CustomFieldSupportedEntity::procedure->value,
            $procedureId,
            CustomFieldSupportedEntity::statement->value,
            $fieldId
        );

        if (null === $configs || [] === $configs) {
            return [];
        }

        $field = $configs[0]->getConfiguration();
        $field->setId($configs[0]->getId());

        if ([] === $field->getOptions()) {
            return [];
        }

        if (null !== $esFilteredIds && [] === $esFilteredIds) {
            // Active regular filters matched no statements — every option necessarily has zero.
            return array_fill_keys(
                array_map(static fn ($option) => $option->getId(), $field->getOptions()),
                0
            );
        }

        $counts = [];
        foreach ($field->getOptions() as $option) {
            $counts[$option->getId()] = $this->countForOption(
                $procedureId,
                $fieldId,
                $option->getId(),
                $isOriginalStatementView,
                $otherCfFilters,
                $esFilteredIds,
            );
        }

        return $counts;
    }

    /**
     * @param array<string, string[]> $otherCfFilters
     * @param string[]|null           $esFilteredIds
     */
    private function countForOption(
        string $procedureId,
        string $fieldId,
        string $optionValue,
        bool $isOriginalStatementView,
        array $otherCfFilters,
        ?array $esFilteredIds = null,
    ): int {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Statement::class, 's')
            ->andWhere('s.procedure = :procedureId')
            ->andWhere('s.deleted = false')
            ->andWhere($isOriginalStatementView ? 's.original IS NULL' : 's.original IS NOT NULL')
            ->andWhere('JSON_CONTAINS_CUSTOM_FIELD(s.customFields, :fieldId, :optionValue) = 1')
            ->setParameter('procedureId', $procedureId)
            ->setParameter('fieldId', $fieldId)
            ->setParameter('optionValue', $optionValue);

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

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
