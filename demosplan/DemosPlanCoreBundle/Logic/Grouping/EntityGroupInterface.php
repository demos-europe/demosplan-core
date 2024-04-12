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

/**
 * Instances of this class represent a generic, nestable group. It contains a number of subgroups and
 * a number of entries. Subgroups are of the same type as this, while entries can be of any type.
 *
 * @template T of \demosplan\DemosPlanCoreBundle\Entity\CoreEntity
 */
interface EntityGroupInterface
{
    /**
     * @return array<int|string, T>
     */
    public function getEntries(): array;

    /**
     * @param array<int|string,T> $entries
     * @return void
     */
    public function setEntries(array $entries): void;

    /**
     * @param string|int $key
     * @param T          $entry
     */
    public function setEntry($key, $entry): void;

    /**
     * @param string|int              $key
     * @param EntityGroupInterface<T> $subgroup
     */
    public function setSubgroup($key, EntityGroupInterface $subgroup): void;

    /**
     * @param string|int $key
     *
     * @return EntityGroupInterface<T>|null
     */
    public function getSubgroup($key): ?EntityGroupInterface;

    /**
     * @return array<int|string,EntityGroupInterface<T>>
     */
    public function getSubgroups(): array;

    /**
     * @param array<int|string, EntityGroupInterface<T>> $subgroups
     */
    public function setSubgroups(array $subgroups): void;

    public function getTitle(): string;

    /**
     * Get the total element count from this and all subgroups.
     */
    public function getTotal(): int;

    /**
     * Get the level (nesting depth) from this instance.
     */
    public function getLevel(): int;
}
