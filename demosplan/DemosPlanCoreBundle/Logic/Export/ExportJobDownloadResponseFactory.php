<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use demosplan\DemosPlanCoreBundle\Entity\Export\AsyncExportJobInterface;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Response\StreamedFileOutput;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the result a background export stored, for the download request that follows the polling.
 *
 * The file is streamed rather than read into memory: an export archive can be several hundred
 * megabytes, which is what moved the export into the background in the first place.
 */
class ExportJobDownloadResponseFactory
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly FilesystemOperator $defaultStorage,
        private readonly FilesystemOperator $localStorage,
    ) {
    }

    /**
     * @return StreamedResponse|null null when the result is no longer available - the maintenance
     *                               sweep removes the files of expired jobs
     *
     * @throws FilesystemException
     */
    public function createForJob(AsyncExportJobInterface $job): ?StreamedResponse
    {
        $fileHash = $job->getFileHash();
        if (null === $fileHash) {
            return null;
        }

        $fileInfo = $this->fileService->getFileInfo($fileHash);
        $storage = $this->resolveStorage($fileInfo->getAbsolutePath());
        if (!$storage instanceof FilesystemOperator) {
            return null;
        }

        $stream = $storage->readStream($fileInfo->getAbsolutePath());
        $response = new StreamedResponse(static fn () => StreamedFileOutput::sendAndClose($stream));
        $response->headers->set('Content-Type', 'application/octet-stream');
        // Declared up front so the browser can show real progress for an archive that takes a while
        // to arrive; the body is sent in chunks, but its total size is known here.
        $response->headers->set('Content-Length', (string) $storage->fileSize($fileInfo->getAbsolutePath()));
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="'.($job->getFileName() ?? $fileInfo->getFileName()).'"'
        );

        return $response;
    }

    /**
     * @throws FilesystemException
     */
    private function resolveStorage(string $absolutePath): ?FilesystemOperator
    {
        if ($this->defaultStorage->fileExists($absolutePath)) {
            return $this->defaultStorage;
        }

        if ($this->localStorage->fileExists($absolutePath)) {
            return $this->localStorage;
        }

        return null;
    }
}
