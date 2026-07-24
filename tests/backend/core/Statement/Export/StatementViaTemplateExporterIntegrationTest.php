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
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementTemplateDataBuilder;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementTemplateValidator;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementViaTemplateExporter;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementTemplateData;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
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
class StatementViaTemplateExporterIntegrationTest extends AbstractStatementViaTemplateExporterTestCase
{
    private const EXAMPLE_IMAGE_SUBPATH = 'tests/backend/core/ExternalFileSaver/Functional/fff.png';
    private const IMAGE_HASH = '0123456789abcdef0123456789abcdef';

    protected ?StatementViaTemplateExporter $sut = null;

    private (StatementTemplateDataBuilder&MockObject)|null $dataBuilder = null;
    private (FileService&MockObject)|null $fileService = null;

    protected function setUp(): void
    {
        parent::setUp();

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(static fn (?string $id, array $parameters = []): string => (string) $id);

        $validator = new StatementTemplateValidator();
        $htmlHelper = $this->getContainer()->get(HtmlHelper::class);

        $this->dataBuilder = $this->createMock(StatementTemplateDataBuilder::class);
        $this->fileService = $this->createMock(FileService::class);

        $this->sut = new StatementViaTemplateExporter($validator, $this->dataBuilder, $htmlHelper, $this->createMock(LoggerInterface::class), $translator, $this->fileService);
    }

    protected function tempFilePrefix(): string
    {
        return 'tpl_integration_';
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

    public function testEmbedsImageFromSegmentHtml(): void
    {
        $this->fileService->method('ensureLocalFileFromHash')
            ->with(self::IMAGE_HASH)
            ->willReturn(DemosPlanPath::getRootPath(self::EXAMPLE_IMAGE_SUBPATH));

        $this->dataBuilder->method('build')->willReturn($this->buildData([
            $this->makeSegment(
                'M42-1',
                '<p>Vorbringen mit Bild.</p><img src="/file/PROC-1/'.self::IMAGE_HASH.'" alt="Foto">',
                '<p>Erwiderung.</p>'
            ),
        ]));

        $resultPath = $this->renderToFile();

        self::assertSame([], $this->getRemainingVariables($resultPath));
        self::assertSame(1, $this->countEmbeddedImages($resultPath));
        self::assertStringNotContainsString('export.image.load.error', $this->extractBodyText($resultPath));
    }

    public function testFallsBackToPlaceholderTextWhenImageFileCannotBeResolved(): void
    {
        $this->fileService->method('ensureLocalFileFromHash')
            ->with(self::IMAGE_HASH)
            ->willThrowException(new Exception('File not Found'));

        $this->dataBuilder->method('build')->willReturn($this->buildData([
            $this->makeSegment(
                'M42-1',
                '<p>Vorbringen mit Bild.</p><img src="/file/PROC-1/'.self::IMAGE_HASH.'" alt="Foto">',
                '<p>Erwiderung.</p>'
            ),
        ]));

        $resultPath = $this->renderToFile();

        self::assertSame([], $this->getRemainingVariables($resultPath));
        self::assertSame(0, $this->countEmbeddedImages($resultPath));
        self::assertStringContainsString('export.image.load.error', $this->extractBodyText($resultPath));
    }

    private function countEmbeddedImages(string $absolutePath): int
    {
        $zip = new ZipArchive();
        self::assertSame(true, $zip->open($absolutePath));
        $count = 0;
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            if (str_starts_with($zip->getNameIndex($i), 'word/media/')) {
                ++$count;
            }
        }
        $zip->close();

        return $count;
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

    private function renderToFile(): string
    {
        $templateProcessor = $this->sut->export(
            $this->createMock(Procedure::class),
            $this->createMock(Statement::class),
            $this->exampleTemplate
        );
        $resultPath = $this->reservePath('.docx');
        $templateProcessor->saveAs($resultPath);

        return $resultPath;
    }
}
