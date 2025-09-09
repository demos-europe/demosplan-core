<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use EDT\DqlQuerying\Functions\InvertedBoolean;
use EDT\Querying\FluentQueries\ConditionDefinition;

class StatementConditionDefinition extends ConditionDefinition
{
    /**
     * @return $this
     */
    public function originalStatement(): self
    {
        return $this->propertyIsNull(['original']);
    }

    /**
     * @return $this
     */
    public function isNonOriginal(): self
    {
        return $this->propertyIsNotNull(['original']);
    }

    public function notClusterRelated(): self
    {
        return $this->allConditionsApply()
            ->propertyHasValue(false, ['clusterStatement'])
            ->propertyIsNull(['headStatement'])
            ->propertyHasSize(0, ['cluster']);
    }

    /**
     * @return $this
     */
    public function hasSegments(string $procedureId): self
    {
        return $this->add(new HasSegmentsClause($procedureId));
    }

    /**
     * @return $this
     */
    public function hasNoSegments(string $procedureId): self
    {
        return $this->add(new InvertedBoolean(new HasSegmentsClause($procedureId)));
    }

    /**
     * @return $this
     */
    public function inProcedureWithId(string $procedureId): self
    {
        return $this->propertyHasValue($procedureId, ['procedure', 'id']);
    }

    /**
     * @return $this
     */
    public function assignedToUser(User $assignee): self
    {
        return $this->propertyHasValue($assignee->getId(), ['assignee', 'id']);
    }

    public function unassigned(): self
    {
        return $this->propertyIsNull(['assignee']);
    }

    /**
     * @return StatementConditionDefinition
     */
    public function anyConditionApplies(): ConditionDefinition
    {
        $subDefinition = new self($this->conditionFactory, false);
        $this->subDefinitions[] = $subDefinition;

        return $subDefinition;
    }

    /**
     * @return StatementConditionDefinition
     */
    public function allConditionsApply(): ConditionDefinition
    {
        $subDefinition = new self($this->conditionFactory, true);
        $this->subDefinitions[] = $subDefinition;

        return $subDefinition;
    }
}
