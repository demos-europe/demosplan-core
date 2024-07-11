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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementMetaFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class SegmentsByStatementsExporterTest extends FunctionalTestCase
{
    private Statement|Proxy|null $testStatement;
    private Statement|Proxy|null $testOriginalStatement;
    private StatementMeta|Proxy|null $testStatementeMeta;

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
        $this->testOriginalStatement = StatementFactory::createOne();
        $this->testStatement = StatementFactory::createOne(['original' => $this->testOriginalStatement]);
        $this->testStatementeMeta = StatementMetaFactory::createOne();
        $this->testOriginalStatement->setChildren([$this->testStatement->_real()]);
        $this->testStatement->setMeta($this->testStatementeMeta->_real());
    }

    public function testMapStatementsToPathInZipWithTrueDuplicate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $statement = $this->getStatementReference(LoadStatementData::TEST_STATEMENT);
        $this->sut->mapStatementsToPathInZip([$statement, $statement]);
    }

    public function testGetFileName(): void
    {
        $this->testOriginalStatement->setInternId('12345');
        $this->testOriginalStatement->_save();
        $this->testStatement->setInternId('12345');
        $this->testOriginalStatement->_save();

        $testData = [
            '{ID}-{NAME}-{EINGANSNR}' => $this->testStatement->getExternId().'-'.$this->testStatement->getMeta()->getOrgaName().'-'.$this->testStatement->getInternId(),
            '{NAME}'                  => $this->testStatement->getMeta()->getOrgaName(),
            'My Custom Template'      => 'My Custom Template',
            ''                        => $this->testStatement->getExternId().'-'.$this->testStatement->getMeta()->getOrgaName().'-'.$this->testStatement->getInternId(),
        ];

        foreach ($testData as $templateName => $rawExpectedFileName) {
            $this->verifyFileNameFromTemplate(
                $rawExpectedFileName,
                $templateName,
                $this->testStatement);
        }
    }

    private function verifyFileNameFromTemplate(string $rawExpectedFileName, string $templateName, Statement|Proxy|null $testStatement): void
    {
        $expectedFileName = $this->slugify->slugify($rawExpectedFileName);
        $fileName = $this->sut->getFileName($testStatement->_real(), $templateName);
        self::assertSame($expectedFileName, $fileName);
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
