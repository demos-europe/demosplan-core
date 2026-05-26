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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidStatementTemplateException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementTemplateDataBuilder;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementTemplateValidator;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementViaTemplateExporter;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementTemplateData;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Base\UnitTestCase;
use ZipArchive;

class StatementViaTemplateExporterTest extends UnitTestCase
{
    private const SUBMITTER_NAME = 'Maria Mustermann';

    protected ?StatementViaTemplateExporter $sut = null;

    private (StatementTemplateValidator&MockObject)|null $validator = null;
    private (StatementTemplateDataBuilder&MockObject)|null $dataBuilder = null;

    /**
     * @var list<string>|null
     */
    private ?array $temporaryFiles = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryFiles = [];

        $this->validator = $this->createMock(StatementTemplateValidator::class);
        $this->dataBuilder = $this->createMock(StatementTemplateDataBuilder::class);

        $htmlHelper = $this->createMock(HtmlHelper::class);
        $htmlHelper->method('getHtmlValidText')
            ->willReturnCallback(static fn (string $text): string => $text);

        $this->sut = new StatementViaTemplateExporter(
            $this->validator,
            $this->dataBuilder,
            $htmlHelper,
            $this->createMock(LoggerInterface::class)
        );
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

    public function testRendersSimplePlaceholdersWhenTemplateHasNoSegments(): void
    {
        $templatePath = $this->createTemplateWithParagraphs([
            'Sehr geehrte/r ${'.StatementTemplateValidator::PLACEHOLDER_NAME.'}',
            'Ihre Stellungnahme: ${'.StatementTemplateValidator::PLACEHOLDER_STATEMENT_EXTERN_ID.'}',
            'Datum: ${'.StatementTemplateValidator::PLACEHOLDER_TODAY_DATE.'}',
        ]);
        $this->dataBuilder->method('build')->willReturn($this->buildData([
            'submitterName'     => self::SUBMITTER_NAME,
            'statementExternId' => 'M42',
            'todayDate'         => '18.05.2026',
        ]));

        $resultPath = $this->renderToFile($templatePath);

        self::assertSame([], $this->getRemainingVariables($resultPath));
        $bodyText = $this->extractBodyText($resultPath);
        self::assertStringContainsString(self::SUBMITTER_NAME, $bodyText);
        self::assertStringContainsString('M42', $bodyText);
        self::assertStringContainsString('18.05.2026', $bodyText);
    }

    public function testRendersSegmentBlockWithMultipleSegments(): void
    {
        $templatePath = $this->createSegmentBlockTemplate();
        $this->dataBuilder->method('build')->willReturn($this->buildData(
            ['submitterName' => self::SUBMITTER_NAME],
            [
                $this->makeSegment('M42-1', '<p>Erstes Vorbringen</p>', '<p>Erste Erwiderung</p><p>Mit zweiter Zeile.</p>'),
                $this->makeSegment('M42-2', '<p>Zweites Vorbringen</p>', '<p>Zweite Erwiderung</p>'),
            ]
        ));

        $resultPath = $this->renderToFile($templatePath);

        self::assertSame([], $this->getRemainingVariables($resultPath));
        $bodyText = $this->extractBodyText($resultPath);
        self::assertStringContainsString(self::SUBMITTER_NAME, $bodyText);
        self::assertStringContainsString('M42-1', $bodyText);
        self::assertStringContainsString('M42-2', $bodyText);
        self::assertStringContainsString('Erstes Vorbringen', $bodyText);
        self::assertStringContainsString('Mit zweiter Zeile.', $bodyText);
    }

    public function testReplacesNullSimpleFieldsWithEmptyStrings(): void
    {
        $templatePath = $this->createTemplateWithParagraphs([
            'Name: ${'.StatementTemplateValidator::PLACEHOLDER_NAME.'}',
            'Hausnummer: ${'.StatementTemplateValidator::PLACEHOLDER_HOUSE_NUMBER.'}',
        ]);
        $this->dataBuilder->method('build')->willReturn($this->buildData([
            'submitterName'        => self::SUBMITTER_NAME,
            'submitterHouseNumber' => null,
        ]));

        $resultPath = $this->renderToFile($templatePath);

        self::assertSame([], $this->getRemainingVariables($resultPath));
        $bodyText = $this->extractBodyText($resultPath);
        self::assertStringContainsString(self::SUBMITTER_NAME, $bodyText);
        self::assertStringContainsString('Hausnummer: ', $bodyText);
    }

