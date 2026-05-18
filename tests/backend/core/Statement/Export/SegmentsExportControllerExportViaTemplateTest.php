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

use demosplan\DemosPlanCoreBundle\Controller\Segment\SegmentsExportController;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidStatementTemplateException;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\FileNameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementExportTagFilter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementViaTemplateExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\Base\UnitTestCase;

/**
 * Tests {@see SegmentsExportController::exportViaTemplate()} — the controller
 * action that resolves a TUS-uploaded DOCX template, runs it through
 * {@see StatementViaTemplateExporter}, and streams the result back.
 *
 * Each test copies the fixture template in `res/` to a tempfile and points
 * the mocked {@see FileService} at it, so the controller runs against a real
 * DOCX without DB/Foundry setup.
 */
class SegmentsExportControllerExportViaTemplateTest extends UnitTestCase
{
    private const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    private const FIXTURE_PARAGRAPHS = __DIR__.'/res/statement_template_example_paragraphs.docx';
    private const FIXTURE_TABLE = __DIR__.'/res/statement_template_example_table.docx';

    protected ?SegmentsExportController $sut = null;

    private ?RequestStack $requestStack = null;
    private (FileService&MockObject)|null $fileService = null;
    private (FileNameGenerator&MockObject)|null $fileNameGenerator = null;
    private (StatementHandler&MockObject)|null $statementHandler = null;
    private (StatementViaTemplateExporter&MockObject)|null $exporter = null;
    private (ProcedureHandler&MockObject)|null $procedureHandler = null;

    /**
     * @var list<string>|null
     */
    private ?array $temporaryFiles = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryFiles = [];

        $this->requestStack = new RequestStack();
        $this->procedureHandler = $this->createMock(ProcedureHandler::class);
        $this->fileService = $this->createMock(FileService::class);
        $this->fileNameGenerator = $this->createMock(FileNameGenerator::class);
        $this->statementHandler = $this->createMock(StatementHandler::class);
        $this->exporter = $this->createMock(StatementViaTemplateExporter::class);

        $nameGenerator = $this->createMock(NameGenerator::class);
        $nameGenerator->method('generateDownloadFilename')
            ->willReturnCallback(static fn (string $filename): string => 'attachment; filename="'.$filename.'"');
        $tagFilter = $this->createMock(StatementExportTagFilter::class);

