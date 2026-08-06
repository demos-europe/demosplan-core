<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Entity\SortableInterface;
use demosplan\DemosPlanCoreBundle\Logic\ReorderEntityListByInteger;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Reorders the non-configuration phases of a single audience, given an already loaded,
 * ascending-by-orderInAudience collection. Kept free of any EDT/ResourceType dependency
 * so the index math can be unit tested without mocking a final class.
 *
 * {@link ReorderEntityListByInteger} was written for collections whose sortIndex starts
 * at 0 (e.g. {@link \demosplan\DemosPlanCoreBundle\Entity\Workflow\Place}). Its only
 * compensation for a non-zero-based collection is shifting an incoming index that is
 * *below* the collection's lowest value - which only helps moves toward the front.
 * Moving an item to any other position (including the very end) would silently land one
 * slot short, or throw a false "already has the desired index" error, if the raw
 * frontend index were passed through unmodified. To avoid relying on that partial
 * self-correction (and to avoid the collection's absolute values drifting away from a
 * small range across repeated reorders - risking a regular phase ending up with
 * orderInAudience 0 and being mistaken for the configuration phase), the given scope is
 * renumbered to a clean 1..N immediately before every reorder, and the frontend's 0-based
 * newIndex is translated with a fixed +1.
 */
class ProcedurePhaseDefinitionAudienceReorderer
{
    /**
     * @param Collection<int, ProcedurePhaseDefinition> $phasesOfAudience ascending by orderInAudience, excluding the configuration phase
     */
    public function reorder(string $movedPhaseId, int $newIndex, Collection $phasesOfAudience): void
    {
        $renumberedPhases = $this->renumberStartingAtOne($phasesOfAudience);

        $listReorder = new ReorderEntityListByInteger($newIndex + 1, $movedPhaseId, $renumberedPhases);
        $listReorder->reorderEntityList();
    }

    /**
     * Renumbers the given (already ascending) phases to a clean 1..N sequence, preserving
     * their relative order.
     *
     * @param Collection<int, ProcedurePhaseDefinition> $phases
     *
     * @return Collection<int, SortableInterface> keyed by the freshly assigned orderInAudience
     */
    private function renumberStartingAtOne(Collection $phases): Collection
    {
        $result = new ArrayCollection();
        $index = 1;
        foreach ($phases as $phase) {
            $phase->setOrderInAudience($index);
            $result->set($index, $phase);
            ++$index;
        }

        return $result;
    }
}
