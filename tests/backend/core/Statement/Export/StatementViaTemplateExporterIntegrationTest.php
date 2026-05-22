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
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementTemplateDataBuilder;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementTemplateValidator;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementViaTemplateExporter;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementTemplateData;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\UnitTestCase;
use ZipArchive;

/**
 * End-to-end test of the via-template export against the committed example
 * fixture. The unit tests mock either the validator or the data builder —
 * this one wires up the REAL validator + REAL exporter + REAL HtmlHelper
 * against the REAL fixture DOCX, with segment HTML that mirrors what the
 * rich-text editor actually produces (`<p>`-wrapped paragraphs). Only the
 * data builder stays mocked so we don't need DB / Foundry setup.
 *
 * Catches regression classes that slipped past the mock-heavy unit tests
 * during DPLAN-17476's smoke test:
 *   - illegal placeholders in the committed example DOCX;
 *   - PhpWord "Cannot add TextRun in TextRun" when segments contain
 *     paragraph-level HTML.
 */
class StatementViaTemplateExporterIntegrationTest extends UnitTestCase
{
    private const FIXTURE = __DIR__.'/res/statement_template_example.docx';

    protected ?StatementViaTemplateExporter $sut = null;

    private (StatementTemplateDataBuilder&MockObject)|null $dataBuilder = null;

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
            ->willReturnCallback(static fn (?string $id, array $parameters = []): string => (string) $id);

        $validator = new StatementTemplateValidator($translator);
        $htmlHelper = $this->getContainer()->get(HtmlHelper::class);

        $this->dataBuilder = $this->createMock(StatementTemplateDataBuilder::class);

        $this->sut = new StatementViaTemplateExporter($validator, $this->dataBuilder, $htmlHelper);
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

    public function testRendersCommittedFixtureWithHtmlSegments(): void
    {
        $this->dataBuilder->method('build')->willReturn($this->buildData([
            $this->makeSegment(
                'M42-1',
                '<p>Erstes Vorbringen mit <strong>fettem</strong> Text.</p><p>Mit zweiter Zeile.</p>',
                '<p>Erste Erwiderung.</p>'
            ),
            $this->makeSegment('M42-2', '<p>Zweites Vorbringen.</p>', '<p>Zweite Erwiderung.</p>'),
        ]));

        $resultPath = $this->renderToFile();

        self::assertSame([], $this->getRemainingVariables($resultPath));
        $bodyText = $this->extractBodyText($resultPath);
        self::assertStringContainsString('Erstes Vorbringen mit', $bodyText);
        self::assertStringContainsString('Mit zweiter Zeile.', $bodyText);
        self::assertStringContainsString('M42-2', $bodyText);
        self::assertStringContainsString('Zweite Erwiderung.', $bodyText);
    }

    /**
     * @param list<Segment&MockObject> $segments
     */
    private function buildData(array $segments): StatementTemplateData
    {
        $data = new StatementTemplateData();
        $data->setSubmitterName('Maria Mustermann');
        $data->setSubmitterOrgaName('Musterfirma GmbH');
        $data->setSubmitterStreet('Musterstraße');
        $data->setSubmitterHouseNumber('1');
        $data->setSubmitterPostalCode('12345');
        $data->setSubmitterCity('Musterstadt');
        $data->setStatementExternId('M42');
        $data->setStatementInternId('E0042');
        $data->setProcedureName('Testverfahren');
        $data->setTodayDate('18.05.2026');
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

    private function renderToFile(): string
    {
        $copiedPath = $this->copyFixture(self::FIXTURE);
        $templateProcessor = $this->sut->export(
            $this->createMock(Procedure::class),
            $this->createMock(Statement::class),
            $copiedPath
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

    private function copyFixture(string $sourcePath): string
    {
        self::assertFileExists($sourcePath);
        $destination = $this->reservePath('.docx');
        copy($sourcePath, $destination);

        return $destination;
    }

    private function reservePath(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl_integration_').$extension;
        $this->temporaryFiles ??= [];
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
