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
use Exception;
use ReflectionClass;

class PostProcedureUpdatedEvent extends DPlanEvent implements PostProcedureUpdatedEventInterface
{
    public function __construct(
        readonly protected Procedure $procedureBeforeUpdate,
        readonly protected Procedure $procedureAfterUpdate,
        private array $fieldsNotPresentInNewProcedure = [],
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

    /** Some properties might not exist for both objects (old and new) and can not be compared - of Proxy as example
     * this method provides access to those properties to be able to check them manually.
     */
    public function getPropertiesFailedToCompare(): array
    {
        return $this->fieldsNotPresentInNewProcedure;
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
            } catch (Exception $e) {
                // The property can not be accessed or does not exist within newObject
                // store it and continue with other properties
                $this->fieldsNotPresentInNewProcedure[$propertyName] = ['old' => $oldObject, 'new' => $newObject];

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
