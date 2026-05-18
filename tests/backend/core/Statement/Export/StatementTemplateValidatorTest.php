<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Export;

use demosplan\DemosPlanCoreBundle\Exception\InvalidStatementTemplateException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementTemplateValidator;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\UnitTestCase;

class StatementTemplateValidatorTest extends UnitTestCase
{
    protected ?StatementTemplateValidator $sut = null;

    /**
     * @var list<string>|null
     */
    private ?array $temporaryFiles = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryFiles = [];

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(static function (?string $id, array $parameters = []): string {
                if ([] === $parameters) {
                    return (string) $id;
                }
                $serialized = [];
                foreach ($parameters as $name => $value) {
                    $serialized[] = $name.'='.$value;
                }

                return $id.'|'.implode(',', $serialized);
            });

        $this->sut = new StatementTemplateValidator($translator);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles ?? [] as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $this->temporaryFiles = null;

        parent::tearDown();
    }

    public function testReturnsNullWhenTemplateUsesNoSegmentPlaceholders(): void
    {
        $path = $this->createDocxWithParagraphs([
            '${submitterName}',
            '${todayDate}',
        ]);

        self::assertNull($this->sut->validate($path));
    }

    public function testReturnsAsParagraphsModeForValidParagraphBlock(): void
    {
        $path = $this->createDocxWithParagraphs([
            '${submitterName}',
            '${segmentsAsParagraphs}',
            '${segmentExternId}',
            '${segmentText}',
            '${/segmentsAsParagraphs}',
        ]);

        self::assertSame(
            StatementTemplateValidator::MODE_AS_PARAGRAPHS,
            $this->sut->validate($path)
        );
    }

    public function testReturnsWithinTableModeForMarkerInsideATableCell(): void
    {
        $path = $this->createDocxWithTableCells([
            '${segmentsWithinTable}${segmentExternId}',
            '${segmentText}',
            '${segmentRecommendation}',
        ]);

        self::assertSame(
            StatementTemplateValidator::MODE_WITHIN_TABLE,
            $this->sut->validate($path)
        );
    }

    public function testThrowsForUnknownPlaceholder(): void
    {
        $path = $this->createDocxWithParagraphs([
            '${submitterName}',
            '${notAPlaceholderWeAllow}',
        ]);

        $this->expectException(InvalidStatementTemplateException::class);
        $this->expectExceptionMessageMatches('/unknown_placeholder/');
        $this->expectExceptionMessageMatches('/notAPlaceholderWeAllow/');

        $this->sut->validate($path);
    }

    public function testThrowsForIncompleteAsParagraphsPair(): void
    {
        $path = $this->createDocxWithParagraphs([
            '${segmentsAsParagraphs}',
            '${segmentExternId}',
            // Closing marker intentionally omitted.
        ]);

        $this->expectException(InvalidStatementTemplateException::class);
        $this->expectExceptionMessageMatches('/as_paragraphs_marker_incomplete/');

        $this->sut->validate($path);
    }

    public function testThrowsWhenBothModeMarkersArePresent(): void
    {
        $path = $this->createDocxWithMixedLayout(
            [
                '${segmentsAsParagraphs}',
                '${segmentExternId}',
                '${/segmentsAsParagraphs}',
            ],
            ['${segmentsWithinTable}', '${segmentText}']
        );

        $this->expectException(InvalidStatementTemplateException::class);
        $this->expectExceptionMessageMatches('/both_modes_present/');

        $this->sut->validate($path);
    }

    public function testThrowsWhenSegmentDataIsPresentWithoutAnyModeMarker(): void
    {
        $path = $this->createDocxWithParagraphs([
            '${submitterName}',
            '${segmentExternId}',
        ]);

        $this->expectException(InvalidStatementTemplateException::class);
        $this->expectExceptionMessageMatches('/segment_data_without_mode/');

        $this->sut->validate($path);
    }

    public function testThrowsWhenWithinTableMarkerLivesOutsideATable(): void
    {
        $path = $this->createDocxWithParagraphs([
            '${segmentsWithinTable}',
            '${segmentExternId}',
        ]);

        $this->expectException(InvalidStatementTemplateException::class);
        $this->expectExceptionMessageMatches('/within_table_not_in_table/');

        $this->sut->validate($path);
    }

    public function testThrowsForMalformedDocx(): void
    {
        $path = $this->createTemporaryFile('this is not a valid OOXML zip');

        $this->expectException(InvalidStatementTemplateException::class);
        $this->expectExceptionMessageMatches('/malformed_docx/');

        $this->sut->validate($path);
    }

    public function testAcceptsCommittedParagraphsExample(): void
    {
        self::assertSame(
            StatementTemplateValidator::MODE_AS_PARAGRAPHS,
            $this->sut->validate(__DIR__.'/res/statement_template_example_paragraphs.docx')
        );
    }

    public function testAcceptsCommittedTableExample(): void
    {
        self::assertSame(
            StatementTemplateValidator::MODE_WITHIN_TABLE,
            $this->sut->validate(__DIR__.'/res/statement_template_example_table.docx')
        );
    }

    /**
     * @param list<string> $paragraphTexts
     */
    private function createDocxWithParagraphs(array $paragraphTexts): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        foreach ($paragraphTexts as $text) {
            $section->addText($text);
        }

        return $this->saveDocx($phpWord);
    }

    /**
     * @param list<string> $cellTexts
     */
    private function createDocxWithTableCells(array $cellTexts): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $row = $table->addRow();
        foreach ($cellTexts as $text) {
            $row->addCell()->addText($text);
        }

        return $this->saveDocx($phpWord);
    }

    /**
     * @param list<string> $paragraphTexts
     * @param list<string> $cellTexts
     */
    private function createDocxWithMixedLayout(array $paragraphTexts, array $cellTexts): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        foreach ($paragraphTexts as $text) {
            $section->addText($text);
        }
        $table = $section->addTable();
        $row = $table->addRow();
        foreach ($cellTexts as $text) {
            $row->addCell()->addText($text);
        }

        return $this->saveDocx($phpWord);
    }

    private function saveDocx(PhpWord $phpWord): string
    {
        $path = $this->reservePath('.docx');
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    private function createTemporaryFile(string $contents): string
    {
        $path = $this->reservePath('.bin');
        file_put_contents($path, $contents);

        return $path;
    }

    private function reservePath(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl_validator_').$extension;
        $this->temporaryFiles ??= [];
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
