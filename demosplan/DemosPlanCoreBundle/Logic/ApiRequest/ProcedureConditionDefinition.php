<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use Carbon\Carbon;
use EDT\DqlQuerying\Functions\Constant;
use EDT\DqlQuerying\Functions\Property;
use EDT\DqlQuerying\Functions\Smaller;
use EDT\Querying\Contracts\PropertyPathAccessInterface;
use EDT\Querying\FluentQueries\ConditionDefinition;
use EDT\Querying\PropertyPaths\PropertyPath;

class ProcedureConditionDefinition extends ConditionDefinition
{
    /**
     * @return $this
     */
    public function propertyHasValueBeforeNow(string $property, string ...$properties): ConditionDefinition
    {
        $propertyPath = new PropertyPath(null, '', PropertyPathAccessInterface::DIRECT, $property, ...$properties);
        $now = new Constant(Carbon::now(), 'CURRENT_TIMESTAMP()');

        $this->conditions[] = new Smaller(
            new Property($propertyPath),
            $now
        );

        return $this;
    }

    /**
     * @return ProcedureConditionDefinition
     */
    public function anyConditionApplies(): ConditionDefinition
    {
        $subDefinition = new self($this->conditionFactory, false);
        $this->subDefinitions[] = $subDefinition;

        return $subDefinition;
    }

    /**
     * @return ProcedureConditionDefinition
     */
    public function allConditionsApply(): ConditionDefinition
    {
        $subDefinition = new self($this->conditionFactory, true);
        $this->subDefinitions[] = $subDefinition;

        return $subDefinition;
    }
}
