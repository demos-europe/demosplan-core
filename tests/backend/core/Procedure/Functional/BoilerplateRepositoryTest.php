<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\BoilerplateFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateRepository;
use Tests\Base\FunctionalTestCase;

class BoilerplateRepositoryTest extends FunctionalTestCase
{
    protected ?BoilerplateRepository $sut = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(BoilerplateRepository::class);
    }

    /**
     * DPLAN-18271: below the attempt limit, a failure just increments the counter and
     * leaves the boilerplate pending (not user-visible again).
     */
    public function testHandleDeletionFailureIncrementsCounterBelowTheLimit(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['pendingDeletion' => true])->_real();
        $boilerplateId = $boilerplate->getId();

        $this->sut->handleDeletionFailure($boilerplateId, 10);

        $reloaded = $this->reloadBoilerplate($boilerplateId);
        static::assertSame(1, $reloaded->getDeletionFailureCount());
        static::assertTrue($reloaded->isPendingDeletion());
    }

    /**
     * DPLAN-18271: the 10th consecutive failure gives up — pendingDeletion resets to
     * false (visible again) and the counter resets to 0, rather than retrying forever.
     */
    public function testHandleDeletionFailureGivesUpAfterReachingTheLimit(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['pendingDeletion' => true])->_real();
        $boilerplateId = $boilerplate->getId();

        for ($attempt = 1; $attempt <= 9; ++$attempt) {
            $this->sut->handleDeletionFailure($boilerplateId, 10);
        }
        $afterNine = $this->reloadBoilerplate($boilerplateId);
        static::assertSame(9, $afterNine->getDeletionFailureCount());
        static::assertTrue($afterNine->isPendingDeletion());

        $this->sut->handleDeletionFailure($boilerplateId, 10);

        $afterTen = $this->reloadBoilerplate($boilerplateId);
        static::assertSame(0, $afterTen->getDeletionFailureCount());
        static::assertFalse($afterTen->isPendingDeletion());
    }

    private function reloadBoilerplate(string $id): Boilerplate
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->clear();

        return $this->sut->get($id);
    }
}
