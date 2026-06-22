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

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class CustomFieldFilterResolver
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Returns IDs of entities whose customFields JSON matches ALL given field/value pairs.
     * AND logic across fields, OR logic across multiple values within one field.
     *
     * @param array<string, list<string>> $fieldFilters fieldId → [optionId, …]
     *
     * @return list<string>
     */
    public function resolveMatchingIds(
        CustomFieldSupportedEntity $entity,
        string $procedureId,
        array $fieldFilters,
    ): array {
        if ([] === $fieldFilters) {
            return [];
        }

        [$alias, $entityClass, $procedureExpr, $extraConditions] = $this->entityConfig($entity);

        $dql = "SELECT {$alias}.id FROM {$entityClass} {$alias} WHERE {$procedureExpr} = :procedureId";

        foreach ($extraConditions as $condition) {
            $dql .= " AND {$condition}";
        }

        $params = ['procedureId' => $procedureId];
        $fieldIdx = 0;

        foreach ($fieldFilters as $fieldId => $values) {
            $clauses = [];
            foreach ($values as $valIdx => $value) {
                $idParam = "cf{$fieldIdx}id";
                $valParam = "cf{$fieldIdx}v{$valIdx}";
                $clauses[] = "JSON_CONTAINS_CUSTOM_FIELD({$alias}.customFields, :{$idParam}, :{$valParam}) = 1";
                $params[$idParam] = $fieldId;
                $params[$valParam] = $value;
            }
            $dql .= ' AND ('.implode(' OR ', $clauses).')';
            ++$fieldIdx;
        }

        $query = $this->entityManager->createQuery($dql);
        foreach ($params as $key => $value) {
            $query->setParameter($key, $value);
        }

        return array_column($query->getArrayResult(), 'id');
    }

    /**
     * @return array{0: string, 1: class-string, 2: string, 3: list<string>}
     *                                                                       [alias, FQCN, procedure DQL expression, extra WHERE conditions]
     */
    private function entityConfig(CustomFieldSupportedEntity $entity): array
    {
        return match ($entity) {
            CustomFieldSupportedEntity::statement => [
                's',
                Statement::class,
                's.procedure',
                ['s.deleted = false', 's.original IS NOT NULL'],
            ],
            CustomFieldSupportedEntity::segment => [
                'seg',
                Segment::class,
                'seg.procedure',
                ['seg.deleted = false'],
            ],
            default => throw new InvalidArgumentException("Entity '{$entity->value}' does not support custom field ID resolution"),
        };
    }
}
