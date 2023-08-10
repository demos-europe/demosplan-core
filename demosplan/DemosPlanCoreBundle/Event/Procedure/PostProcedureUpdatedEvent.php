<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Events\PostProcedureUpdatedEventInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use ReflectionClass;

class PostProcedureUpdatedEvent extends DPlanEvent implements PostProcedureUpdatedEventInterface
{
    public function __construct(
        readonly protected Procedure $procedureBeforeUpdate,
        readonly protected Procedure $procedureAfterUpdate
    ) {
    }

    public function getProcedureBeforeUpdate(): Procedure
    {
        return $this->procedureBeforeUpdate;
    }

    public function getProcedureAfterUpdate(): Procedure
    {
        return $this->procedureAfterUpdate;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getModifiedValues(): array
    {
        return $this->determineModifiedValues($this->procedureBeforeUpdate, $this->procedureAfterUpdate);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function determineModifiedValues(object $oldObject, object $newObject): array
    {
        $modifiedValues = [];

        $reflectionClass = new ReflectionClass($oldObject);
        $properties = $reflectionClass->getProperties();

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            $oldValue = $property->getValue($oldObject);
            $newValue = $property->getValue($newObject);

            if ($oldValue !== $newValue) {
                if (is_object($oldValue) && is_object($newValue)) {
                    $modifiedSubValues = $this->determineModifiedValues($oldValue, $newValue);
                    if ([] !== $modifiedSubValues) {
                        $modifiedValues[$propertyName] = $modifiedSubValues;
                    }
                } else {
                    $modifiedValues[$propertyName] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                    ];
                }
            }
        }

        return $modifiedValues;
    }
}
