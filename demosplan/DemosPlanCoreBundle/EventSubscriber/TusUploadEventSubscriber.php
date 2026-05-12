<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Application\Header;
use demosplan\DemosPlanCoreBundle\Exception\VirusFoundException;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;
use TusPhp\Events\UploadComplete;
use TusPhp\File;

/**
 * Hook into the Tus events for upload processing.
 *
 * Tus is the [transload-it upload specification](https://tus.io)
 * which our uploader component in the frontend (Uppy) uses
 * to process files in chunks.
 * This class will be called when the route /_tus/upload is called
 */
class TusUploadEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly LoggerInterface $logger,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $tusFileIdCache,
    ) {
    }

    private const TUS_PATH_PREFIX = '/_tus/upload/';
    private const RECOVERY_TTL_SECONDS = 1800;
    private const RECOVERY_KEY_PREFIX = 'tus_recovery.';

    /**
     * @return array<string,string|array<int,string|int>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            UploadComplete::NAME    => 'onUploadComplete',
            KernelEvents::RESPONSE  => 'onKernelResponse',
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
            // local file is valid, no need for flysystem
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

        $fileId = '';
        $tusKey = $file->getKey();
        try {
            $checkVirus = FileService::VIRUSCHECK_ASYNC;
            $this->logger->info('Try to save temporary file', [$file->getFilePath()]);
            $fileId = $this->fileService->persistAndStoreLocalFile(
                $file->getFilePath(),
                $filename,
                null,
                $procedureId,
                $checkVirus,
                $fileHash,
                // Persist the resolved ids on the tus cache entry as soon as the File
                // entity has been created, *before* the long-running virus scan and
                // flysystem move. This way a client whose connection died during those
                // slow ops can still recover the FILE_ID/HASH headers on a retry HEAD
                // via onKernelResponse below.
                function (\demosplan\DemosPlanCoreBundle\Entity\File $persisted) use ($tusKey, $fileHash): void {
                    $this->storeRecovery($tusKey, (string) $persisted->getId(), $fileHash);
                },
            )->getId();
        } catch (VirusFoundException $e) {
            $this->logger->error('Virus found in File ', [$e]);
            $fileId = $fileHash = '';
        } catch (Throwable $e) {
            $this->logger->error('Could not save uploaded File ', [$e]);
            $fileId = $fileHash = '';
        }

        // Re-write to make sure the recovery cache reflects the final state — in
        // particular it overwrites the early hint when the slow ops failed and the
        // entity was rolled back, so retries don't pick up a stale id.
        $this->storeRecovery($tusKey, $fileId, $fileHash);
        $this->logger->info('Uploaded File processed. Hash: '.$fileId);

        $headers = $response->getHeaders();
        $headers[Header::FILE_ID] = $fileId;
        $headers[Header::FILE_HASH] = $fileHash;
        $response->setHeaders($headers);
    }

    /**
     * Inject the persisted FILE_ID/FILE_HASH headers on any tus response for an
     * already-completed upload. Lets the client recover the ids on a retry HEAD/PATCH
     * after the original response was lost mid-flight.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (!str_starts_with($path, self::TUS_PATH_PREFIX)) {
            return;
        }

        $key = substr($path, strlen(self::TUS_PATH_PREFIX));
        if ('' === $key || str_contains($key, '/')) {
            return;
        }

        $entry = $this->loadRecovery($key);
        if (null === $entry || '' === $entry[Header::FILE_ID]) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set(Header::FILE_ID, $entry[Header::FILE_ID]);
        $response->headers->set(Header::FILE_HASH, $entry[Header::FILE_HASH]);
    }

    /**
     * @return array{x-demosplan-file-id: string, x-demosplan-file-hash: string}|null
     */
    private function loadRecovery(string $tusKey): ?array
    {
        try {
            $item = $this->tusFileIdCache->getItem(self::RECOVERY_KEY_PREFIX.$tusKey);
        } catch (Throwable $e) {
            $this->logger->warning('tus recovery cache lookup failed', ['key' => $tusKey, 'exception' => $e]);

            return null;
        }
        if (!$item->isHit()) {
            return null;
        }
        $value = $item->get();

        return is_array($value) ? $value : null;
    }

    private function storeRecovery(string $tusKey, string $fileId, string $fileHash): void
    {
        try {
            $item = $this->tusFileIdCache->getItem(self::RECOVERY_KEY_PREFIX.$tusKey);
            $item->set([
                Header::FILE_ID   => $fileId,
                Header::FILE_HASH => $fileHash,
            ]);
            $item->expiresAfter(self::RECOVERY_TTL_SECONDS);
            $this->tusFileIdCache->save($item);
        } catch (Throwable $e) {
            $this->logger->warning('tus recovery cache write failed', ['key' => $tusKey, 'exception' => $e]);
        }
    }
}
