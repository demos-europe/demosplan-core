<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureDeletionLog;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureDeletionLogService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureDeletionLogRepository;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class ProcedureDeletionLogServiceTest extends FunctionalTestCase
{
    protected ?ProcedureDeletionLogService $sut = null;

    private ?ProcedureDeletionLogRepository $repository = null;

    private Procedure|Proxy|null $procedure = null;

    private ?User $testUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(ProcedureDeletionLogService::class);
        $this->repository = self::getContainer()->get(ProcedureDeletionLogRepository::class);
        $this->procedure = ProcedureFactory::createOne();
        $this->testUser = $this->loginTestUser(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
    }

    public function testLogSoftDeleteCreatesEntry(): void
    {
        // Arrange
        $countBefore = $this->countEntries(ProcedureDeletionLog::class);

        // Act
        $this->sut->logSoftDelete($this->procedure->_real(), $this->testUser);

        // Assert
        static::assertSame($countBefore + 1, $this->countEntries(ProcedureDeletionLog::class));

        $logEntry = $this->repository->findSoftDeleteEntryForProcedure($this->procedure->getId());
        static::assertInstanceOf(ProcedureDeletionLog::class, $logEntry);
        static::assertSame(ProcedureDeletionLog::DELETE_TYPE_SOFT, $logEntry->getDeleteType());
        static::assertSame($this->procedure->getId(), $logEntry->getProcedureId());
        static::assertSame($this->procedure->getName(), $logEntry->getProcedureName());
        static::assertSame($this->procedure->isMasterTemplate(), $logEntry->isBlueprint());
        static::assertSame($this->testUser->getId(), $logEntry->getDeletedByUserId());
        static::assertSame($this->testUser->getFirstname(), $logEntry->getDeletedByUserFirstName());
        static::assertSame($this->testUser->getLastname(), $logEntry->getDeletedByUserLastName());
        static::assertSame($this->testUser->getEmail(), $logEntry->getDeletedByUserEmail());
        static::assertNotNull($logEntry->getProcedure());
        static::assertNotNull($logEntry->getDeletedAt());
    }

    public function testLogHardDeleteNullsFkOnSoftEntryAndCreatesNewEntry(): void
    {
        // Arrange
        $this->sut->logSoftDelete($this->procedure->_real(), $this->testUser);
        $softEntry = $this->repository->findSoftDeleteEntryForProcedure($this->procedure->getId());
        static::assertNotNull($softEntry->getProcedure());
        $countBefore = $this->countEntries(ProcedureDeletionLog::class);

        // Act
        $this->sut->logHardDelete($this->procedure->_real());

        // Assert
        static::assertSame($countBefore + 1, $this->countEntries(ProcedureDeletionLog::class));

        // Soft entry FK must be nulled
        $softEntryAfter = $this->repository->find($softEntry->getId());
        static::assertNull($softEntryAfter->getProcedure());

        // New hard-delete entry
        $procedureId = $this->procedure->getId();
        $hardEntries = $this->repository->getAllHardDeleted();
        $hardEntry = array_values(array_filter(
            $hardEntries,
            static fn (ProcedureDeletionLog $entry) => $entry->getProcedureId() === $procedureId
        ))[0] ?? null;
        static::assertInstanceOf(ProcedureDeletionLog::class, $hardEntry);
        static::assertSame(ProcedureDeletionLog::DELETE_TYPE_HARD, $hardEntry->getDeleteType());
        static::assertSame($this->procedure->getId(), $hardEntry->getProcedureId());
        static::assertNull($hardEntry->getProcedure());
        static::assertSame(ProcedureDeletionLogService::SYSTEM_ACTOR_NAME, $hardEntry->getDeletedByUserFirstName());
        static::assertSame(ProcedureDeletionLogService::SYSTEM_ACTOR_NAME, $hardEntry->getDeletedByUserLastName());
        static::assertNull($hardEntry->getDeletedByUserId());
        static::assertNull($hardEntry->getDeletedByUserEmail());
    }
}
