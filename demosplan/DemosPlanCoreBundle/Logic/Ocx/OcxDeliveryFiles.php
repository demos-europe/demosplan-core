<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Ocx;

use demosplan\DemosPlanCoreBundle\Entity\Ocx\OcxDelivery;
use demosplan\DemosPlanCoreBundle\Exception\OcxProblemException;
use demosplan\DemosPlanCoreBundle\Logic\FileInUseChecker;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use Throwable;

/**
 * Files of a delivery: attachments become regular files via {@see FileService}, entries parts are
 * internal data in the default storage.
 */
readonly class OcxDeliveryFiles
{
    public function __construct(
        private FileInUseChecker $fileInUseChecker,
        private FileService $fileService,
        private FilesystemOperator $defaultStorage,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Copies at most one byte more than $limit to a temporary file and hashes on the way.
     *
     * @param resource $body
     *
     * @return array{string, string, int} path, sha256 and number of bytes copied
     *
     * @throws OcxProblemException
     * @throws RandomException
     */
    public function spool($body, int $limit): array
    {
        $path = DemosPlanPath::getTemporaryPath('ocx_'.bin2hex(random_bytes(16)));
        $target = fopen($path, 'wb');
        if (false === $target) {
            throw OcxProblemException::unprocessable('The file could not be stored.');
        }
        $context = hash_init('sha256');
        $size = 0;
        try {
            while ($size <= $limit && !feof($body)) {
                $chunk = fread($body, (int) min(1048576, $limit + 1 - $size));
                if (false === $chunk || '' === $chunk) {
                    break;
                }
                hash_update($context, $chunk);
                fwrite($target, $chunk);
                $size += strlen($chunk);
            }
        } finally {
            fclose($target);
        }

        return [$path, hash_final($context), $size];
    }

    /**
     * @return string the file id
     *
     * @throws OcxProblemException
     */
    public function storeAttachment(OcxDelivery $delivery, string $fileName, string $path): string
    {
        try {
            $file = $this->fileService->saveTemporaryLocalFile(
                $path,
                $this->fileService->sanitizeFileName(basename($fileName)),
                $delivery->getUser()->getId(),
                $delivery->getProcedure()->getId()
            );
        } catch (Throwable $e) {
            $this->logger->warning('OCX file could not be stored', ['deliveryId' => $delivery->getId(), 'exception' => $e::class]);
            throw OcxProblemException::unprocessable('The file could not be stored.');
        }

        return (string) $file->getId();
    }

    /**
     * @return string the storage path
     *
     * @throws OcxProblemException
     */
    public function storePart(OcxDelivery $delivery, int $partIndex, string $body): string
    {
        $path = 'ocx/'.$delivery->getId().'/entries-'.$partIndex.'.json';
        try {
            $this->defaultStorage->write($path, $body);
        } catch (Throwable $e) {
            $this->logger->warning('OCX part could not be stored', ['deliveryId' => $delivery->getId(), 'part' => $partIndex, 'exception' => $e::class]);
            throw OcxProblemException::unprocessable('The part could not be stored.');
        }

        return $path;
    }

    /**
     * @throws Throwable
     */
    public function readPart(string $path): string
    {
        return $this->defaultStorage->read($path);
    }

    /**
     * Deletes everything received for the delivery. With $keepFilesInUse, attachments a statement
     * took over stay.
     */
    public function discardReceived(OcxDelivery $delivery, bool $keepFilesInUse = false): void
    {
        $deliveryId = (string) $delivery->getId();
        $fileIds = array_column($delivery->getReceivedAttachments(), 'fileId');
        if ($keepFilesInUse) {
            $fileIds = array_filter($fileIds, fn (string $fileId): bool => !$this->isFileInUse($deliveryId, $fileId));
        }
        $this->deleteParts($deliveryId, array_column($delivery->getReceivedParts(), 'path'));
        $this->deleteAttachments($deliveryId, $fileIds);
        $delivery->clearReceived();
    }

    /**
     * @param iterable<string> $paths
     */
    public function deleteParts(string $deliveryId, iterable $paths): void
    {
        foreach ($paths as $path) {
            try {
                $this->defaultStorage->delete($path);
            } catch (Throwable $e) {
                $this->logger->warning('OCX part could not be deleted', ['deliveryId' => $deliveryId, 'exception' => $e::class]);
            }
        }
    }

    /**
     * @param iterable<string> $fileIds
     */
    public function deleteAttachments(string $deliveryId, iterable $fileIds): void
    {
        foreach ($fileIds as $fileId) {
            try {
                $this->fileService->deleteFile($fileId);
            } catch (Throwable $e) {
                $this->logger->warning('OCX file could not be deleted', ['deliveryId' => $deliveryId, 'fileId' => $fileId, 'exception' => $e::class]);
            }
        }
    }

    /**
     * Errs on the side of keeping the file.
     */
    private function isFileInUse(string $deliveryId, string $fileId): bool
    {
        try {
            return $this->fileInUseChecker->isFileInUse($fileId);
        } catch (Throwable $e) {
            $this->logger->warning('OCX file use could not be checked', ['deliveryId' => $deliveryId, 'fileId' => $fileId, 'exception' => $e::class]);

            return true;
        }
    }
}
