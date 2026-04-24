<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Segment;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;
use ReflectionMethod;
use Tests\Base\FunctionalTestCase;

/**
 * Guards the one-line addition in `ProcedureService::copyPlaces()` that
 * propagates the `locked` flag from blueprint places to their clones.
 *
 * Without the `$newPlace->setLocked($sourcePlace->isLocked())` call in the
 * copy loop, procedures spawned from a blueprint configured with locked
 * workflow places would silently lose the lock configuration. This test
 * drives the private `copyPlaces` method via reflection (it is called from
 * `copyFromBlueprint` during procedure creation) and asserts the
 * per-place lock state survives the copy.
 */
class SegmentLockBlueprintPropagationTest extends FunctionalTestCase
{
    protected ?ProcedureService $sut = null;
    private ?PlaceRepository $placeRepository = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(ProcedureService::class);
        $this->placeRepository = $this->getContainer()->get(PlaceRepository::class);
    }

    public function testCopyPlacesPropagatesLockedFlagFromBlueprintToClone(): void
    {
        $blueprint = ProcedureFactory::createOne()->_real();
        PlaceFactory::createOne(['procedure' => $blueprint, 'name' => 'locked-step', 'locked' => true]);
        PlaceFactory::createOne(['procedure' => $blueprint, 'name' => 'unlocked-step', 'locked' => false]);

        $target = ProcedureFactory::createOne()->_real();

        $this->invokePrivateCopyPlaces($blueprint, $target);
        $this->getEntityManager()->flush();

        $clones = $this->placeRepository->findBy(['procedure' => $target]);
        $clonesByName = [];
        foreach ($clones as $place) {
            $clonesByName[$place->getName()] = $place;
        }

        self::assertArrayHasKey('locked-step', $clonesByName);
        self::assertArrayHasKey('unlocked-step', $clonesByName);
        self::assertTrue(
            $clonesByName['locked-step']->isLocked(),
            'Locked blueprint place must propagate `locked=true` to the cloned procedure',
        );
        self::assertFalse(
            $clonesByName['unlocked-step']->isLocked(),
            'Unlocked blueprint place must propagate `locked=false` to the cloned procedure',
        );
    }

    /**
     * copyPlaces is intentionally private — it's an implementation detail of
     * procedure duplication. Reflection keeps the test focused on the copy
     * loop without pulling in the whole blueprint-to-procedure pipeline.
     */
    private function invokePrivateCopyPlaces(Procedure $source, Procedure $target): void
    {
        $method = new ReflectionMethod(ProcedureService::class, 'copyPlaces');
        $method->setAccessible(true);
        $method->invoke($this->sut, $source->getId(), $target);
    }
}
