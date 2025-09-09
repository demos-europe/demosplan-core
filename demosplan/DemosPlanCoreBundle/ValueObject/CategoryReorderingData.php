<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use Doctrine\Common\Collections\Collection;
use InvalidArgumentException;

/**
 * @method Collection    getNewNeighbors()
 * @method Elements|null getNewParent()
 * @method Elements      getMoveTarget()
 * @method Elements|null getPreviousParent()
 * @method Collection    getPreviousNeighbors()
 * @method int           getPreviousIndex()
 * @method int           getNewIndex()
 */
class CategoryReorderingData extends ValueObject
{
    /**
     * @var Collection<int, Elements>
     */
    protected $newNeighbors;

    /**
     * @var Elements|null
     */
    protected $newParent;

    /**
     * @var Elements
     */
    protected $moveTarget;

    /**
     * @var Elements|null
     */
    protected $previousParent;

    /**
     * @var Collection<int, Elements>
     */
    protected $previousNeighbors;

    /**
     * @var int
     */
    protected $previousIndex;

    /**
     * @var int
     */
    protected $newIndex;

    /**
     * @param Collection<int, Elements> $newNeighbors
     * @param Collection<int, Elements> $previousNeighbors
     */
    public function __construct(
        Elements $moveTarget,
        ?Elements $newParent,
        Collection $newNeighbors,
        ?Elements $previousParent,
        Collection $previousNeighbors,
        int $newIndex
    ) {
        if ($moveTarget === $newParent) {
            throw new InvalidArgumentException('Category to move and the parent it is to be moved into are identical.');
        }

        $this->moveTarget = $moveTarget;
        $this->newParent = $newParent;
        $this->newNeighbors = $newNeighbors;
        $this->previousParent = $previousParent;
        $this->previousNeighbors = $previousNeighbors;
        $this->previousIndex = $previousNeighbors->indexOf($moveTarget);
        $this->newIndex = $newIndex;
        $this->lock();
    }
}
