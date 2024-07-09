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

use Cocur\Slugify\Slugify;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadStatementData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class SegmentsByStatementsExporterTest extends FunctionalTestCase
{
    private Procedure|Proxy|null $testProcedure;

    /**
     * @var SegmentsByStatementsExporter
     */
    protected $sut;

    private Slugify|Proxy|null $slugify;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(SegmentsByStatementsExporter::class);
        $this->slugify = $this->getContainer()->get(Slugify::class);
        $this->testProcedure = ProcedureFactory::createOne();
    }

    public function testMapStatementsToPathInZipWithTrueDuplicate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $statement = $this->getStatementReference(LoadStatementData::TEST_STATEMENT);
        $this->sut->mapStatementsToPathInZip([$statement, $statement]);
    }

    public function testGetSynopseFileName(): void
    {
        $templateName = '{ID}-{NAME}-{EINGANSNR}';
        $suffix = 'docx';
        $fileName = $this->sut->getSynopseFileName($this->testProcedure->_real(), $suffix, $templateName);
        self::assertSame($this->testProcedure->getId().'-'.$this->testProcedure->getName().'-'.$this->testProcedure->getExternId().'.'.$suffix, $fileName);

        $templateName = 'My Template';
        $fileName = $this->sut->getSynopseFileName($this->testProcedure->_real(), $suffix, $templateName);
        self::assertSame('My Template.'.$suffix, $fileName);

        $templateName = '';
        $fileName = $this->sut->getSynopseFileName($this->testProcedure->_real(), $suffix, $templateName);
        self::assertSame('Synopse-'.$this->slugify->slugify($this->testProcedure->getName()).'.'.$suffix, $fileName);
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
