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

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Events\PostProcedureUpdatedEventInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use ReflectionClass;
use Throwable;

class PostProcedureUpdatedEvent extends DPlanEvent implements PostProcedureUpdatedEventInterface
{
    public function __construct(
        protected readonly Procedure $procedureBeforeUpdate,
        protected readonly Procedure $procedureAfterUpdate,
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
     * Attention! This method will not discover newly added entities for a ToMany Collection relation.
     *
     * @return array<string, array<string, mixed>>
     */
    private function determineModifiedValues(object $oldObject, object $newObject, int $nestingLimit = 2): array
    {
        $modifiedValues = [];

        if ($oldObject instanceof DateTime && $newObject instanceof DateTime) {
            if ($oldObject->getTimestamp() !== $newObject->getTimestamp()) {
                $modifiedValues['old'] = $oldObject;
                $modifiedValues['new'] = $newObject;
            }

            return $modifiedValues;
        }

        $reflectionClass = new ReflectionClass($oldObject);
        $properties = $reflectionClass->getProperties();

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            // skip self references
            if ('procedure' === $propertyName) {
                continue;
            }

            try {
                $oldValue = $property->getValue($oldObject);
                $newValue = $property->getValue($newObject);
            } catch (Throwable) {
                // Skip properties that can not be compared: uninitialized typed properties
                // (e.g. Doctrine lazy proxies backed by Symfony's LazyObjectState, which throw
                // an Error on access), or properties not declared on both objects when old/new
                // are of different classes (ReflectionException).
                continue;
            }

            if ($oldValue !== $newValue) {
                if (is_object($oldValue) && is_object($newValue)) {
                    $modifiedSubValues = [];
                    if (0 < $nestingLimit) {
                        $modifiedSubValues = $this->determineModifiedValues(
                            $oldValue,
                            $newValue,
                            $nestingLimit - 1
                        );
                    }
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
