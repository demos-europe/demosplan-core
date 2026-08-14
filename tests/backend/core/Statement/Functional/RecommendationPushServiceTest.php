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
            self::getContainer()->get(HTMLSanitizer::class),
            $this->entityManager,
            new NullLogger(),
        );
    }

    public function testWritesTheRecommendationOfAnAssessableStatement(): void
    {
        $statement = $this->assessableStatement('procedure-1');
        $this->statementRepository->method('find')->willReturn($statement);

        $results = $this->sut->push($this->procedure('procedure-1'), [
            ['sourceStatementId' => 'statement-1', 'recommendation' => '<p>Wird berücksichtigt.</p>'],
        ]);

        self::assertSame(RecommendationPushOutcome::PUSHED, $results[0]->outcome);
        self::assertStringContainsString('Wird berücksichtigt.', $statement->getRecommendation());
    }

    public function testStripsScriptFromTheIncomingPayload(): void
    {
        $statement = $this->assessableStatement('procedure-1');
        $this->statementRepository->method('find')->willReturn($statement);

        $this->sut->push($this->procedure('procedure-1'), [
            [
                'sourceStatementId' => 'statement-1',
                'recommendation'    => '<p>ok</p><script>alert(1)</script><img src=x onerror=alert(1)>',
            ],
        ]);

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

        $results = $this->sut->push($this->procedure('procedure-1'), [
            ['sourceStatementId' => 'statement-1', 'recommendation' => '   <p>  </p> '],
        ]);

        self::assertSame(RecommendationPushOutcome::SKIPPED_EMPTY, $results[0]->outcome);
        self::assertStringContainsString('Bestehende Abwägung', $statement->getRecommendation());
    }

    public function testRejectsAStatementOfAnotherProcedure(): void
    {
        $this->statementRepository->method('find')->willReturn($this->assessableStatement('other-procedure'));

        $results = $this->sut->push($this->procedure('procedure-1'), [
            ['sourceStatementId' => 'statement-1', 'recommendation' => '<p>x</p>'],
        ]);

        self::assertSame(RecommendationPushOutcome::FAILED_FOREIGN_PROCEDURE, $results[0]->outcome);
        self::assertTrue($results[0]->outcome->isFailure());
    }

    public function testRejectsAnUnknownStatement(): void
    {
        $this->statementRepository->method('find')->willReturn(null);

        $results = $this->sut->push($this->procedure('procedure-1'), [
            ['sourceStatementId' => 'nope', 'recommendation' => '<p>x</p>'],
        ]);

        self::assertSame(RecommendationPushOutcome::FAILED_UNKNOWN_STATEMENT, $results[0]->outcome);
    }

    public function testRejectsAnOriginalStatement(): void
    {
        // An original has no original of its own; originals are read-only on this side.
        $original = new Statement();
        $original->setProcedure($this->procedure('procedure-1'));
        $this->statementRepository->method('find')->willReturn($original);

        $results = $this->sut->push($this->procedure('procedure-1'), [
            ['sourceStatementId' => 'statement-1', 'recommendation' => '<p>x</p>'],
        ]);

        self::assertSame(RecommendationPushOutcome::FAILED_NOT_ASSESSABLE, $results[0]->outcome);
    }

    /**
     * Each changed setRecommendation() records another version, so a repeated id must not be applied
     * twice within one request.
     */
    public function testAppliesOnlyTheFirstEntryForARepeatedStatement(): void
    {
        $statement = $this->assessableStatement('procedure-1');
        $this->statementRepository->method('find')->willReturn($statement);

        $results = $this->sut->push($this->procedure('procedure-1'), [
            ['sourceStatementId' => 'statement-1', 'recommendation' => '<p>erste</p>'],
            ['sourceStatementId' => 'statement-1', 'recommendation' => '<p>zweite</p>'],
        ]);

        self::assertSame(RecommendationPushOutcome::PUSHED, $results[0]->outcome);
        self::assertSame(RecommendationPushOutcome::SKIPPED_DUPLICATE, $results[1]->outcome);
        self::assertStringContainsString('erste', $statement->getRecommendation());
        self::assertStringNotContainsString('zweite', $statement->getRecommendation());
    }

    public function testCommitsWhatSucceededAlongsideAFailure(): void
    {
        $good = $this->assessableStatement('procedure-1');
        $this->statementRepository->method('find')->willReturnCallback(
            fn (string $id): ?Statement => 'good' === $id ? $good : null
        );
        $this->entityManager->expects(self::once())->method('flush');

        $results = $this->sut->push($this->procedure('procedure-1'), [
            ['sourceStatementId' => 'bad', 'recommendation' => '<p>x</p>'],
            ['sourceStatementId' => 'good', 'recommendation' => '<p>y</p>'],
        ]);

        self::assertSame(RecommendationPushOutcome::FAILED_UNKNOWN_STATEMENT, $results[0]->outcome);
        self::assertSame(RecommendationPushOutcome::PUSHED, $results[1]->outcome);
    }

    public function testDoesNotFlushWhenNothingChanged(): void
    {
        $this->statementRepository->method('find')->willReturn(null);
        $this->entityManager->expects(self::never())->method('flush');

        $this->sut->push($this->procedure('procedure-1'), [
            ['sourceStatementId' => 'nope', 'recommendation' => '<p>x</p>'],
        ]);
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
