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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Logic\Statement\OriginalStatementExporter;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\PhpWord;
use Tests\Base\FunctionalTestCase;

class OriginalStatementExporterTest extends FunctionalTestCase
{
    /**
     * @var OriginalStatementExporter
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = static::getContainer()->get(OriginalStatementExporter::class);
    }

    public function testExportOriginalStatementsWithEmptyArray(): void
    {
        $procedure = ProcedureFactory::createOne();
        $result = $this->sut->exportOriginalStatements([], $procedure->_real());

        /** @var PhpWord $phpWord */
        $phpWord = $result->getPhpWord();

        // Assert there's exactly one section
        static::assertCount(1, $phpWord->getSections());

        $section = $phpWord->getSection(0);
        $elements = $section->getElements();

        // Should have exactly one element which is the "no statements" text
        static::assertCount(1, $elements);
        static::assertInstanceOf(Text::class, $elements[0]);

        /** @var Text $textElement */
        $textElement = $elements[0];
        static::assertStringContainsString('Für die aktuelle Filterung sind keine Stellungnahmen verfügbar.', $textElement->getText());
    }

    public function testExportOriginalStatementsWithMultipleStatements(): void
    {
        $procedure = ProcedureFactory::createOne();
        $statement1 = StatementFactory::createOne([
            'procedure' => $procedure->_real(),
            'text'      => 'First statement content',
            'externId'  => 'STMT-001',
        ]);
        $statement2 = StatementFactory::createOne([
            'procedure' => $procedure->_real(),
            'text'      => 'Second statement content',
            'externId'  => 'STMT-002',
        ]);

        $statements = [$statement1->_real(), $statement2->_real()];
        $result = $this->sut->exportOriginalStatements($statements, $procedure->_real());

        /** @var PhpWord $phpWord */
        $phpWord = $result->getPhpWord();

        // Should have multiple sections (one per statement)
        static::assertCount(2, $phpWord->getSections());

        // Assert each section contains the correct statement
        $firstSection = $phpWord->getSection(0);
        $secondSection = $phpWord->getSection(1);
        $this->assertStatementInSection($firstSection->getElements()[3]->getRows(), 'STMT-001', 'First statement content');
        $this->assertStatementInSection($secondSection->getElements()[3]->getRows(), 'STMT-002', 'Second statement content');
    }

    /**
     * Asserts that a statement is correctly formatted in the given section.
     *
     * @param \PhpOffice\PhpWord\Element\Row[] $rows             the rows of the table containing the statement
     * @param string                           $expectedExternId the expected external ID of the statement
     * @param string                           $expectedText     the expected text content of the statement
     */
    private function assertStatementInSection(array $rows, string $expectedExternId, string $expectedText): void
    {
        // Verify table structure
        static::assertCount(2, $rows, 'Table should have exactly 2 rows (header + content)');

        $headerRow = $rows[0];
        $contentRow = $rows[1];

        // Verify column count
        static::assertCount(2, $headerRow->getCells(), 'Header row should have 2 columns');
        static::assertCount(2, $contentRow->getCells(), 'Content row should have 2 columns');

        // Verify header texts
        $headerIdText = $headerRow->getCells()[0]->getElements()[0]->getText();
        $headerContentText = $headerRow->getCells()[1]->getElements()[0]->getText();

        static::assertEquals('Stellungnahme-ID', $headerIdText, 'Header ID column mismatch');
        static::assertEquals('Stellungnahme', $headerContentText, 'Header content column mismatch');

        // Verify statement data
        $actualExternId = $contentRow->getCells()[0]->getElements()[0]->getText();
        $actualContent = $contentRow->getCells()[1]->getElements()[0]->getText();

        static::assertEquals($expectedExternId, $actualExternId, 'Statement ID mismatch');
        static::assertStringContainsString($expectedText, $actualContent, 'Statement content not found');
    }
}
