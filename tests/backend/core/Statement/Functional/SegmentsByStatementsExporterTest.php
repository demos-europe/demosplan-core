<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadStatementData;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use Tests\Base\FunctionalTestCase;

class SegmentsByStatementsExporterTest extends FunctionalTestCase
{
    /**
     * @var SegmentsByStatementsExporter
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(SegmentsByStatementsExporter::class);
    }

    public function testMapStatementsToPathInZipWithTrueDuplicate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $statement = $this->getStatementReference(LoadStatementData::TEST_STATEMENT);
        $this->sut->mapStatementsToPathInZip([$statement, $statement]);
    }

    public function testMapStatementsToPathInZipWithSuperficialDuplicate(): void
    {
        self::markSkippedForCIIntervention();

        $statementA = $this->createMinimalTestStatement('a', 'a', 'a');
        $statementB = $this->createMinimalTestStatement('b', 'a', 'a');

        $statements = $this->sut->mapStatementsToPathInZip([$statementA, $statementB]);

        $expectedAKey = 'statement_submit_name_a (statement_intern_id_a-statement_id_a).docx';
        self::assertArrayHasKey($expectedAKey, $statements);
        self::assertSame($statementA, $statements[$expectedAKey]);
        $expectedBKey = 'statement_submit_name_a (statement_intern_id_a-statement_id_b).docx';
        self::assertArrayHasKey($expectedBKey, $statements);
        self::assertSame($statementB, $statements[$expectedBKey]);
    }

    public function testMapStatementsToPathInZipWithoutDuplicate(): void
    {
        self::markSkippedForCIIntervention();

        $statementA = $this->createMinimalTestStatement('a', 'a', 'a');
        $statementB = $this->createMinimalTestStatement('b', 'b', 'b');

        $statements = $this->sut->mapStatementsToPathInZip([$statementA, $statementB]);

        $expectedAKey = 'statement_submit_name_a (statement_intern_id_a).docx';
        self::assertArrayHasKey($expectedAKey, $statements);
        self::assertSame($statementA, $statements[$expectedAKey]);
        $expectedBKey = 'statement_submit_name_b (statement_intern_id_b).docx';
        self::assertArrayHasKey($expectedBKey, $statements);
        self::assertSame($statementB, $statements[$expectedBKey]);
    }

    private function createMinimalTestStatement(string $idSuffix, string $internIdSuffix, string $submitterNameSuffix): Statement
    {
        $statement = new Statement();
        $statement->setId("statement_id_$idSuffix");
        $statement->setInternId("statement_intern_id_$internIdSuffix");
        $statement->getMeta()->setSubmitName("statement_submit_name_$submitterNameSuffix");

        return $statement;
    }
}
