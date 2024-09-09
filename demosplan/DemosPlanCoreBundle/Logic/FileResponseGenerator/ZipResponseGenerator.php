<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\FileResponseGenerator;

use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Exception\AssessmentTableZipExportException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTablePdfExporter;
use demosplan\DemosPlanCoreBundle\Logic\ZipExportService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;
use PhpOffice\PhpSpreadsheet\Writer\Exception as WriterException;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;
use ZipStream\ZipStream;

use function Symfony\Component\String\u;

class ZipResponseGenerator extends FileResponseGeneratorAbstract
{
    private const FIILE_NOT_FOUND_OR_READABLE = 'Unable to load or read file from path.';
    private const FILE_HASH_INVALID = 'The hash for a file, which should be added to a zip export is not a string,
    is an empty string, equals \'..\' or contains a slash (\'/\').';
    private const UNKOWN_ERROR = 'An error occurred during creation of zip file for export.';
    private const ATTACHMENT_NOT_ADDED = 'error.statments.zip.export.attachment';
    private const ATTACHMENT_GENERIC = 'error.statements.zip.export.generic.attachment';
    private const XLSX_GENERIC = 'error.statements.zip.export.generic.xlsx';
    private const ZIP_NOT_CREATED = 'error.statements.zip.export';
    private array $errorMessages;
    private array $errorCount;

    public function __construct(
        array $supportedTypes,
        NameGenerator $nameGenerator,
        private readonly ZipExportService $zipExportService,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($nameGenerator);
        $this->supportedTypes = $supportedTypes;
        $this->errorMessages = [];
        $this->errorCount = [
            'attachmentNotAddedCount'    => 0,
            'attachmentUnkownErrorCount' => 0,
        ];
    }

    /**
     * @throws AssessmentTableZipExportException
     */
    public function __invoke(array $file): Response
    {
        try {
            self::checkIfNeededArrayKeysExist($file);
        } catch (InvalidArgumentException $e) {
            $this->logger->error($e->getMessage(), $file);
            throw new AssessmentTableZipExportException('error', self::ZIP_NOT_CREATED);
        }

        return $this->createStreamedResponseForZip($file);
    }

    private function createStreamedResponseForZip(array $file): StreamedResponse
    {
        return $this->zipExportService->buildZipStreamResponse(
            $file['zipFileName'].'.zip',
            fn (ZipStream $zipStream) => $this->fillZipWithData($zipStream, $file)
        );
    }

    private function fillZipWithData(ZipStream $zipStream, array $file): void
    {
        $exportType = $file['exportType'];
        if ('statementsWithAttachments' === $exportType) {
            $this->addXlsFileToZip($zipStream, $file);
            $this->addAttachmentsToZip($zipStream, $file);
        }
        if ('originalStatements' === $exportType) {
            $this->addOriginalStatementPdfsTopZip($zipStream, $file);
        }
        $this->addCountedErrorMessages();
        if (0 < count($this->errorMessages)) {
            $this->addErrorTextFile($zipStream);
        }
    }

    private function addOriginalStatementPdfsTopZip(ZipStream $zipStream, array $file): void
    {
        foreach ($file['originalStatementsAsPdfs'] as $pdf) {
            $pdf['name'] = str_replace('Originalstellungnahmen', 'Originalstellungnahme', $pdf['name']);
            $zipStream->addFile(
                $file['zipFileName'].'/'.$pdf['externId'].$pdf['name'],
                $pdf['content']
            );
        }
    }

    private function addXlsFileToZip(ZipStream $zipStream, array $file): void
    {
        $temporaryFullFilePath = DemosPlanPath::getTemporaryPath($file['xlsx']['filename']);
        /** @var IWriter $writer */
        $writer = $file['xlsx']['writer'];
        try {
            $writer->save($temporaryFullFilePath);
            $this->zipExportService->addFileToZipStream(
                $temporaryFullFilePath,
                $file['zipFileName'].'/'.$file['xlsx']['filename'],
                $zipStream
            );
            // uses local file, no need for flysystem
            $fs = new Filesystem();
            $fs->remove($temporaryFullFilePath);
        } catch (FilesystemException $e) {
            $this->handleError($e, self::FIILE_NOT_FOUND_OR_READABLE, self::XLSX_GENERIC);
        } catch (WriterException|Exception $e) {
            $this->handleError($e, self::UNKOWN_ERROR, self::XLSX_GENERIC);
        }
    }

