<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureIntegration\RecommendationPushOutcome;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureIntegration\RecommendationPushService;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Tests\Base\UnitTestCase;

/**
 * The purifier is the real one, because "does the incoming HTML get neutralised" is exactly what a
 * mock would paper over.
 */
class RecommendationPushServiceTest extends UnitTestCase
{
    private ?RecommendationPushService $sut = null;
    private ?MockObject $statementRepository = null;
    private ?MockObject $entityManager = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statementRepository = $this->createMock(StatementRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->sut = new RecommendationPushService(
            $this->statementRepository,
            $this->getContainer()->get(HTMLSanitizer::class),
            $this->entityManager,
            new NullLogger(),
        );
    }

    public function testWritesTheRecommendationOfAnAssessableStatement(): void
    {
        $statement = $this->assessableStatement('procedure-1');
        $this->statementRepository->method('find')->willReturn($statement);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->sut->push($this->procedure('procedure-1'), 'statement-1', '<p>Wird berücksichtigt.</p>');

        self::assertSame(RecommendationPushOutcome::UPDATED, $result->outcome);
        self::assertSame('statement-1', $result->targetId);
        self::assertSame('M1', $result->externId);
        self::assertStringContainsString('Wird berücksichtigt.', $statement->getRecommendation());
    }

    public function testStripsScriptFromTheIncomingPayload(): void
    {
        $statement = $this->assessableStatement('procedure-1');
        $this->statementRepository->method('find')->willReturn($statement);

        $this->sut->push(
            $this->procedure('procedure-1'),
            'statement-1',
            '<p>ok</p><script>alert(1)</script><img src=x onerror=alert(1)>'
        );

        $written = $statement->getRecommendation();
        self::assertStringNotContainsString('<script', $written);
        self::assertStringNotContainsString('onerror', $written);
        self::assertStringContainsString('ok', $written);
    }

    public function testSkipsAnEmptyRecommendationInsteadOfErasingTheExistingOne(): void
    {
        $statement = $this->assessableStatement('procedure-1');
        $statement->setRecommendation('<p>Bestehende Abwägung</p>');
        $this->statementRepository->method('find')->willReturn($statement);
        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->sut->push($this->procedure('procedure-1'), 'statement-1', '   <p>  </p> ');

        self::assertSame(RecommendationPushOutcome::SKIPPED_EMPTY, $result->outcome);
        self::assertStringContainsString('Bestehende Abwägung', $statement->getRecommendation());
    }

    public function testRejectsAStatementOfAnotherProcedureWithoutNamingIt(): void
    {
        $this->statementRepository->method('find')->willReturn($this->assessableStatement('other-procedure'));

        $result = $this->sut->push($this->procedure('procedure-1'), 'statement-1', '<p>x</p>');

        self::assertSame(RecommendationPushOutcome::FAILED_FOREIGN_PROCEDURE, $result->outcome);
        self::assertTrue($result->outcome->isFailure());
        self::assertNull($result->targetId);
        self::assertNull($result->externId);
    }

    public function testRejectsAnUnknownStatement(): void
    {
        $this->statementRepository->method('find')->willReturn(null);
        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->sut->push($this->procedure('procedure-1'), 'nope', '<p>x</p>');

        self::assertSame(RecommendationPushOutcome::FAILED_UNKNOWN_TARGET, $result->outcome);
    }

    public function testRejectsAnOriginalStatement(): void
    {
        // An original has no original of its own; originals are read-only on this side.
        $original = new Statement();
        $original->setProcedure($this->procedure('procedure-1'));
        $this->statementRepository->method('find')->willReturn($original);

        $result = $this->sut->push($this->procedure('procedure-1'), 'statement-1', '<p>x</p>');

        self::assertSame(RecommendationPushOutcome::FAILED_NOT_UPDATABLE, $result->outcome);
    }

    private function procedure(string $id): Procedure
    {
        $procedure = $this->createMock(Procedure::class);
        $procedure->method('getId')->willReturn($id);

        return $procedure;
    }

    /**
     * A statement as the assessment table shows it: a copy of an original, not deleted, not moved.
     */
    private function assessableStatement(string $procedureId): Statement
    {
        $statement = new Statement();
        $statement->setOriginal(new Statement());
        $statement->setProcedure($this->procedure($procedureId));
        $statement->setExternId('M1');

        return $statement;
    }
}
