<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedurePhaseDefinitionAudienceReorderer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Base\UnitTestCase;

class ProcedurePhaseDefinitionAudienceReordererTest extends UnitTestCase
{
    private ?ProcedurePhaseDefinitionAudienceReorderer $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new ProcedurePhaseDefinitionAudienceReorderer();
    }

    private function createPhase(string $id, int $orderInAudience): ProcedurePhaseDefinition
    {
        $phase = new ProcedurePhaseDefinition();
        $phase->setId($id);
        $phase->setAudience('internal');
        $phase->setOrderInAudience($orderInAudience);

        return $phase;
    }

    /**
     * @param ProcedurePhaseDefinition[] $phases
     *
     * @return Collection<int, ProcedurePhaseDefinition>
     */
    private function toCollection(array $phases): Collection
    {
        $collection = new ArrayCollection();
        foreach ($phases as $phase) {
            $collection->set($phase->getOrderInAudience(), $phase);
        }

        return $collection;
    }

    private function assertOrder(array $expectedIds, array $phases): void
    {
        $sorted = $phases;
        usort($sorted, static fn (ProcedurePhaseDefinition $a, ProcedurePhaseDefinition $b) => $a->getOrderInAudience() <=> $b->getOrderInAudience());
        $actualIds = array_map(static fn (ProcedurePhaseDefinition $phase) => $phase->getId(), $sorted);

        self::assertSame($expectedIds, $actualIds);
    }

    #[DataProvider('moveWithinAudienceProvider')]
    public function testReorderMovesPhaseToExpectedPosition(int $movedPosition, int $newIndex, array $expectedOrder): void
    {
        $e1 = $this->createPhase('e1', 1);
        $e2 = $this->createPhase('e2', 2);
        $e3 = $this->createPhase('e3', 3);
        $phases = [$e1, $e2, $e3];
        $movedPhase = $phases[$movedPosition];

        $this->sut->reorder($movedPhase->getId(), $newIndex, $this->toCollection($phases));

        $this->assertOrder($expectedOrder, $phases);

        foreach ($phases as $phase) {
            self::assertFalse($phase->isConfigurationPhase(), "phase {$phase->getId()} must never end up as the configuration phase");
        }
    }

    public static function moveWithinAudienceProvider(): array
    {
        return [
            'front to back'   => [0, 2, ['e2', 'e3', 'e1']],
            'back to front'   => [2, 0, ['e3', 'e1', 'e2']],
            'front to middle' => [0, 1, ['e2', 'e1', 'e3']],
            'middle to back'  => [1, 2, ['e1', 'e3', 'e2']],
            'middle to front' => [1, 0, ['e2', 'e1', 'e3']],
            'back to middle'  => [2, 1, ['e1', 'e3', 'e2']],
        ];
    }

    public function testReorderTwoTimesInARowNeverProducesOrderZero(): void
    {
        $e1 = $this->createPhase('e1', 1);
        $e2 = $this->createPhase('e2', 2);
        $e3 = $this->createPhase('e3', 3);
        $phases = [$e1, $e2, $e3];

        // Move e1 to the back...
        $this->sut->reorder('e1', 2, $this->toCollection($phases));
        $this->assertOrder(['e2', 'e3', 'e1'], $phases);

        // ...then move whatever is now at the front to the back again. The collection
        // passed in reflects real post-reorder order (ascending by the now-drifted
        // orderInAudience values), exactly as a fresh DB query would return it.
        $sortedAfterFirstMove = $phases;
        usort($sortedAfterFirstMove, static fn (ProcedurePhaseDefinition $a, ProcedurePhaseDefinition $b) => $a->getOrderInAudience() <=> $b->getOrderInAudience());
        $this->sut->reorder('e2', 2, $this->toCollection($sortedAfterFirstMove));

        $this->assertOrder(['e3', 'e1', 'e2'], $phases);

        foreach ($phases as $phase) {
            self::assertNotSame(0, $phase->getOrderInAudience(), "phase {$phase->getId()} must never drift to orderInAudience 0");
            self::assertFalse($phase->isConfigurationPhase());
        }
    }

    public function testReorderOnSingleItemCollectionThrows(): void
    {
        $e1 = $this->createPhase('e1', 1);

        $this->expectException(\demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException::class);

        $this->sut->reorder('e1', 0, $this->toCollection([$e1]));
    }

    public function testReorderThrowsOnNegativeNewIndex(): void
    {
        $e1 = $this->createPhase('e1', 1);
        $e2 = $this->createPhase('e2', 2);

        $this->expectException(\demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException::class);

        $this->sut->reorder('e1', -1, $this->toCollection([$e1, $e2]));
    }

    public function testReorderThrowsOnNewIndexBeyondAudienceRange(): void
    {
        $e1 = $this->createPhase('e1', 1);
        $e2 = $this->createPhase('e2', 2);

        $this->expectException(\demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException::class);

        $this->sut->reorder('e1', 2, $this->toCollection([$e1, $e2]));
    }

    public function testReorderThrowsOnEmptyAudienceCollection(): void
    {
        $this->expectException(\demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException::class);

        $this->sut->reorder('e1', 0, new ArrayCollection());
    }
}
