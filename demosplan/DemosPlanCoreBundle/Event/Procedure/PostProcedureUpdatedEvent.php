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
use DemosEurope\DemosplanAddon\Contracts\Entities\SlugInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\PostProcedureUpdatedEventInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Slug;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use ReflectionClass;

class PostProcedureUpdatedEvent extends DPlanEvent implements PostProcedureUpdatedEventInterface
{
    public function __construct(
        readonly protected Procedure $procedureBeforeUpdate,
        readonly protected Procedure $procedureAfterUpdate,
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
        if ($oldObject instanceof Slug && $newObject instanceof Slug) {
            return $this->handleSlugRelation($oldObject, $newObject);
        }

        $reflectionClass = new ReflectionClass($oldObject);
        $properties = $reflectionClass->getProperties();

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            // skip self references
            if ('procedure' === $propertyName) {
                continue;
            }

            $oldValue = $property->getValue($oldObject);
            $newValue = $property->getValue($newObject);

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

    /** The Slugs need special treatment as the newly added Slug is not a proxy like the others and does not
     * support access to doctrine proxy properties
     */
    private function handleSlugRelation(SlugInterface $oldSlug, SlugInterface $newSlug): array
    {
        $modifiedValues = [];
        if ($oldSlug->getId() !== $newSlug->getId()) {
            $modifiedValues['id'] = ['old' => $oldSlug->getId(), 'new' => $newSlug->getId()];
        }
        if ($oldSlug->getName() !== $newSlug->getName()) {
            $modifiedValues['name'] = ['old' => $oldSlug->getName(), 'new' => $newSlug->getName()];
        }

        return $modifiedValues;
    }
}
