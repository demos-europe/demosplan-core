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
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
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
use Tests\Base\UnitTestCase;
use ZipArchive;

class StatementViaTemplateExporterTest extends UnitTestCase
{
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
            $htmlHelper
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
            'Sehr geehrte/r ${submitterName}',
            'Ihre Einwendung: ${statementExternId}',
            'Datum: ${todayDate}',
        ]);
        $this->validator->method('validate')->with($templatePath)->willReturn(null);
        $this->dataBuilder->method('build')->willReturn($this->buildData([
            'submitterName'     => 'Maria Mustermann',
            'statementExternId' => 'E0042',
            'todayDate'         => '18.05.2026',
        ]));

        $resultPath = $this->renderToFile($templatePath);

        self::assertSame([], $this->getRemainingVariables($resultPath));
        $bodyText = $this->extractBodyText($resultPath);
        self::assertStringContainsString('Maria Mustermann', $bodyText);
        self::assertStringContainsString('E0042', $bodyText);
        self::assertStringContainsString('18.05.2026', $bodyText);
    }

    public function testRendersAsParagraphsModeWithMultipleSegments(): void
    {
        $templatePath = $this->createParagraphsModeTemplate();
        $this->validator->method('validate')->with($templatePath)
            ->willReturn(StatementTemplateValidator::MODE_AS_PARAGRAPHS);
        $this->dataBuilder->method('build')->willReturn($this->buildData(
            ['submitterName' => 'Maria Mustermann'],
            [
                $this->makeSegment(
                    'M12-1',
                    'Sektion A',
                    '<p>Erstes Vorbringen</p>',
                    '<p>Erste Erwiderung</p><p>Mit zweiter Zeile.</p>'
                ),
                $this->makeSegment('M12-2', 'Sektion B', '<p>Zweites Vorbringen</p>', '<p>Zweite Erwiderung</p>'),
            ]
        ));

        $resultPath = $this->renderToFile($templatePath);

        self::assertSame([], $this->getRemainingVariables($resultPath));
        $bodyText = $this->extractBodyText($resultPath);
        self::assertStringContainsString('Maria Mustermann', $bodyText);
        self::assertStringContainsString('M12-1', $bodyText);
        self::assertStringContainsString('M12-2', $bodyText);
        self::assertStringContainsString('Erstes Vorbringen', $bodyText);
        self::assertStringContainsString('Zweite Erwiderung', $bodyText);
    }

    public function testRendersWithinTableModeWithMultipleSegments(): void
    {
        $templatePath = $this->createWithinTableModeTemplate();
        $this->validator->method('validate')->with($templatePath)
            ->willReturn(StatementTemplateValidator::MODE_WITHIN_TABLE);
        $this->dataBuilder->method('build')->willReturn($this->buildData(
            ['submitterName' => 'Maria Mustermann'],
            [
                $this->makeSegment('M12-1', 'Sektion A', 'Erstes Vorbringen', 'Erste Erwiderung'),
                $this->makeSegment('M12-2', 'Sektion B', 'Zweites Vorbringen', 'Zweite Erwiderung'),
            ]
        ));

        $resultPath = $this->renderToFile($templatePath);

        self::assertSame([], $this->getRemainingVariables($resultPath));
        $bodyText = $this->extractBodyText($resultPath);
        self::assertStringContainsString('M12-1', $bodyText);
        self::assertStringContainsString('M12-2', $bodyText);
        self::assertStringContainsString('Erstes Vorbringen', $bodyText);
        self::assertStringContainsString('Zweite Erwiderung', $bodyText);
        self::assertStringNotContainsString('segmentsWithinTable', $bodyText);
    }

    public function testReplacesNullSimpleFieldsWithEmptyStrings(): void
    {
        $templatePath = $this->createTemplateWithParagraphs([
            'Name: ${submitterName}',
            'E-Mail: ${submitterEmail}',
        ]);
        $this->validator->method('validate')->willReturn(null);
        $this->dataBuilder->method('build')->willReturn($this->buildData([
            'submitterName'  => 'Maria Mustermann',
            'submitterEmail' => null,
        ]));

        $resultPath = $this->renderToFile($templatePath);

        self::assertSame([], $this->getRemainingVariables($resultPath));
        $bodyText = $this->extractBodyText($resultPath);
        self::assertStringContainsString('Maria Mustermann', $bodyText);
        self::assertStringContainsString('E-Mail: ', $bodyText);
    }

    public function testPropagatesValidatorExceptionWithoutTouchingTheTemplate(): void
    {
        $templatePath = $this->createTemplateWithParagraphs(['${submitterName}']);
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
            'submitterName'       => '',
            'submitterOrgaName'   => '',
            'submitterStreet'     => '',
            'submitterPostalCode' => '',
            'submitterCity'       => '',
            'submitterEmail'      => '',
            'statementExternId'   => '',
            'statementSubmitDate' => '',
            'procedureName'       => '',
            'procedureExternId'   => '',
            'todayDate'           => '',
            'planningAgencyName'  => '',
            'planner'             => '',
        ];
        $values = array_merge($defaults, $simpleValues);

        $data = new StatementTemplateData();
        $data->setSubmitterName($values['submitterName']);
        $data->setSubmitterOrgaName($values['submitterOrgaName']);
        $data->setSubmitterStreet($values['submitterStreet']);
        $data->setSubmitterPostalCode($values['submitterPostalCode']);
        $data->setSubmitterCity($values['submitterCity']);
        $data->setSubmitterEmail($values['submitterEmail']);
        $data->setStatementExternId($values['statementExternId']);
        $data->setStatementSubmitDate($values['statementSubmitDate']);
        $data->setProcedureName($values['procedureName']);
        $data->setProcedureExternId($values['procedureExternId']);
        $data->setTodayDate($values['todayDate']);
        $data->setPlanningAgencyName($values['planningAgencyName']);
        $data->setPlanner($values['planner']);
        $data->setSegments($segments);
        $data->lock();

        return $data;
    }

    private function makeSegment(string $externId, string $placeName, string $text, string $recommendation): Segment&MockObject
    {
        $place = $this->createMock(Place::class);
        $place->method('getName')->willReturn($placeName);

        $segment = $this->createMock(Segment::class);
        $segment->method('getExternId')->willReturn($externId);
        $segment->method('getPlace')->willReturn($place);
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

    private function createParagraphsModeTemplate(): string
    {
        return $this->createTemplateWithParagraphs([
            'Sehr geehrte/r ${submitterName}',
            '${segmentsAsParagraphs}',
            'Punkt ${segmentExternId} — ${segmentPlace}',
            '${segmentText}',
            '${segmentRecommendation}',
            '${/segmentsAsParagraphs}',
        ]);
    }

    private function createWithinTableModeTemplate(): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Sehr geehrte/r ${submitterName}');
        $table = $section->addTable();
        $row = $table->addRow();
        $row->addCell()->addText('${segmentsWithinTable}${segmentExternId} — ${segmentPlace}');
        $row->addCell()->addText('${segmentText}');
        $row->addCell()->addText('${segmentRecommendation}');

        return $this->saveDocx($phpWord);
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
