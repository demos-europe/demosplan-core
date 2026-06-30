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

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Controller\Segment\SegmentsExportController;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidStatementTemplateException;
use demosplan\DemosPlanCoreBundle\Exception\MalformedDocxException;
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
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tests {@see SegmentsExportController::exportViaTemplate()} — the controller
 * action that resolves a TUS-uploaded DOCX template, runs it through
 * {@see StatementViaTemplateExporter}, and streams the result back.
 *
 * Each test points the mocked {@see FileService} at the per-test working copy
 * of the public example DOCX (set up by
 * {@see AbstractStatementViaTemplateExporterTestCase}), so the controller runs
 * against a real DOCX without DB/Foundry setup.
 */
class SegmentsExportControllerExportViaTemplateTest extends AbstractStatementViaTemplateExporterTestCase
{
    private const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    protected ?SegmentsExportController $sut = null;

    private ?RequestStack $requestStack = null;
    private (FileService&MockObject)|null $fileService = null;
    private (FileNameGenerator&MockObject)|null $fileNameGenerator = null;
    private (StatementHandler&MockObject)|null $statementHandler = null;
    private (StatementViaTemplateExporter&MockObject)|null $exporter = null;
    private (ProcedureHandler&MockObject)|null $procedureHandler = null;
    private (MessageBagInterface&MockObject)|null $messageBag = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestStack = new RequestStack();
        $this->procedureHandler = $this->createMock(ProcedureHandler::class);
        $this->fileService = $this->createMock(FileService::class);
        $this->fileNameGenerator = $this->createMock(FileNameGenerator::class);
        $this->statementHandler = $this->createMock(StatementHandler::class);
        $this->exporter = $this->createMock(StatementViaTemplateExporter::class);
        $this->messageBag = $this->createMock(MessageBagInterface::class);

        $nameGenerator = $this->createMock(NameGenerator::class);
        $nameGenerator->method('generateDownloadFilename')
            ->willReturnCallback(static fn (string $filename): string => 'attachment; filename="'.$filename.'"');
        $tagFilter = $this->createMock(StatementExportTagFilter::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $this->sut = new SegmentsExportController(
            $nameGenerator,
            $this->procedureHandler,
            $this->requestStack,
            $tagFilter,
            $translator,
        );
        $this->sut->setLogger($this->createMock(LoggerInterface::class));
        $this->sut->setMessageBag($this->messageBag);
    }

    protected function tempFilePrefix(): string
    {
        return 'segments_controller_';
    }

    public function testReturnsStreamedDocxResponseAndDeletesLocalCopyOnSuccess(): void
    {
        $copiedTemplatePath = $this->exampleTemplate;
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

    public function testRedirectsWithMessageBagWhenHashIsMissing(): void
    {
        $this->requestStack->push(new Request([], [], [], [], [], ['HTTP_REFERER' => 'http://localhost/referrer-page']));
        $this->messageBag->expects(self::once())->method('add')->with('error', self::anything());

        $response = $this->callExportViaTemplate();

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testRedirectsWithMessageBagWhenMimeTypeIsNotDocx(): void
    {
        $this->pushRequestWithHash('hash-pdf', 'http://localhost/referrer-page');
        $this->fileService->method('getFileInfo')
            ->with('hash-pdf')
            ->willReturn($this->buildFileInfo('hash-pdf', 'application/pdf'));
        $this->messageBag->expects(self::once())->method('add')->with('error', self::anything());

        $response = $this->callExportViaTemplate();

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testRedirectsWithMessageBagAndDeletesTemplateOnValidatorFailure(): void
    {
        $copiedTemplatePath = $this->exampleTemplate;
        $this->pushRequestWithHash('hash-bad', 'http://localhost/referrer-page');
        $this->givenFileServiceResolves('hash-bad', self::DOCX_MIME, $copiedTemplatePath);
        $this->fileService->expects(self::once())->method('deleteLocalFile')->with($copiedTemplatePath);
        $this->procedureHandler->method('getProcedureWithCertainty')
            ->willReturn($this->createMock(Procedure::class));
        $this->statementHandler->method('getStatementWithCertainty')
            ->willReturn($this->createMock(Statement::class));
        $this->exporter->method('export')
            ->willThrowException(new MalformedDocxException());

        $this->messageBag->expects(self::once())->method('add')->with('error', self::anything());

        $response = $this->callExportViaTemplate();

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testRedirectsWithMessageBagAndDeletesTemplateOnGenericException(): void
    {
        $copiedTemplatePath = $this->exampleTemplate;
        $this->pushRequestWithHash('hash-boom', 'http://localhost/referrer-page');
        $this->givenFileServiceResolves('hash-boom', self::DOCX_MIME, $copiedTemplatePath);
        $this->fileService->expects(self::once())->method('deleteLocalFile')->with($copiedTemplatePath);
        $this->procedureHandler->method('getProcedureWithCertainty')
            ->willReturn($this->createMock(Procedure::class));
        $this->statementHandler->method('getStatementWithCertainty')
            ->willReturn($this->createMock(Statement::class));
        $this->exporter->method('export')->willThrowException(new \Exception('boom'));

        $this->messageBag->expects(self::once())->method('add')->with('error', self::anything());

        $response = $this->callExportViaTemplate();

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    private function callExportViaTemplate(): StreamedResponse|RedirectResponse
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

    private function pushRequestWithHash(string $hash, string $referer = ''): void
    {
        $server = [] !== $referer ? ['HTTP_REFERER' => $referer] : [];
        $this->requestStack->push(new Request(['uploadedDocxTemplate' => $hash], [], [], [], [], $server));
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
}
