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
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\ZipExportService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use PhpOffice\PhpSpreadsheet\Writer\Exception as WriterException;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use ZipStream\Exception\FileNotFoundException;
use ZipStream\Exception\FileNotReadableException;
use ZipStream\ZipStream;

class ZipResponseGenerator extends FileResponseGeneratorAbstract
{
    private const FIILE_NOT_FOUND_OR_READABLE = 'Unable to load or read file from path.';
    private const FILE_HASH_INVALID = 'The hash for a file, which should be added to a zip export is not a string,
    is an empty string, equals \'..\' or contains a slash (\'/\').';
    private const UNKOWN_ERROR = 'An error occurred during creation of zip file for export.';
    private const ATTACHMENT_NOT_ADDED = 'error.statments.zip.export.attachment';
    private const ATTACHMENT_GENERIC = 'error.statements.zip.export.generic.attachment';
    private const XLSX_GENERIC = 'error.statements.zip.export.generic.xlsx';
    private array $errorMessages;
    private array $errorCount;

    public function __construct(
        array $supportedTypes,
        NameGenerator $nameGenerator,
        private readonly ZipExportService $zipExportService,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator
    ) {
        parent::__construct($nameGenerator);
        $this->supportedTypes = $supportedTypes;
        $this->errorMessages = [];
        $this->errorCount = [
            'attachmentNotAddedCount' => 0,
            'attachmentUnkownErrorCount' => 0,
        ];
    }

    public function __invoke(array $file): Response
    {
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
        } catch (FileNotFoundException|FileNotReadableException $e) {
            $this->handleError($e, self::FIILE_NOT_FOUND_OR_READABLE, self::XLSX_GENERIC);
        } catch (WriterException|Exception $e) {
            $this->handleError($e, self::UNKOWN_ERROR, self::XLSX_GENERIC);
        }

        foreach ($file['attachments'] as $attachmentArray) {
            /** @var File $attachment */
            foreach ($attachmentArray as $attachment) {
                try {
                    $this->zipExportService->addFileToZipStream(
                        $attachment->getFilePathWithHash(),
                        $file['zipFileName'].'/'.$attachment->getHash().'_'.$attachment->getFilename(),
                        $zipStream
                    );
                } catch (FileNotFoundException|FileNotReadableException $e) {
                    $this->handleError($e, self::FIILE_NOT_FOUND_OR_READABLE);
                    $this->errorCount['attachmentNotAddedCount']++;
                } catch (InvalidDataException $e) {
                    $this->handleError($e, self::FILE_HASH_INVALID);
                    $this->errorCount['attachmentNotAddedCount']++;
                } catch (Exception $e) {
                    $this->handleError($e, self::UNKOWN_ERROR);
                    $this->errorCount['attachmentUnkownErrorCount']++;
                }
            }
        }
        $this->addCountedErrorMessages();
        if (0 < count($this->errorMessages)) {
            $this->addErrorTextFile($zipStream);
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
}
