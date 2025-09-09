<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use demosplan\DemosPlanCoreBundle\Event\Procedure\EventConcern;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Exception;

use function array_key_exists;
use function is_array;

trait EventConcernTrait
{
    /**
     * @var array<string, array<int, EventConcern>>
     */
    protected $criticalEventConcerns = [];

    public function addCriticalEventConcern(string $key, EventConcern $eventConcern)
    {
        $criticalEventConcerns = $this->getCriticalEventConcerns();
        if (array_key_exists($key, $criticalEventConcerns)) {
            if (!is_array($criticalEventConcerns[$key])) {
                throw new InvalidArgumentException(sprintf('value key already exists and is not an array: %s', $key));
            }
        } else {
            $criticalEventConcerns[$key] = [];
        }
        $criticalEventConcerns[$key][] = $eventConcern;
        $this->criticalEventConcerns = $criticalEventConcerns;
    }

    public function getCriticalEventConcernMessages(): array
    {
        $messages = [];
        foreach ($this->criticalEventConcerns as $criticalEventConcerns) {
            foreach ($criticalEventConcerns as $criticalEventConcern) {
                $messages[] = $criticalEventConcern->getMessage();
            }
        }

        return $messages;
    }

    /**
     * @return array<string, array<int, EventConcern>>
     */
    public function getCriticalEventConcerns(): array
    {
        return $this->criticalEventConcerns;
    }

    public function hasCriticalEventConcerns(): bool
    {
        foreach ($this->criticalEventConcerns as $criticalEventConcern) {
            if ([] !== $criticalEventConcern) {
                return true;
            }
        }

        return false;
    }

    public function addCriticalConcern(string $key, string $eventConcernText, Exception $e): void
    {
        $eventConcern = new EventConcern($eventConcernText, $e);
        $this->addCriticalEventConcern($key, $eventConcern);
    }
}
