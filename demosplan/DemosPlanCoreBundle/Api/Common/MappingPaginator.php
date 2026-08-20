<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Common;

use ApiPlatform\State\Pagination\PaginatorInterface;

/**
 * Decorates an ApiPlatform PaginatorInterface, mapping each yielded item (e.g. a
 * Doctrine entity) through a callback (e.g. Resource::fromEntity), while forwarding
 * all pagination metadata untouched.
 *
 * @template TIn of object
 * @template TOut of object
 *
 * @implements PaginatorInterface<TOut>
 */
final class MappingPaginator implements \IteratorAggregate, PaginatorInterface
{
    /**
     * @param PaginatorInterface<TIn> $inner
     * @param \Closure(TIn): TOut     $map
     */
    public function __construct(
        private readonly PaginatorInterface $inner,
        private readonly \Closure $map,
    ) {
    }

    public function getCurrentPage(): float
    {
        return $this->inner->getCurrentPage();
    }

    public function getItemsPerPage(): float
    {
        return $this->inner->getItemsPerPage();
    }

    public function getLastPage(): float
    {
        return $this->inner->getLastPage();
    }

    public function getTotalItems(): float
    {
        return $this->inner->getTotalItems();
    }

    public function count(): int
    {
        return $this->inner->count();
    }

    public function getIterator(): \Traversable
    {
        foreach ($this->inner as $item) {
            yield ($this->map)($item);
        }
    }
}
