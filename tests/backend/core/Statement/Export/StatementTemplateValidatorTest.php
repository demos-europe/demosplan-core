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

class StatementTemplateValidatorTest extends AbstractStatementViaTemplateExporterTestCase
{
    protected ?StatementTemplateValidator $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->expectException(InvalidStatementTemplateException::class);
        $this->expectExceptionMessageMatches('/unknown_placeholder/');
        $this->expectExceptionMessageMatches('/notAPlaceholderWeAllow/');

        $this->sut->validate($path);
    }

    public function testThrowsForIncompleteSegmentMarkerPair(): void
    {
        $path = $this->createDocxWithParagraphs([
            '${'.StatementTemplateValidator::MARKER_SEGMENTS_OPEN.'}',
            '${'.StatementTemplateValidator::PLACEHOLDER_SEGMENT_EXTERN_ID.'}',
            // Closing marker intentionally omitted.
        ]);

        $this->expectException(InvalidStatementTemplateException::class);
        $this->expectExceptionMessageMatches('/segments_marker_incomplete/');

        $this->sut->validate($path);
    }

    public function testThrowsWhenSegmentDataIsPresentWithoutTheBlock(): void
    {
        $path = $this->createDocxWithParagraphs([
            '${'.StatementTemplateValidator::PLACEHOLDER_NAME.'}',
            '${'.StatementTemplateValidator::PLACEHOLDER_SEGMENT_EXTERN_ID.'}',
        ]);

        $this->expectException(InvalidStatementTemplateException::class);
        $this->expectExceptionMessageMatches('/segment_data_without_block/');

        $this->sut->validate($path);
    }

    public function testThrowsForMalformedDocx(): void
    {
        $path = $this->createTemporaryFile('this is not a valid OOXML zip');

        $this->expectException(InvalidStatementTemplateException::class);
        $this->expectExceptionMessageMatches('/malformed_docx/');

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
