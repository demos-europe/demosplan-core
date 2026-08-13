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

use demosplan\DemosPlanCoreBundle\Exception\IncompleteSegmentMarkersException;
use demosplan\DemosPlanCoreBundle\Exception\MalformedDocxException;
use demosplan\DemosPlanCoreBundle\Exception\MissingSegmentBlockException;
use demosplan\DemosPlanCoreBundle\Exception\UnknownPlaceholdersException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementTemplateValidator;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class StatementTemplateValidatorTest extends AbstractStatementViaTemplateExporterTestCase
{
    protected ?StatementTemplateValidator $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new StatementTemplateValidator();
    }

    protected function tempFilePrefix(): string
    {
        return 'tpl_validator_';
    }

    public function testAcceptsTemplateWithoutSegmentPlaceholders(): void
    {
        $path = $this->createDocxWithParagraphs([
            '${'.StatementTemplateValidator::PLACEHOLDER_NAME.'}',
            '${'.StatementTemplateValidator::PLACEHOLDER_TODAY_DATE.'}',
        ]);

        $this->expectNotToPerformAssertions();
        $this->sut->validate($path);
    }

    public function testAcceptsValidSegmentBlock(): void
    {
        $path = $this->createDocxWithParagraphs([
            '${'.StatementTemplateValidator::PLACEHOLDER_NAME.'}',
            '${'.StatementTemplateValidator::MARKER_SEGMENTS_OPEN.'}',
            '${'.StatementTemplateValidator::PLACEHOLDER_SEGMENT_EXTERN_ID.'}',
            '${'.StatementTemplateValidator::PLACEHOLDER_SEGMENT_TEXT.'}',
            '${'.StatementTemplateValidator::MARKER_SEGMENTS_CLOSE.'}',
        ]);

        $this->expectNotToPerformAssertions();
        $this->sut->validate($path);
    }

    public function testThrowsForUnknownPlaceholder(): void
    {
        $path = $this->createDocxWithParagraphs([
            '${'.StatementTemplateValidator::PLACEHOLDER_NAME.'}',
            '${notAPlaceholderWeAllow}',
        ]);

        try {
            $this->sut->validate($path);
            self::fail('Expected UnknownPlaceholdersException');
        } catch (UnknownPlaceholdersException $exception) {
            self::assertContains('notAPlaceholderWeAllow', $exception->getUnknownPlaceholders());
        }
    }

    public function testThrowsForIncompleteSegmentMarkerPair(): void
    {
        $path = $this->createDocxWithParagraphs([
            '${'.StatementTemplateValidator::MARKER_SEGMENTS_OPEN.'}',
            '${'.StatementTemplateValidator::PLACEHOLDER_SEGMENT_EXTERN_ID.'}',
            // Closing marker intentionally omitted.
        ]);

        $this->expectException(IncompleteSegmentMarkersException::class);
        $this->sut->validate($path);
    }

    public function testThrowsForDuplicateOpenMarker(): void
    {
        $path = $this->createDocxWithParagraphs([
            '${'.StatementTemplateValidator::MARKER_SEGMENTS_OPEN.'}',
            '${'.StatementTemplateValidator::PLACEHOLDER_SEGMENT_EXTERN_ID.'}',
            '${'.StatementTemplateValidator::MARKER_SEGMENTS_CLOSE.'}',
            '${'.StatementTemplateValidator::MARKER_SEGMENTS_OPEN.'}',
        ]);

        $this->expectException(IncompleteSegmentMarkersException::class);
        $this->sut->validate($path);
    }

    public function testThrowsWhenSegmentDataIsPresentWithoutTheBlock(): void
    {
        $path = $this->createDocxWithParagraphs([
            '${'.StatementTemplateValidator::PLACEHOLDER_NAME.'}',
            '${'.StatementTemplateValidator::PLACEHOLDER_SEGMENT_EXTERN_ID.'}',
        ]);

        $this->expectException(MissingSegmentBlockException::class);
        $this->sut->validate($path);
    }

    public function testThrowsForMalformedDocx(): void
    {
        $path = $this->createTemporaryFile('this is not a valid OOXML zip');

        $this->expectException(MalformedDocxException::class);
        $this->sut->validate($path);
    }

    public function testAcceptsCommittedExample(): void
    {
        $this->expectNotToPerformAssertions();
        $this->sut->validate($this->exampleTemplate);
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
}