        $this->sut = new SegmentsExportController(
            $nameGenerator,
            $this->procedureHandler,
            $this->requestStack,
            $tagFilter,
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

    /**
     * @dataProvider exampleTemplateProvider
     */
    public function testReturnsStreamedDocxResponseAndDeletesLocalCopyOnSuccess(string $fixturePath): void
    {
        $copiedTemplatePath = $this->copyFixtureTemplate($fixturePath);
        $this->pushRequestWithHash('hash-success');
        $this->givenFileServiceResolves('hash-success', self::DOCX_MIME, $copiedTemplatePath);
        $this->fileService->expects(self::once())->method('deleteLocalFile')->with($copiedTemplatePath);

        $this->procedureHandler->method('getProcedureWithCertainty')
            ->willReturn($this->createMock(Procedure::class));
        $this->statementHandler->method('getStatementWithCertainty')
            ->willReturn($this->createMock(Statement::class));
        $this->fileNameGenerator->method('getFileName')->willReturn('m12-mustermann');
        $this->exporter->method('export')->willReturn(new TemplateProcessor($copiedTemplatePath));

        $response = $this->callExportViaTemplate();

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame(
            self::DOCX_MIME.'; charset=utf-8',
            $response->headers->get('Content-Type')
        );
        self::assertStringContainsString(
            'm12-mustermann.docx',
            (string) $response->headers->get('Content-Disposition')
        );

        // Exercise the streaming closure so the deleteLocalFile() expectation
        // in the `finally` actually fires.
        ob_start();
        $response->sendContent();
        ob_end_clean();
    }

    public function testThrowsBadRequestWhenHashIsMissing(): void
    {
        $this->requestStack->push(new Request());

        $this->expectException(BadRequestHttpException::class);
        $this->callExportViaTemplate();
    }

    public function testThrowsBadRequestWhenMimeTypeIsNotDocx(): void
    {
        $this->pushRequestWithHash('hash-pdf');
        $this->fileService->method('getFileInfo')
            ->with('hash-pdf')
            ->willReturn($this->buildFileInfo('hash-pdf', 'application/pdf'));

        $this->expectException(BadRequestHttpException::class);
        $this->callExportViaTemplate();
    }

    public function testReturnsUnprocessableEntityAndDeletesTemplateOnValidatorFailure(): void
    {
        $copiedTemplatePath = $this->copyFixtureTemplate();
        $this->pushRequestWithHash('hash-bad');
        $this->givenFileServiceResolves('hash-bad', self::DOCX_MIME, $copiedTemplatePath);
        $this->fileService->expects(self::once())->method('deleteLocalFile')->with($copiedTemplatePath);
        $this->procedureHandler->method('getProcedureWithCertainty')
            ->willReturn($this->createMock(Procedure::class));
        $this->statementHandler->method('getStatementWithCertainty')
            ->willReturn($this->createMock(Statement::class));
        $this->exporter->method('export')
            ->willThrowException(new InvalidStatementTemplateException('Unknown placeholder: ${oops}'));

        try {
            $this->callExportViaTemplate();
            self::fail('Expected UnprocessableEntityHttpException');
        } catch (UnprocessableEntityHttpException $exception) {
            self::assertSame('Unknown placeholder: ${oops}', $exception->getMessage());
        }
    }

    public function testRethrowsAndDeletesTemplateOnGenericException(): void
    {
        $copiedTemplatePath = $this->copyFixtureTemplate();
        $this->pushRequestWithHash('hash-boom');
        $this->givenFileServiceResolves('hash-boom', self::DOCX_MIME, $copiedTemplatePath);
        $this->fileService->expects(self::once())->method('deleteLocalFile')->with($copiedTemplatePath);
        $this->procedureHandler->method('getProcedureWithCertainty')
            ->willReturn($this->createMock(Procedure::class));
        $this->statementHandler->method('getStatementWithCertainty')
            ->willReturn($this->createMock(Statement::class));
        $this->exporter->method('export')->willThrowException(new RuntimeException('boom'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $this->callExportViaTemplate();
    }

    private function callExportViaTemplate(): StreamedResponse
    {
        return $this->sut->exportViaTemplate(
            $this->fileService,
            $this->fileNameGenerator,
            $this->statementHandler,
            $this->exporter,
            'procedure-id',
            'statement-id',
        );
    }

    private function pushRequestWithHash(string $hash): void
    {
        $this->requestStack->push(new Request(['uploadedDocxTemplate' => $hash]));
    }

    private function givenFileServiceResolves(string $hash, string $contentType, string $localPath): void
    {
        $this->fileService->method('getFileInfo')
            ->with($hash)
            ->willReturn($this->buildFileInfo($hash, $contentType));
        $this->fileService->method('ensureLocalFileFromHash')
            ->with($hash)
            ->willReturn($localPath);
    }

    private function buildFileInfo(string $hash, string $contentType): FileInfo
    {
        return new FileInfo(
            $hash,
            'uploaded_template.docx',
            1024,
            $contentType,
            'storage/'.$hash,
            '/tmp/'.$hash,
            null,
        );
    }

    private function copyFixtureTemplate(string $sourcePath = self::FIXTURE_PARAGRAPHS): string
    {
        self::assertFileExists($sourcePath);

        $destination = tempnam(sys_get_temp_dir(), 'segments_controller_').'.docx';
        copy($sourcePath, $destination);
        $this->temporaryFiles ??= [];
        $this->temporaryFiles[] = $destination;

        return $destination;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function exampleTemplateProvider(): array
    {
        return [
            'paragraphs mode' => [self::FIXTURE_PARAGRAPHS],
            'table mode'      => [self::FIXTURE_TABLE],
        ];
    }
}
