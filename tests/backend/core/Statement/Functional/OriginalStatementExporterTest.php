<?php

declare(strict_types=1);

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
        $this->sut =  static::getContainer()->get(OriginalStatementExporter::class);
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

        /** @var \PhpOffice\PhpWord\Element\Text $textElement */
        $textElement = $elements[0];
        static::assertStringContainsString('Für die aktuelle Filterung sind keine Stellungnahmen verfügbar.', $textElement->getText());
    }


    public function testExportOriginalStatementsWithMultipleStatements(): void
    {
        $procedure = ProcedureFactory::createOne();
        $statement1 = StatementFactory::createOne([
            'procedure' => $procedure->_real(),
            'text' => 'First statement content',
            'externId' => 'STMT-001'
        ]);
        $statement2 = StatementFactory::createOne([
            'procedure' => $procedure->_real(),
            'text' => 'Second statement content',
            'externId' => 'STMT-002'
        ]);

        $statements = [$statement1->_real(), $statement2->_real()];
        $result = $this->sut->exportOriginalStatements($statements, $procedure->_real());

        /** @var PhpWord $phpWord */
        $phpWord = $result->getPhpWord();

        // Should have multiple sections (one per statement)
        static::assertCount(2, $phpWord->getSections());

        // Test first statement section
        $firstSection = $phpWord->getSection(0);
        $secondSection = $phpWord->getSection(1);
        $this->assertStatementInSection($firstSection->getElements()[3]->getRows(), 'STMT-001', 'First statement content');
        $this->assertStatementInSection($secondSection->getElements()[3]->getRows(), 'STMT-002', 'Second statement content');

    }

    private function assertStatementInSection($rows, string $expectedExternId, string $expectedText): void
    {

        //Stellungnahme-ID
        static::assertEquals('Stellungnahme-ID', $rows[0]->getCells()[0]->getElements()[0]->getText(), 'Statement ID mismatch');
        static::assertEquals('Stellungnahme', $rows[0]->getCells()[1]->getElements()[0]->getText(), 'Statement ID mismatch');

        // Assert table structure: header row + content row
        static::assertCount(2, $rows, 'Table should have exactly 2 rows (header + content)');

        // Check header row has 2 columns
        $headerCells = $rows[0]->getCells();
        static::assertCount(2, $headerCells, 'Header row should have 2 columns');

        // Check content row has 2 columns
        $contentCells = $rows[1]->getCells();
        static::assertCount(2, $contentCells, 'Content row should have 2 columns');

        // Assert statement ID in first column
        $idCellText = $contentCells[0]->getElements()[0]->getText();
        static::assertEquals($expectedExternId, $idCellText, 'Statement ID mismatch');

        // Assert statement content in second column
        $contentCellText = $contentCells[1]->getElements()[0]->getText();
        static::assertStringContainsString($expectedText, $contentCellText, 'Statement content not found');
    }

}

