<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\FileInterface;
use DemosEurope\DemosplanAddon\Contracts\FileServiceInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\TimeoutException;
use demosplan\DemosPlanCoreBundle\Exception\VirusFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\FileContainerRepository;
use demosplan\DemosPlanCoreBundle\Repository\FileRepository;
use demosplan\DemosPlanCoreBundle\Repository\SingleDocumentRepository;
use demosplan\DemosPlanCoreBundle\Tools\VirusCheckInterface;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Faker\Provider\Uuid;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToCopyFile;
use OldSound\RabbitMqBundle\RabbitMq\RpcClient;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class FileService extends CoreService implements FileServiceInterface
{
    protected $container;
    protected $baseGetURL;

    final public const INVALID_FILENAME_CHARS = ['%', '&', ':'];

    /**
     * Die Datei wird nach dem Upload zum FileService nicht direkt auf Viren geprüft.
     */
    final public const VIRUSCHECK_ASYNC = 'async';

    /**
     * Die Datei wird nicht auf Viren geprüft.
     */
    final public const VIRUSCHECK_NONE = 'none';

    /**
     * @var string
     */
    protected $fileString;

    public function __construct(
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly EntityManagerInterface $entityManager,
        private readonly FileContainerRepository $fileContainerRepository,
        private readonly FileInUseChecker $fileInUseChecker,
        private readonly FileRepository $fileRepository,
        private readonly FilesystemOperator $defaultStorage,
        private readonly FilesystemOperator $localStorage,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly MessageBagInterface $messageBag,
        private readonly RequestStack $requestStack,
        private readonly SingleDocumentRepository $singleDocumentRepository,
        private readonly TranslatorInterface $translator,
        private readonly VirusCheckInterface $virusChecker,
        protected RpcClient $client,
    ) {
    }

    /**
     * Get file.
     */
    public function get(string $hash): ?File
    {
        /*
         * @var File|null
         */
        return $this->fileRepository
            ->getFile($hash);
    }

    /**
     * Get infos from a specific file.
     *
     * @param string $hash
     *
     * @throws Exception
     */
    public function getFileInfo($hash, ?string $procedureId = null): FileInfo
    {
        $file = $this->fileRepository->getFile($hash, $procedureId);

        if (null !== $file) {
            $path = $file->getPath();
            $absolutePath = $this->getAbsolutePath($path);

            $path .= '/'.$file->getHash();
            $absolutePath .= '/'.$file->getHash();
            $absolutePath = $this->adjustPathPrefix($absolutePath);

            // Set String to be used in other Entities
            $this->setFileString($file->getFileString());

            return new FileInfo(
                $file->getId(),
                $file->getFilename(),
                $file->getSize(),
                $file->getMimetype(),
                $path,
                $absolutePath,
                $file->getProcedure()
            );
        }

        $this->logger->warning('GetFileInfo failed Ident: ', [$hash]);
        throw new Exception('File not Found');
    }

    public function createFileStringFromFile(File $file): string
    {
        return $file->getFileString();
    }

    /**
     * Get FileInfo from complete FileString.
     *
     * @param string $fileString
     *
     * @throws Exception
     */
    public function getFileInfoFromFileString($fileString): FileInfo
    {
        $fileStringParts = explode(':', $fileString);
        if (!isset($fileStringParts[1])) {
            throw new Exception('Could not derive FileInfo from fileString', [$fileString]);
        }

        return $this->getFileInfo($fileStringParts[1]);
    }

    /**
     * Get Files of the container.
     *
     * @param string $entity
     * @param string $entityId
     * @param string $field
     *
     * @return File[]
     */
    public function getEntityFiles($entity, $entityId, $field)
    {
        try {
            return $this->fileContainerRepository
                ->getFiles($entity, $entityId, $field);
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Get only FileStrings instead of File Objects of the container.
     *
     * @return string[]
     */
    public function getEntityFileString(string $entity, string $entityId, string $field): array
    {
        try {
            return $this->fileContainerRepository
                ->getFileStrings($entity, $entityId, $field);
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Get all Files of the container.
     *
     * @param bool $includeDeleted
     *
     * @return File[]
     */
    public function getAllFiles($includeDeleted = false): array
    {
        try {
            if ($includeDeleted) {
                return $this->fileRepository->findAll();
            }

            return $this->fileRepository->findBy(['deleted' => false]);
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Check whether some files should be (soft) deleted as they are
     * not used anywhere anymore.
     */
    public function checkDeletedFiles()
    {
        $allFiles = collect($this->getAllFiles());

        foreach ($allFiles as $file) {
            /** @var File $file */
            $isUsed = $this->fileInUseChecker->isFileInUse($file->getId());
            if (!$isUsed) {
                $this->getLogger()->info('Delete unused File', ['id' => $file->getId()]);
                try {
                    $file->setDeleted(true);
                    $this->fileRepository->updateObject($file);
                } catch (Exception $e) {
                    $this->getLogger()->warning('Could not update File', [$e, $e->getMessage()]);
                }
            }
        }
    }

    /**
     * Check whether some (already soft deleted) files should be fully deleted as they are
     * not used anywhere anymore.
     *
     * @return int Amount of deleted Files
     */
    public function deleteSoftDeletedFiles(): int
    {
        $allSoftDeletedFiles = collect($this->fileRepository->findBy(['deleted' => true]));

        $filesDeleted = 0;
        /** @var File $file */
        foreach ($allSoftDeletedFiles as $file) {
            try {
                $this->getLogger()->info('Try to fully remove soft deleted File ', [$file->getId()]);
                $deleted = $this->deleteFile($file->getId());
                if ($deleted) {
                    if (null === $file->getId()) {
                        throw new InvalidDataException('FileId should not be null');
                    }
                    $this->fileRepository->deleteByHashOrIdent($file->getId());
                }
                ++$filesDeleted;
            } catch (Exception $e) {
                $this->getLogger()->warning('Could not delete soft deleted File', [$e, $e->getMessage()]);
            }
        }

        return $filesDeleted;
    }

    /**
     * Iterate over all existing files and check whether an corresponding
     * file entry exists.
     *
     * @return int Amount of deleted Files
     *
     * @throws Exception
     */
    public function removeOrphanedFiles(): int
    {
        try {
            $filesDeleted = 0;
            $files = $this->defaultStorage->listContents('/', true);
            foreach ($files as $file) {
                if ($file->isDir()) {
                    continue;
                }

                try {
                    $filename = basename($file->path());
                    $existingFile = $this->fileRepository->count(['hash' => $filename]);
                    if (0 === $existingFile) {
                        try {
                            if ($this->defaultStorage->fileExists($file->path())) {
                                $this->getLogger()->info('Remove orphaned file', [$filename]);
                                $this->defaultStorage->delete($file->path());
                            }
                            ++$filesDeleted;
                        } catch (Exception) {
                            $this->getLogger()->warning('Could not remove orphaned file', [$file->path()]);
                        }
                    }
                } catch (FilesystemException $e) {
                    $this->getLogger()->error('Could not remove file '.$file->path().' '.$e->getMessage());
                }
            }
        } catch (FilesystemException $e) {
            $this->getLogger()->error('Could not list files in default storage '.$e->getMessage());
        }

        return $filesDeleted;
    }

    /**
     * Remove stale upload Files.
     *
     * @return int Amount of deleted Files
     */
    public function removeTemporaryUploadFiles(): int
    {
        $finder = new Finder();
        // local file only, no need for flysystem
        $fs = new Filesystem();
        $finder->files()->in(DemosPlanPath::getProjectPath('web/uploads/files'));

        $filesDeleted = 0;
        foreach ($finder as $file) {
            $fileTime = Carbon::createFromTimestamp($file->getMTime());
            // delete files older than one hour
            if (1 < Carbon::now()->diffInHours($fileTime)) {
                $this->getLogger()->info('Remove old temporary upload file', [$file->getRealPath(), $file->getSize()]);
                try {
                    $fs->remove($file->getRealPath());
                    ++$filesDeleted;
                } catch (Exception) {
                    $this->getLogger()->warning('Could not remove temporary upload file', [$file->getRealPath()]);
                }
            }
        }

        return $filesDeleted;
    }

    /**
     * Returns an Imagestring with the Placeholderimage.
     *
     * @return string
     */
    public function getNotFoundImagePath()
    {
        return DemosPlanPath::getRootPath('demosplan/DemosPlanCoreBundle/Resources/public/img/placeholder.png');
    }

    /**
     * Saves a temporary local file to the storage and returns the corresponding File entity.
     * File needs to be accessible for the file system of the current process.
     * No s3 or other remote storage is used.
     *
     * @throws VirusFoundException|Throwable
     */
    public function saveTemporaryLocalFile(
        string $filePath,
        string $fileName,
        ?string $userId = null,
        ?string $procedureId = null,
        ?string $virencheck = FileServiceInterface::VIRUSCHECK_SYNC,
        ?string $hash = null,
    ): File {
        $dplanFile = new File();
        $symfonyFile = new \Symfony\Component\HttpFoundation\File\File($filePath);
        $dplanFile->setMimetype($symfonyFile->getMimeType());
        $dplanFile->setFilename($fileName);
        $dplanFile->setName($fileName);
        $dplanFile->setAuthor($userId);
        $dplanFile->setHash($hash);
        try {
            if (null !== $procedureId) {
                $dplanFile->setProcedure($this->entityManager->getReference(Procedure::class, $procedureId));
            }
        } catch (Throwable) {
            // Procedure does not exist
        }

        return $this->handleLocalFileStorage($symfonyFile, $virencheck, $dplanFile, $filePath);
    }

    /**
     * @deprecated use {@link saveTemporaryLocalFile} instead
     */
    public function saveTemporaryFile(string $filePath, string $fileName, ?string $userId = null, ?string $procedureId = null, ?string $virencheck = FileServiceInterface::VIRUSCHECK_SYNC, ?string $hash = null): FileInterface
    {
        return $this->saveTemporaryLocalFile($filePath, $fileName, $userId, $procedureId, $virencheck, $hash);
    }

    /**
     * Save an uploaded File.
     *
     * @param string|null $userId
     * @param string      $virencheck optional
     *
     * @throws Throwable
     */
    public function saveUploadedFile(UploadedFile $symfonyFile, $userId = null, $virencheck = FileServiceInterface::VIRUSCHECK_SYNC): File
    {
        $dplanFile = new File();
        $dplanFile->setMimetype($symfonyFile->getClientMimeType());
        $dplanFile->setFilename($symfonyFile->getClientOriginalName());
        $dplanFile->setName($symfonyFile->getClientOriginalName());
        $dplanFile->setAuthor($userId);
        if ($this->currentProcedureService->getProcedure() instanceof Procedure) {
            $dplanFile->setProcedure($this->currentProcedureService->getProcedure());
        }
        $filePath = $symfonyFile->getPathname();

        return $this->handleLocalFileStorage($symfonyFile, $virencheck, $dplanFile, $filePath);
    }

    /**
     * @throws Throwable|TimeoutException
     */
    protected function saveFileEntity(
        File $fileEntity,
        string $hash,
        string $path,
        string $size,
    ): File {
        try {
            // $symfonyFile needs to be used before it is moved
            $fileEntity->setSize($size);

            // reset hash even when it existed before as moveLocalFile() always returns current hash
            $fileEntity->setHash($hash);
            $fileEntity->setPath($path);

            // Create DatabaseEntry
            return $this->addFile($fileEntity);
        } catch (Throwable $e) {
            $this->logger->warning('File could not be saved', [$e, $e->getMessage()]);

            $this->deleteFile($hash);

            throw $e;
        }
    }

    /**
     * Add File entity to Database.
     *
     * @throws Exception
     */
    public function addFile(File $file): File
    {
        $this->fileRepository->addObject($file);

        // Set String to be used in other Entities
        $this->setFileString($file->getFileString());

        return $file;
    }

    /**
     * Adds a file Container.
     *
     * @param string $entityId
     * @param string $fileId
     * @param string $fileString
     */
    public function addStatementFileContainer($entityId, $fileId, $fileString, bool $flush = true): ?FileContainer
    {
        return $this->addFileContainer($entityId, Statement::class, $fileId, $fileString, $flush);
    }

    /**
     * Adds a file Container.
     *
     * @param string $entityId
     * @param string $fileId
     * @param string $fileString
     *
     * @return FileContainer|null
     */
    public function addFileContainer($entityId, string $entityClass, $fileId, $fileString, bool $flush = true)
    {
        try {
            $fileContainer = new FileContainer();
            $fileContainer->setEntityClass($entityClass);
            $fileContainer->setEntityId($entityId);
            $fileContainer->setEntityField('file');
            $file = $this->getDoctrine()->getManager()->getReference(File::class, $fileId);
            $fileContainer->setFile($file);
            $fileContainer->setFileString($fileString);
            // check whether we have a valid file
            if (!$fileContainer->getFile() instanceof File || '' === $fileContainer->getFileString()) {
                return null;
            }

            return $this->fileContainerRepository->addObject($fileContainer, $flush);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Clones the given {@link FileContainer} with a new {@link FileContainer::$id ID} and
     * the new entity ID as {@link FileContainer::$entityId}. Persist and flush it afterwards.
     *
     * @throws Exception
     */
    public function addFileContainerCopy(string $newEntityId, FileContainer $originalFileContainer): FileContainer
    {
        $newFileContainer = clone $originalFileContainer;
        $newFileContainer->setId(Uuid::uuid());
        $newFileContainer->setEntityId($newEntityId);

        return $this->fileContainerRepository->addObject($newFileContainer);
    }

    /**
     * Delete a filecontainer based on the fileId and the id
     * of the entity that it is attached to.
     *
     * @param string $fileId
     * @param string $entityId
     */
    public function deleteFileContainer($fileId, $entityId)
    {
        $file = $this->fileRepository->get($fileId);
        $fileContainer = $this->fileContainerRepository->getByPairing($file, $entityId);
        if (null !== $fileContainer) {
            $this->fileContainerRepository->delete($fileContainer->getId());
        }
    }

    /**
     * Delete file physically and from Database.
     *
     * @param string $hash
     *
     * @throws Exception
     */
    public function deleteFile($hash): bool
    {
        // try to delete File physically
        try {
            // try to delete File. When File is not found an exception will
            // be thrown (and caught) here. Orphaned file will be cleaned up by
            // removeOrphanedFiles() called in daily maintenance task
            // Files may be stored in different storages
            $file = $this->getFileInfo($hash);
            $this->getLogger()->info('Try to remove File', ['hash' => $file->getHash(), 'absolutePath' => $file->getAbsolutePath()]);
            $this->defaultStorage->delete($hash);
            $this->localStorage->delete($hash);
            $this->getLogger()->info('Removed File', ['hash' => $file->getHash(), 'absolutePath' => $file->getAbsolutePath()]);
        } catch (Exception $e) {
            $this->logger->warning('Could not delete File: ', [$e, $e->getMessage()]);
        }

        return $this->fileRepository->deleteByHashOrIdent($hash);
    }

    /**
     * Delete file physically and from Database.
     *
     * @param string $fileString
     *
     * @throws Exception
     */
    public function deleteFileFromFileString($fileString): bool
    {
        if (null === $fileString || is_array($fileString) || '' === $fileString) {
            return false;
        }

        $fileStringParts = explode(':', $fileString);
        if (isset($fileStringParts[1])) {
            return $this->deleteFile($fileStringParts[1]);
        }

        return false;
    }

    /**
     * @throws InvalidDataException
     * @throws Throwable
     */
    public function createCopyOfFile(string $fileString, string $procedureId): ?File
    {
        try {
            return $this->copyByFileString($fileString, $procedureId);
        } catch (FileNotFoundException) {
            $file = $this->getFileInfoFromFileString($fileString);
            $this->messageBag->add(
                'error',
                'error.file.notFound',
                ['fileName' => $file->getFileName()]
            );
            $this->getLogger()->error('Could not find file', [$file->getHash()]);

            return null;
        }
    }

    /**
     * @param string $fileString
     *
     * @throws InvalidDataException
     * @throws Throwable
     */
    public function copyByFileString($fileString, ?string $procedureId = null): ?File
    {
        $file = $this->getFileInfoFromFileString($fileString);

        return $this->copy($file->getHash(), $procedureId);
    }

    /**
     * Copy a file or `null` if no file could be found for the given hash.
     *
     * @throws InvalidDataException|Throwable
     */
    public function copy(?string $hash, ?string $targetProcedureId = null): ?File
    {
        $fileToCopy = $this->get($hash);
        if (!$fileToCopy instanceof File) {
            return null;
        }

        // create a new temporary file that will be stored correctly by saveTemporaryLocalFile()
        $newHash = $this->createHash();
        $newFilename = $fileToCopy->getPath().'/'.$newHash;

        try {
            $this->defaultStorage->copy($fileToCopy->getFilePathWithHash(), $newFilename);
        } catch (FilesystemException|UnableToCopyFile $e) {
            $this->logger->error('Could not copy file', [$e, $fileToCopy->getFilePathWithHash(), $newFilename]);

            return null;
        }

        // when specific target procedureId is given this shall win
        $procedure = null;
        if ($fileToCopy->getProcedure() instanceof Procedure) {
            $procedure = $fileToCopy->getProcedure();
        }
        if (null !== $targetProcedureId) {
            $procedure = $this->entityManager->getReference(Procedure::class, $targetProcedureId);
        }

        $dplanFile = new File();
        $dplanFile->setMimetype($fileToCopy->getMimetype());
        $dplanFile->setFilename($fileToCopy->getFilename());
        $dplanFile->setAuthor($fileToCopy->getAuthor());
        if ($procedure instanceof Procedure) {
            $dplanFile->setProcedure($procedure);
        }

        return $this->saveFileEntity($dplanFile, $newHash, $fileToCopy->getPath(), $fileToCopy->getSize());
    }

    public function getContent(File $file): string
    {
        try {
            return $this->defaultStorage->read($file->getFilePathWithHash());
        } catch (FilesystemException|InvalidDataException $e) {
            $this->logger->error('Could not read file content', [$e, $file->getFilePathWithHash()]);

            return '';
        }
    }

    /**
     * String saved at the entities to represent a file.
     *
     * @return string
     *
     * @deprecated do not use service property, use {@link FileInfo} instead
     */
    public function getFileString()
    {
        return $this->fileString;
    }

    /**
     * @param string $fileString
     */
    public function setFileString($fileString)
    {
        $this->fileString = $fileString;
    }

    /**
     * @return string
     */
    public function getFilesPath()
    {
        return $this->globalConfig->getFileServiceFilePath();
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    public function getFilesPathAbsolute()
    {
        return $this->globalConfig->getFileServiceFilePathAbsolute();
    }

    /**
     * Is the given mimetype allowed in global settings?
     *
     * @param string $mimeType
     * @param string $temporaryFilePath
     *
     * @throws FileException
     */
    public function checkMimeTypeAllowed($mimeType, $temporaryFilePath): void
    {
        $allowedMimeTypes = $this->globalConfig->getAllowedMimeTypes();
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $this->logger->warning('MimeType is not allowed. Given MimeType: ', [$mimeType]);
            // delete temporary file. May be done with Symfony Filesystem component as file needs to exist locally
            $fs = new Filesystem();
            $fs->remove($temporaryFilePath);
            throw new FileException('MimeType "'.$mimeType.'" is not allowed', 20);
        }
    }

    /**
     * Move temporary file to the files destination.
     *
     * @param \Symfony\Component\HttpFoundation\File\File $file File or UploadedFile
     * @param string                                      $path
     */
    protected function moveLocalFile(\Symfony\Component\HttpFoundation\File\File $file, $path = '', ?string $existingHash = null): string
    {
        // Generate a unique name for the file before saving it
        $hash = $existingHash ?? $this->createHash();

        // Move the file to the directory where files are stored
        $stream = fopen($file->getPathname(), 'rb');
        $this->defaultStorage->writeStream(sprintf('%s/%s', $path, $hash), $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $hash;
    }

    public function ensureLocalFileFromHash(string $hash, ?string $path = null): string
    {
        $file = $this->getFileInfo($hash);

        return $this->ensureLocalFile($file->getAbsolutePath(), $hash, $path);
    }

    /**
     * Generate a unique name for the file.
     */
    public function createHash(): string
    {
        return md5(uniqid('', true));
    }

    /**
     * Create Absolute path from a relative one.
     *
     * @param string $path
     *
     * @return string
     */
    protected function getAbsolutePath($path)
    {
        if (str_starts_with($path, '.')) {
            $path = realpath($this->globalConfig->getInstanceAbsolutePath().'/'.$path);
        }

        return $path;
    }

    /**
     * Scan a specific file for a virus.
     * The path of the file, will be sent to a service, which executes the viruscheck.
     *
     * @throws VirusFoundException|Exception
     */
    protected function virusCheck(\Symfony\Component\HttpFoundation\File\File $file): void
    {
        try {
            $hasVirus = $this->virusChecker->hasVirus($file);
            if ($hasVirus) {
                $this->removeRequestFiles();

                throw new VirusFoundException();
            }
        } catch (Exception $e) {
            $fs = new DemosFilesystem();
            $fs->remove($file->getPathname());
            $this->removeRequestFiles();
            $this->getLogger()->error('Error in virusCheck:', [$e]);
            throw $e;
        }
    }

    /**
     * remove uploaded Files from request that they could do no harm.
     */
    private function removeRequestFiles(): void
    {
        $fs = new DemosFilesystem();
        /** @var UploadedFile $file */
        foreach ($this->requestStack->getCurrentRequest()->files?->all() as $file) {
            $fs->remove($file->getPathname());
        }
        $this->requestStack->getCurrentRequest()->files?->replace([]);
    }

    /**
     * Get File Information.
     *
     * @param string $text
     * @param string $key
     * @param string $scale
     *
     * @return string
     */
    public function getInfoFromFileString($text, $key, $scale = 'KB')
    {
        if (!is_string($text) || !is_string($key)) {
            return '';
        }

        $returnValue = '';
        $parts = explode(':', $text);
        switch ($key) {
            case 'name':
                $returnValue = $parts[0] ?? '';
                break;
            case 'hash':
                $returnValue = $parts[1] ?? '';
                break;
            case 'size':
                if (isset($parts[2])) {
                    $returnValue = $this->convertSize($scale, (int) $parts[2]);
                }
                break;
            case 'mimeType':
                if (isset($parts[3])) {
                    $returnValue = $this->convertMimeType($parts[3]);
                }
                break;
        }

        return $returnValue;
    }

    public function getInfoArrayFromFileString(string $text, string $scale = 'KB'): array
    {
        $parts = explode(':', $text);

        return [
            'name'     => $parts[0] ?? '',
            'hash'     => $parts[1] ?? '',
            'size'     => array_key_exists(2, $parts) ? $this->convertSize($scale, (int) $parts[2]) : '',
            'mimeType' => array_key_exists(3, $parts) ? $this->convertMimeType($parts[3]) : '',
        ];
    }

    /**
     * @param int $value The size in bytes
     */
    public function convertSize(string $scale, int $value): string
    {
        $returnValue = match ($scale) {
            'B'     => $value,
            'KB'    => $value / 1024,
            'MB'    => $value / 1_048_576,
            'GB'    => $value / 1_073_741_824,
            'TB'    => $value / 1_099_511_627_776,
            default => throw new InvalidArgumentException("Unsupported scale: {$scale}"),
        };

        return round($returnValue, 2).' '.$scale;
    }

    /**
     * Gib einen lesbaren MimeType aus.
     *
     * @param string $value
     */
    protected function convertMimeType($value)
    {
        $mimeTypeReadable = $value;
        $mimeTypes = [
            'text/plain'                                                              => 'txt',
            'text/html'                                                               => 'html',
            'text/css'                                                                => 'css',
            'application/javascript'                                                  => 'js',
            'application/json'                                                        => 'json',
            'application/xml'                                                         => 'xml',
            'application/x-shockwave-flash'                                           => 'swf',
            'application/octet-stream'                                                => 'binary',
            'video/x-flv'                                                             => 'flv',
            // images,
            'image/png'                                                               => 'png',
            'image/jpeg'                                                              => 'jpg',
            'image/gif'                                                               => 'gif',
            'image/bmp'                                                               => 'bmp',
            'image/vnd.microsoft.icon'                                                => 'ico',
            'image/tiff'                                                              => 'tiff',
            'image/svg+xml'                                                           => 'svg',
            // archives,
            'application/zip'                                                         => 'zip',
            'application/x-rar-compressed'                                            => 'rar',
            'application/x-msdownload'                                                => 'exe',
            'application/vnd.ms-cab-compressed'                                       => 'cab',
            // audio/video,
            'audio/mpeg'                                                              => 'mp3',
            'video/quicktime'                                                         => 'mov',
            // adobe,
            'application/pdf'                                                         => 'pdf',
            'application/x-pdf'                                                       => 'pdf',
            'application/x-download'                                                  => 'pdf',
            'image/vnd.adobe.photoshop'                                               => 'psd',
            'application/postscript'                                                  => 'ps',
            // ms office,
            'application/msword'                                                      => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/rtf'                                                         => 'rtf',
            'application/vnd.ms-excel'                                                => 'xls',
            'application/vnd.ms-powerpoint'                                           => 'ppt',
            // open office,
            'application/vnd.oasis.opendocument.text'                                 => 'odt',
            'application/vnd.oasis.opendocument.spreadsheet'                          => 'ods',
        ];

        // Wenn du den MimeType findest, ersetze ihn, ansonsten den technischen MimeType
        if (isset($mimeTypes[$value])) {
            $mimeTypeReadable = $mimeTypes[$value];
        }

        return $mimeTypeReadable;
    }

    /**
     * Given an array of File ids, returns the corresponding array of File entities.
     *
     * @param string[] $fileIds
     *
     * @return File[]
     */
    public function getFilesByIds(array $fileIds): array
    {
        return $this->fileRepository->findBy(['ident' => $fileIds]);
    }

    public function getFileById($fileId)
    {
        return $this->fileRepository->findOneBy(['ident' => $fileId]);
    }

    /**
     * Returns an array with the $fileIds which don't belong to $procedureId.
     * If they all belong to the procedure then returns an empty array.
     *
     * @return string[]
     */
    public function getFilesNotInProcedure(array $fileIds, string $procedureId): array
    {
        $singleDocRepository = $this->singleDocumentRepository;
        $procedureSingleDocs = $singleDocRepository->getFilesByProcedureId($procedureId);
        $procedureFileIds = $this->getFilesFromSingleDocuments($procedureSingleDocs);

        return array_diff($fileIds, $procedureFileIds);
    }

    /**
     * Given an array of SingleDocuments (array format) returns their corresponding File entities.
     *
     * @return File[]
     */
    public function getFilesFromSingleDocuments(array $singleDocuments)
    {
        return array_map(
            fn ($singleDocument) => $this->getFileIdFromSingleDocument($singleDocument),
            $singleDocuments);
    }

    /**
     * Given a SingleDocument (array format) returns its corresponding File id.
     *
     * @throws Exception
     */
    public function getFileIdFromSingleDocument(array $singleDocument)
    {
        $singleDocInfo = $this->getFileInfoFromFileString($singleDocument['document']);

        return $singleDocInfo->getHash();
    }

    /**
     * Given a SingleDocument id returns its corresponding File id.
     *
     * @throws Exception
     */
    public function getFileIdFromSingleDocumentId(string $singleDocumentId): string
    {
        $singleDocument = $this->singleDocumentRepository->get($singleDocumentId);
        if (null === $singleDocument) {
            $this->logger->error("No file entity related to singleDocumentId: $singleDocumentId");
            throw new \InvalidArgumentException('error.generic');
        }
        $singleDocInfo = $this->getFileInfoFromFileString($singleDocument->getDocument());
        if ('' === $singleDocInfo->getHash()) {
            $this->logger->error("Wrong document info for singleDocumentId: $singleDocumentId. Doc. Info: ".Json::encode($singleDocInfo));
            throw new \InvalidArgumentException('error.generic');
        }

        return $singleDocInfo->getHash();
    }

    public function getFileIdFromUploadFile(string $uploadFile): string
    {
        $fileStringParts = explode(':', $uploadFile);

        return $fileStringParts[1] ?? '';
    }

    /**
     * Get the smaller value of free disk space of one of the two used directories for storing new data.
     * Returns null if free space cant calculated.
     */
    public function getRemainingDiskSpace(): ?float
    {
        $smallerValue = null;
        try {
            $globalConfig = $this->globalConfig;
            $fileDirectoryFreeSpace = disk_free_space($globalConfig->getFileServiceFilePath());
            $uploadDirectoryFreeSpace = disk_free_space(DemosPlanPath::getProjectPath('web/uploads/files'));
            $smallerValue = $uploadDirectoryFreeSpace < $fileDirectoryFreeSpace ? $uploadDirectoryFreeSpace : $fileDirectoryFreeSpace;
        } catch (Exception $e) {
            $this->getLogger()->error('Error on getRemainingDiskSpace(): ', [$e]);
        }

        return $smallerValue;
    }

    /**
     * @return string translated, human readable text which contains information about free disk space in GB
     */
    public function getFreeDiskSpaceAsText(): string
    {
        $freeDiskSpaceAsString = $this->translator->trans('error.calculate.free.disk.space');
        $result = $this->getRemainingDiskSpace();
        if (is_float($result)) {
            $freeDiskSpaceAsString = $this->translator->trans(
                'free.disk.space',
                ['spaceInGB' => $this->formatHumanFilesize($result)]
            );
        }

        return $freeDiskSpaceAsString;
    }

    /**
     * @param int|float $bytes
     * @param int       $precision
     *
     * @return string
     */
    public function formatHumanFilesize($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);

        $pow = floor((($bytes > 0) ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= 1024 ** $pow;

        return round($bytes, $precision).' '.$units[$pow];
    }

    public function getFileFromFileString(string $fileString): File
    {
        $fileStringParts = explode(':', $fileString);

        return $this->fileRepository
            ->getFile($fileStringParts[1]);
    }

    public function sanitizeFileName(string $filename): string
    {
        $filename = str_ireplace(self::INVALID_FILENAME_CHARS, '', $filename);

        return str_ireplace(' ', '_', $filename);
    }

    private function storeLocalFile(\Symfony\Component\HttpFoundation\File\File $symfonyFile, bool $viruscheck, File $fileEntity): array
    {
        // Check Mimetype
        $this->checkMimeTypeAllowed($symfonyFile->getMimeType(), $symfonyFile->getPathname());
        // Save file
        // Sort into subfolders to avoid too many files in one folder
        $path = date('Y').'/'.date('m');

        if ($viruscheck && $this->globalConfig->isAvscanEnabled()) {
            $this->virusCheck($symfonyFile);
        }

        // save file into files path
        $this->getLogger()->info(
            'Try to move file',
            ['from' => $symfonyFile->getRealPath(), 'to' => $path]
        );
        $hash = $this->moveLocalFile($symfonyFile, $path, $fileEntity->getHash());
        $this->getLogger()->info('File moved', ['hash' => $hash]);

        return [$path, $hash];
    }

    private function handleLocalFileStorage(
        \Symfony\Component\HttpFoundation\File\File $symfonyFile,
        string $virencheck,
        File $dplanFile,
        string $filePath,
    ): File {
        [$path, $hash] = $this->storeLocalFile($symfonyFile, self::VIRUSCHECK_NONE !== $virencheck, $dplanFile);
        $newEntity = $this->saveFileEntity($dplanFile, $hash, $path, $symfonyFile->getSize());

        // delete temporary file. May be done with Symfony Filesystem component as file needs to exist locally
        $fs = new Filesystem();
        $fs->remove($filePath);

        return $newEntity;
    }

    public function moveLocalFilesToFlysystem(string $localDir): string
    {
        $finder = new Finder();
        $fs = new Filesystem();

        $finder->files()->in($localDir);
        // Move files from the temporary directory to Flysystem
        foreach ($finder as $file) {
            if ($file->isDir()) {
                $this->defaultStorage->createDirectory($file->getPath());
            } else {
                $stream = fopen($file->getPathname(), 'rb+');
                $this->defaultStorage->writeStream($file->getPathname(), $stream);
                fclose($stream);
            }
        }

        // Clean up the temporary directory
        $fs->remove($localDir);

        // return the temporary directory to be used in the next step
        return $localDir;
    }

    public function ensureLocalFile(string $remotePath, ?string $hash = null, ?string $path = null): ?string
    {
        if (null === $path) {
            $path = DemosPlanPath::getTemporaryPath(
                sprintf('%s/%s', uniqid($hash, true), $hash ?? uniqid('', true))
            );
        }
        // Move the file to local directory from flysystem
        $fs = new Filesystem();
        if ($this->defaultStorage->fileExists($remotePath)) {
            $fs->dumpFile($path, $this->defaultStorage->read($remotePath));
        }

        if (!$fs->exists($path)) {
            throw new FileNotFoundException('File not found: '.$path);
        }

        return $path;
    }

    public function deleteLocalFile($localFilePath): void
    {
        $fs = new Filesystem();
        try {
            $fs->remove($localFilePath);
        } catch (Exception $e) {
            $this->getLogger()->error('Could not remove local file', [$localFilePath, $e->getMessage()]);
        }
    }

    private function adjustPathPrefix(string $absolutePath): string
    {
        try {
            if ($this->defaultStorage->fileExists($absolutePath)) {
                return $absolutePath;
            }
        } catch (FilesystemException $e) {
            $this->getLogger()->info('Could not check file existence', [$absolutePath, $e->getMessage()]);
        }

        // try to strip the path prefix from the absolute path
        // as the path prefix was saved to the database prior to flysystem usage
        $prefix = $this->globalConfig->getFileServiceFilePath();
        if (str_starts_with($absolutePath, $prefix)) {
            // remove the prefix
            return substr($absolutePath, strlen($prefix));
        }

        // fallback to the original path
        return $absolutePath;
    }
}
