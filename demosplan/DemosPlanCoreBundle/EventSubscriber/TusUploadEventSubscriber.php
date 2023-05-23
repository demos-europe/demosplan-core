<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use TusPhp\File;
use demosplan\DemosPlanCoreBundle\Application\Header;
use demosplan\DemosPlanCoreBundle\Exception\VirusFoundException;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;
use TusPhp\Cache\FileStore;
use TusPhp\Events\UploadComplete;

/**
 * Hook into the Tus events for upload processing.
 *
 * Tus is the [transload-it upload specification](https://tus.io)
 * which our uploader component in the frontend (Uppy) uses
 * to process files in chunks.
 */
class TusUploadEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var FileService
     */
    private $fileService;

    /**
     * @var FileStore
     */
    private $fileStore;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(FileService $fileService, FileStore $fileStore, LoggerInterface $logger)
    {
        $this->fileService = $fileService;
        $this->logger = $logger;
        $this->fileStore = $fileStore;
    }

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            UploadComplete::NAME => 'onUploadComplete',
        ];
    }

    public function onUploadComplete(UploadComplete $completedEvent): void
    {
        /**
         * @var File
         */
        $file = $completedEvent->getFile();
        $request = $completedEvent->getRequest();
        $response = $completedEvent->getResponse();
        $filename = $this->fileService->sanitizeFileName($file->getName());

        // rename file when filename contains invalid chars
        $filePathParts = explode('/', $file->getFilePath());
        $uploadedFilename = array_pop($filePathParts);
        if ($filename !== $uploadedFilename) {
            $fs = new Filesystem();
            $filePathParts[] = $filename;
            $sanitizedPath = implode('/', $filePathParts);
            // rename uploaded file and update reference in uploaded file
            // in order to avoid dangling file data is prevented renaming, simply allow overwriting.
            $fs->rename($file->getFilePath(), $sanitizedPath, true);
            $file->setFilePath($sanitizedPath);
        }

        $procedureId = $request->getRequest()->headers->get(Header::PROCEDURE_ID);

        $fileHash = $this->fileService->createHash();

        try {
            $checkVirus = FileService::VIRUSCHECK_ASYNC;
            $this->logger->info('Try to save temporary file', [$file->getFilePath()]);
            $fileId = $this->fileService->saveTemporaryFile(
                $file->getFilePath(),
                $filename,
                null,
                $procedureId,
                $checkVirus,
                $fileHash
            )->getId();
        } catch (VirusFoundException $e) {
            $this->logger->error('Virus found in File ', [$e]);
            $fileId = '';
        } catch (Throwable $e) {
            $this->logger->error('Could not save uploaded File ', [$e]);
            $fileId = '';
        }

        // we handled the file and can allow tus to have the same file uploaded again
        // this is not exactly ideal as we're technically wasting bandwidth but oh well
        $this->fileStore->delete($file->getKey());
        $this->logger->info('Uploaded File processed. Hash: '.$fileId);

        $headers = $response->getHeaders();
        $headers[Header::FILE_ID] = $fileId;
        $headers[Header::FILE_HASH] = $fileHash;
        $response->setHeaders($headers);
    }
}
