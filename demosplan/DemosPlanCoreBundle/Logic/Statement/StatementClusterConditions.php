<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Contracts\ClauseFunctionInterface;
use EDT\Querying\Contracts\SortMethodInterface;

/**
 * Encapsulates the domain rules that identify a valid cluster head Statement.
 * Mirrors ClusterStatementResourceType::getAccessConditions() using plain string-array
 * property paths instead of EDT path objects.
 */
class StatementClusterConditions
{
    public function __construct(
        private readonly DqlConditionFactory $conditionFactory,
        private readonly SortMethodFactory $sortMethodFactory,
    ) {
    }

    /**
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function forProcedure(string $procedureId): array
    {
        return [
            $this->conditionFactory->propertyIsNotNull(['original']),
            $this->conditionFactory->propertyHasValue(true, ['clusterStatement']),
            $this->conditionFactory->propertyHasValue(false, ['deleted']),
            $this->conditionFactory->propertyIsNull(['headStatement']),
            $this->conditionFactory->propertyIsNull(['movedStatement']),
            $this->conditionFactory->propertyHasValue($procedureId, ['procedure', 'id']),
        ];
    }

    /**
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function forProcedureById(string $procedureId, string $id): array
    {
        return array_merge(
            $this->forProcedure($procedureId),
            [$this->conditionFactory->propertyHasValue($id, ['id'])]
        );
    }

    /**
     * @return list<SortMethodInterface>
     */
    public function defaultSortMethods(): array
    {
        return [$this->sortMethodFactory->propertyAscending(['externId'])];
    }
}
