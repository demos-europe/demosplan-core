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

use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\ValueObject\Export\StoredExportFile;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Materialises an export response - which is usually streamed - into a stored file, so a background
 * worker can hand the result to a later download request.
 */
class ExportResponseFileStore
{
    /**
     * Large exports must never sit in memory in full, so the body is captured in chunks of this size.
     */
    private const CAPTURE_CHUNK_SIZE = 1024 * 256;

    public function __construct(private readonly FileService $fileService)
    {
    }

    /**
     * @param string $fallbackFileName used when the response carries no Content-Disposition
     *
     * @throws Throwable
     */
    public function store(
        Response $response,
        string $userId,
        ?string $procedureId,
        string $fallbackFileName,
    ): StoredExportFile {
        $tmpPath = tempnam(sys_get_temp_dir(), 'dplan_export_');
        if (false === $tmpPath) {
            throw new RuntimeException('Could not create temporary file for export');
        }

        try {
            $this->captureResponseBody($response, $tmpPath);

            $fileName = $this->resolveFileName($response, $fallbackFileName);
            $fileEntity = $this->fileService->saveTemporaryFile(
                $tmpPath,
                $fileName,
                $userId,
                $procedureId,
                FileService::VIRUSCHECK_NONE
            );

            return new StoredExportFile($fileEntity->getHash(), $fileName);
        } finally {
            // saveTemporaryFile() moves the file on success; on any failure the copy would otherwise
            // stay behind for the lifetime of the worker process.
            (new Filesystem())->remove($tmpPath);
        }
    }

    /**
     * @throws Throwable
     */
    private function captureResponseBody(Response $response, string $tmpPath): void
    {
        $handle = fopen($tmpPath, 'wb');
        if (false === $handle) {
            throw new RuntimeException('Could not open temporary file for export: '.$tmpPath);
        }

        $outerBufferLevel = ob_get_level();
        ob_start(static function (string $buffer) use ($handle): string {
            fwrite($handle, $buffer);

            return '';
        }, self::CAPTURE_CHUNK_SIZE);

        try {
            $response->sendContent();
        } finally {
            // sendContent() runs the whole export for streamed responses, so it may well throw.
            // Discard every buffer it left open, down to the level we started at.
            while (ob_get_level() > $outerBufferLevel) {
                ob_end_clean();
            }
            fclose($handle);
        }
    }

    private function resolveFileName(Response $response, string $fallbackFileName): string
    {
        $disposition = (string) $response->headers->get('Content-Disposition');
        if (1 === preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposition, $matches)) {
            return rawurldecode(trim($matches[1], '"'));
        }

        return $fallbackFileName;
    }
}
