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
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Changes the position number of a phase, and updates the other phases' numbers so
 * they stay in order, one after another.
 * The configuration phase always keeps position 0, so before moving anything this class
 * renumbers the other phases starting at 1.
 * That way, no other phase can end up with position 0 and be confused with the
 * configuration phase.
 */
class ProcedurePhaseDefinitionAudienceReorderer
{
    /**
     * @param Collection<int, ProcedurePhaseDefinition> $phasesOfAudience ascending by orderInAudience, excluding the configuration phase
     */
    public function reorder(string $movedPhaseId, int $newIndex, Collection $phasesOfAudience): void
    {
        $count = $phasesOfAudience->count();
        if (0 === $count || $newIndex < 0 || $newIndex > $count - 1) {
            throw new InvalidArgumentException('newIndex is out of range for the given audience phases');
        }

        $renumberedPhases = $this->renumberStartingAtOne($phasesOfAudience);
        $this->moveToPosition($movedPhaseId, $newIndex + 1, $renumberedPhases);
    }

    /**
     * Gives each phase a new number starting from 1, keeping them in the same order.
     * This removes any gaps or duplicate numbers before we move a phase.
     *
     * @param Collection<int, ProcedurePhaseDefinition> $phases
     *
     * @return Collection<int, ProcedurePhaseDefinition> keyed by the freshly assigned orderInAudience
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

    /**
     * Moves the given phase to its new position, shifting only the phases in between
     * by one to close the gap it leaves and to make room at the target position.
     * Every phase stays within the 1..count range - no other numbers are touched.
     *
     * @param Collection<int, ProcedurePhaseDefinition> $renumberedPhases keyed by orderInAudience, values already 1..count
     */
    private function moveToPosition(string $movedPhaseId, int $newPosition, Collection $renumberedPhases): void
    {
        $movedPhase = $renumberedPhases->filter(
            static fn (ProcedurePhaseDefinition $phase): bool => $phase->getId() === $movedPhaseId
        )->first();

        $oldPosition = $movedPhase->getOrderInAudience();
        if ($oldPosition === $newPosition) {
            throw new InvalidArgumentException('The requested place already has the desired index - there is nothing to change');
        }

        foreach ($renumberedPhases as $phase) {
            if ($phase === $movedPhase) {
                continue;
            }

            $position = $phase->getOrderInAudience();
            if ($newPosition < $oldPosition && $position >= $newPosition && $position < $oldPosition) {
                $phase->setOrderInAudience($position + 1);
            } elseif ($newPosition > $oldPosition && $position > $oldPosition && $position <= $newPosition) {
                $phase->setOrderInAudience($position - 1);
            }
        }

        $movedPhase->setOrderInAudience($newPosition);
    }
}
