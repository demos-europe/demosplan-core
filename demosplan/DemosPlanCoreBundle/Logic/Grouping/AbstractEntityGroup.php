<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Grouping;

use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;

/**
 * @template T of \demosplan\DemosPlanCoreBundle\Entity\CoreEntity
 *
 * @template-implements EntityGroupInterface<T>
 */
class AbstractEntityGroup implements EntityGroupInterface
{
    /**
     * @var array<int|string,T>
     */
    private $entries;

    /**
     * @var array<int|string,EntityGroupInterface<T>>
     */
    private $subgroups;

    /**
     * @var string
     */
    private $title;

    /**
     * @var int
     */
    private $level;

    public function __construct(string $title = '')
    {
        $this->entries = [];
        $this->subgroups = [];
        $this->level = 0;
        $this->title = $title;
    }

    public function setSubgroups(array $subgroups): void
    {
        $this->subgroups = $subgroups;
    }

    public function getSubgroups(): array
    {
        return $this->subgroups;
    }

    public function getSubgroup($key): ?EntityGroupInterface
    {
        return $this->subgroups[$key] ?? null;
    }

    /**
     * @param string|int                                  $key
     * @param EntityGroupInterface<T>|AbstractEntityGroup $subgroup
     */
    public function setSubgroup($key, EntityGroupInterface $subgroup): void
    {
        if ([] !== $subgroup->subgroups) {
            throw new NotYetImplementedException('If needed, re-calculation of subgroup levels must be implemented.');
        }

        $this->subgroups[$key] = $subgroup;

        $subgroup->level = $this->level + 1;
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string|int $key
     * @param T          $entry
     */
    public function setEntry($key, $entry): void
    {
        $this->entries[$key] = $entry;
    }

    /**
     * @param array<int|string,T> $entries
     */
    public function setEntries(array $entries): void
    {
        $this->entries = $entries;
    }

    public function getTotal(): int
    {
        return array_reduce($this->subgroups, static function (int $carry, EntityGroupInterface $group): int {
            return $carry + $group->getTotal();
        }, count($this->entries));
    }

    public function getLevel(): int
    {
        return $this->level;
    }
}
