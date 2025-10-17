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

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\DqlConditionDefinition;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Functions\Constant;
use EDT\DqlQuerying\Functions\Greater;
use EDT\DqlQuerying\Functions\Property;
use EDT\DqlQuerying\Functions\Smaller;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\PropertyPathAccessInterface;
use EDT\Querying\PropertyPaths\PropertyPath;

class ProcedureConditionDefinition extends DqlConditionDefinition
{
    /**
     * @param non-empty-list<non-empty-string> $properties
     *
     * @return $this
     *
     * @throws PathException
     */
    public function propertyHasValueBeforeNow(array $properties): DqlConditionDefinition
    {
        return $this->addDqlCondition(new Smaller(
            $this->createDirectProperty($properties),
            $this->createNowConstant()
        ));
    }

    /**
     * @param non-empty-list<non-empty-string> $properties
     *
     * @return $this
     *
     * @throws PathException
     */
    public function propertyHasValueAfterNow(array $properties): DqlConditionDefinition
    {
        return $this->addDqlCondition(new Greater(
            $this->createDirectProperty($properties),
            $this->createNowConstant()
        ));
    }

    /**
     * @param non-empty-list<non-empty-string> $properties
     *
     * @throws PathException
     */
    protected function createDirectProperty(array $properties): Property
    {
        $propertyPath = new PropertyPath(
            null,
            '',
            PropertyPathAccessInterface::DIRECT,
            $properties
        );

        return new Property($propertyPath);
    }

    protected function createNowConstant(): Constant
    {
        return new Constant(Carbon::now(), 'CURRENT_TIMESTAMP()');
    }

    /**
     * @return ProcedureConditionDefinition
     */
    public function anyConditionApplies(): DqlConditionDefinition
    {
        $subDefinition = new self($this->conditionFactory, false);
        $this->subDefinitions[] = $subDefinition;

        return $subDefinition;
    }

    /**
     * @return ProcedureConditionDefinition
     */
    public function allConditionsApply(): DqlConditionDefinition
    {
        $subDefinition = new self($this->conditionFactory, true);
        $this->subDefinitions[] = $subDefinition;

        return $subDefinition;
    }
}