    private function addAttachmentsToZip(ZipStream $zipStream, array $file): void
    {
        foreach ($file['attachments'] as $attachmentsArray) {
            try {
                foreach ($attachmentsArray['attachments'] as $attachment) {
                    $this->zipExportService->addFileToZipStream(
                        $attachment->getFilePathWithHash(),
                        $file['zipFileName'].'/'.$attachment->getHash().'_'.$attachment->getFilename(),
                        $zipStream
                    );
                }
                /**
                 * The originalAttachment can be of type { @see File } if there was a stn attachment already set within
                 * the exported entity. Or it is an array in case the { @see AssessmentTablePdfExporter } got invoked
                 * to generate a pdf of the original-stn.
                 */
                $originalAttachment = $attachmentsArray['originalAttachment'];
                if ($originalAttachment instanceof File) {
                    $this->zipExportService->addFileToZipStream(
                        $originalAttachment->getFilePathWithHash(),
                        $file['zipFileName'].'/'.$originalAttachment->getHash().'_'.$originalAttachment->getFilename(),
                        $zipStream
                    );
                }
                if (is_array($originalAttachment)) {
                    $content = u(
                        $file['zipFileName'].'/'.$originalAttachment['fileHash'].'_'.$originalAttachment['name']
                    )->ascii();
                    $zipStream->addFile(
                        $content->toString(),
                        $originalAttachment['content']
                    );
                }
            } catch (FilesystemException $e) {
                $this->handleError($e, self::FIILE_NOT_FOUND_OR_READABLE);
                ++$this->errorCount['attachmentNotAddedCount'];
            } catch (InvalidDataException $e) {
                $this->handleError($e, self::FILE_HASH_INVALID);
                ++$this->errorCount['attachmentNotAddedCount'];
            } catch (Exception $e) {
                $this->handleError($e, self::UNKOWN_ERROR);
                ++$this->errorCount['attachmentUnkownErrorCount'];
            }
        }
    }

    private function addErrorTextFile(ZipStream $zipStream): void
    {
        $zipStream->addFile(
            'errors.txt',
            implode("\n", $this->errorMessages)
        );
    }

    private function addCountedErrorMessages(): void
    {
        if (0 < $this->errorCount['attachmentNotAddedCount']) {
            $this->errorMessages[] = $this->translator->trans(
                self::ATTACHMENT_NOT_ADDED,
                ['count' => $this->errorCount['attachmentNotAddedCount']]
            );
        }
        if (0 < $this->errorCount['attachmentUnkownErrorCount']) {
            $this->errorMessages[] = $this->translator->trans(
                self::ATTACHMENT_GENERIC,
                ['count' => $this->errorCount['attachmentUnkownErrorCount']]
            );
        }
    }

    private function handleError(Exception $exception, string $logMessage, string $transKey = ''): void
    {
        $this->logger->critical($logMessage, [$exception]);
        if ('' !== $transKey) {
            $this->errorMessages[] = $this->translator->trans($transKey);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function checkIfNeededArrayKeysExist(array $file): void
    {
        $logSuffix = ', in zip response generation.';
        $prefix = 'Array key expected: ';
        Assert::keyExists($file, 'exportType', $prefix.'exportType'.$logSuffix);
        Assert::string($file['exportType'], 'String expected under the key exportType'.$logSuffix);
        $isOriginalStatementsExport = 'originalStatements' === $file['exportType'];
        $isStatementsWithAttachmentsExport = 'statementsWithAttachments' === $file['exportType'];
        Assert::true(
            $isOriginalStatementsExport || $isStatementsWithAttachmentsExport,
            'The exportType must be either originalStatements or statementsWithAttachments'.$logSuffix
        );
        Assert::keyExists($file, 'zipFileName', $prefix.'zipFileName'.$logSuffix);
        if ($isOriginalStatementsExport) {
            Assert::keyExists(
                $file, 'originalStatementsAsPdfs', $prefix.'originalStatementsAsPdfs'.$logSuffix
            );
            Assert::isArray(
                $file['originalStatementsAsPdfs'],
                'Array expected under the key originalStatementsAsPdfs'.$logSuffix
            );
        }
        if ($isStatementsWithAttachmentsExport) {
            Assert::keyExists($file, 'xlsx', $prefix.'xlsx'.$logSuffix);
            Assert::isArray($file['xlsx'], 'Array expected under the key xlsx'.$logSuffix);
            Assert::keyExists($file['xlsx'], 'filename', $prefix.'filename'.$logSuffix);
            Assert::keyExists($file['xlsx'], 'writer', $prefix.'writer'.$logSuffix);
            Assert::keyExists($file['xlsx'], 'statementIds', $prefix.'statementIds'.$logSuffix);
            Assert::keyExists($file, 'attachments', $prefix.'attachments'.$logSuffix);
            Assert::isArray($file['attachments']);
            foreach ($file['attachments'] as $attachment) {
                Assert::keyExists($attachment, 'attachments');
                Assert::keyExists($attachment, 'originalAttachment');
            }
        }
    }
}