    public function testPropagatesValidatorExceptionWithoutTouchingTheTemplate(): void
    {
        $templatePath = $this->createTemplateWithParagraphs(['${'.StatementTemplateValidator::PLACEHOLDER_NAME.'}']);
        $this->validator->method('validate')->with($templatePath)
            ->willThrowException(new InvalidStatementTemplateException('whatever the validator says'));
        $this->dataBuilder->expects(self::never())->method('build');

        $this->expectException(InvalidStatementTemplateException::class);
        $this->expectExceptionMessage('whatever the validator says');

        $this->sut->export($this->createMock(Procedure::class), $this->createMock(Statement::class), $templatePath);
    }

    /**
     * @param array<string, string|null> $simpleValues
     * @param list<Segment>              $segments
     */
    private function buildData(array $simpleValues = [], array $segments = []): StatementTemplateData
    {
        $defaults = [
            'submitterName'        => '',
            'submitterOrgaName'    => '',
            'submitterStreet'      => '',
            'submitterHouseNumber' => '',
            'submitterPostalCode'  => '',
            'submitterCity'        => '',
            'statementExternId'    => '',
            'statementInternId'    => '',
            'procedureName'        => '',
            'todayDate'            => '',
        ];
        $values = array_merge($defaults, $simpleValues);

        $data = new StatementTemplateData();
        $data->setSubmitterName($values['submitterName']);
        $data->setSubmitterOrgaName($values['submitterOrgaName']);
        $data->setSubmitterStreet($values['submitterStreet']);
        $data->setSubmitterHouseNumber($values['submitterHouseNumber']);
        $data->setSubmitterPostalCode($values['submitterPostalCode']);
        $data->setSubmitterCity($values['submitterCity']);
        $data->setStatementExternId($values['statementExternId']);
        $data->setStatementInternId($values['statementInternId']);
        $data->setProcedureName($values['procedureName']);
        $data->setTodayDate($values['todayDate']);
        $data->setSegments($segments);
        $data->lock();

        return $data;
    }

    private function makeSegment(string $externId, string $text, string $recommendation): Segment&MockObject
    {
        $segment = $this->createMock(Segment::class);
        $segment->method('getExternId')->willReturn($externId);
        $segment->method('getText')->willReturn($text);
        $segment->method('getRecommendation')->willReturn($recommendation);

        return $segment;
    }

    private function renderToFile(string $templatePath): string
    {
        $templateProcessor = $this->sut->export(
            $this->createMock(Procedure::class),
            $this->createMock(Statement::class),
            $templatePath
        );
        $resultPath = $this->reservePath('.docx');
        $templateProcessor->saveAs($resultPath);

        return $resultPath;
    }

    /**
     * @return list<string>
     */
    private function getRemainingVariables(string $absolutePath): array
    {
        $templateProcessor = new TemplateProcessor($absolutePath);

        return array_values($templateProcessor->getVariables());
    }

    private function extractBodyText(string $absolutePath): string
    {
        $zip = new ZipArchive();
        self::assertSame(true, $zip->open($absolutePath));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        self::assertNotFalse($xml);
        if (false === preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/', $xml, $matches)) {
            return '';
        }

        return implode("\n", $matches[1]);
    }

    /**
     * @param list<string> $paragraphTexts
     */
    private function createTemplateWithParagraphs(array $paragraphTexts): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        foreach ($paragraphTexts as $text) {
            $section->addText($text);
        }

        return $this->saveDocx($phpWord);
    }

    private function createSegmentBlockTemplate(): string
    {
        return $this->createTemplateWithParagraphs([
            'Sehr geehrte/r ${'.StatementTemplateValidator::PLACEHOLDER_NAME.'}',
            '${'.StatementTemplateValidator::MARKER_SEGMENTS_OPEN.'}',
            'Punkt ${'.StatementTemplateValidator::PLACEHOLDER_SEGMENT_EXTERN_ID.'}',
            '${'.StatementTemplateValidator::PLACEHOLDER_SEGMENT_TEXT.'}',
            '${'.StatementTemplateValidator::PLACEHOLDER_SEGMENT_RECOMMENDATION.'}',
            '${'.StatementTemplateValidator::MARKER_SEGMENTS_CLOSE.'}',
        ]);
    }

    private function saveDocx(PhpWord $phpWord): string
    {
        $path = $this->reservePath('.docx');
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    private function reservePath(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl_exporter_').$extension;
        $this->temporaryFiles ??= [];
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
