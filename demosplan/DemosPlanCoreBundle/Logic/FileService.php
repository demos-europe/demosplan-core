<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\FileServiceInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\TimeoutException;
use demosplan\DemosPlanCoreBundle\Exception\VirusFoundException;
use demosplan\DemosPlanCoreBundle\Repository\FileContainerRepository;
use demosplan\DemosPlanCoreBundle\Repository\FileRepository;
use demosplan\DemosPlanCoreBundle\Repository\SingleDocumentRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanStatementBundle\Exception\InvalidDataException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Faker\Provider\Uuid;
use OldSound\RabbitMqBundle\RabbitMq\RpcClient;
use PhpAmqpLib\Exception\AMQPTimeoutException;
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

    public const INVALID_FILENAME_CHARS = ['%', '&', ':'];

    /**
     * Die Datei wird nach dem Upload zum FileService direkt auf Viren geprüft.
     */
    public const VIRUSCHECK_SYNC = 'sync';

    /**
     * Die Datei wird nach dem Upload zum FileService nicht direkt auf Viren geprüft.
     */
    public const VIRUSCHECK_ASYNC = 'async';

    /**
     * Die Datei wird nicht auf Viren geprüft.
     */
    public const VIRUSCHECK_NONE = 'none';

    /**
     * @var string
     */
    protected $fileString;

    /**
     * @var RpcClient
     */
    protected $client;

    /**
     * @var RequestStack
     */
    private $requestStack;
    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var FileRepository
     */
    private $fileRepository;

    /**
     * @var FileContainerRepository
     */
    private $fileContainerRepository;

    /**
     * @var CurrentProcedureService
     */
    private $currentProcedureService;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var FileInUseChecker
     */
    private $fileInUseChecker;

    /**
     * @var MessageBagInterface
     */
    private $messageBag;
    /**
     * @var SingleDocumentRepository
     */
    private $singleDocumentRepository;
    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;

    public function __construct(
        CurrentProcedureService $currentProcedureService,
        EntityManagerInterface $entityManager,
        FileContainerRepository $fileContainerRepository,
        FileInUseChecker $fileInUseChecker,
        FileRepository $fileRepository,
        GlobalConfigInterface $globalConfig,
        MessageBagInterface $messageBag,
        RequestStack $requestStack,
        SingleDocumentRepository $singleDocumentRepository,
        TranslatorInterface $translator
    ) {
        $this->currentProcedureService = $currentProcedureService;
        $this->entityManager = $entityManager;
        $this->fileContainerRepository = $fileContainerRepository;
        $this->fileInUseChecker = $fileInUseChecker;
        $this->fileRepository = $fileRepository;
        $this->globalConfig = $globalConfig;
        $this->messageBag = $messageBag;
        $this->requestStack = $requestStack;
        $this->singleDocumentRepository = $singleDocumentRepository;
        $this->translator = $translator;
    }

    /**
     * Get file.
     *
     * @param string $hash
     *
     * @return File|null
     */
    public function get($hash)
    {
        /*
         * @var File|null
         */
        return $this->fileRepository
            ->getFileInfo($hash);
    }

    /**
     * Get infos from a specific file.
     *
     * @param string $hash
     *
     * @throws Exception
     */
    public function getFileInfo($hash): FileInfo
    {
        $file = $this->fileRepository->getFileInfo($hash);

        if (null !== $file) {
            $path = $file->getPath();
            $absolutePath = $this->getAbsolutePath($path);

            $path .= '/'.$file->getHash();
            $absolutePath .= '/'.$file->getHash();

            // Set String to be used in other Entities
            $this->setFileString($file->getFileString());

            return new FileInfo(
                $file->getIdent(),
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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
        $finder = new Finder();
        $fs = new Filesystem();
        $finder->files()->in($this->globalConfig->getFileServiceFilePathAbsolute());

        $filesDeleted = 0;
        foreach ($finder as $file) {
            $filename = $file->getBasename();
            $existingFile = $this->fileRepository->findBy(['hash' => $filename]);
            if (is_array($existingFile) && 0 === count($existingFile)) {
                $this->getLogger()->info('Remove orphaned file', [$filename, $file->getSize()]);
                try {
                    $fs->remove($file->getRealPath());
                    ++$filesDeleted;
                } catch (Exception $e) {
                    $this->getLogger()->warning('Could not remove orphaned file', [$filename]);
                }
            }
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
                } catch (Exception $e) {
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
     * Save a temporary file as regular file by its path.
     *
     * @throws VirusFoundException|Throwable
     */
    public function saveTemporaryFile(
        string $filePath,
        string $fileName,
        ?string $userId = null,
        ?string $procedureId = null,
        ?string $virencheck = FileService::VIRUSCHECK_SYNC,
        ?string $hash = null
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
        } catch (Throwable $exception) {
            // Procedure does not exist
        }

        $this->saveFile($dplanFile, $symfonyFile, self::VIRUSCHECK_NONE !== $virencheck);

        return $dplanFile;
    }

    /**
     * Save an uploaded File.
     *
     * @param string|null $userId
     * @param string      $virencheck optional
     *
     * @throws Throwable
     */
    public function saveUploadedFile(UploadedFile $symfonyFile, $userId = null, $virencheck = FileService::VIRUSCHECK_SYNC): File
    {
        $dplanFile = new File();
        $dplanFile->setMimetype($symfonyFile->getClientMimeType());
        $dplanFile->setFilename($symfonyFile->getClientOriginalName());
        $dplanFile->setName($symfonyFile->getClientOriginalName());
        $dplanFile->setAuthor($userId);
        if ($this->currentProcedureService->getProcedure() instanceof Procedure) {
            $dplanFile->setProcedure($this->currentProcedureService->getProcedure());
        }

        $this->saveFile($dplanFile, $symfonyFile, self::VIRUSCHECK_NONE !== $virencheck);

        return $dplanFile;
    }

    /**
     * @throws Throwable|TimeoutException
     */
    protected function saveFile(
        File $dplanFile,
        \Symfony\Component\HttpFoundation\File\File $symfonyFile,
        bool $viruscheck = true
    ): void {
        try {
            // Check Mimetype
            $this->checkMimeTypeAllowed($symfonyFile->getMimeType(), $symfonyFile->getPathname());
            // Save file
            // Sort into subfolders to avoid too many files in one folder
            $path = date('Y').'/'.date('m');

            if ($viruscheck && $this->globalConfig->isAvscanEnabled()) {
                try {
                    $this->virusCheck($symfonyFile);
                } catch (VirusFoundException $e) {
                    throw $e;
                }
            }

            // $symfonyFile needs to be used before it is moved
            $dplanFile->setSize($symfonyFile->getSize());

            // save file into files path
            $path = $this->getFilesPath().'/'.$path;
            $this->getLogger()->info('Try to move file', ['from' => $symfonyFile->getRealPath(), 'to' => $path]);
            $hash = $this->moveFile($symfonyFile, $path, $dplanFile->getHash());
            $this->getLogger()->info('File moved', ['hash' => $hash]);

            // reset hash even when it existed before as moveFile() always returns current hash
            $dplanFile->setHash($hash);
            $dplanFile->setPath($path);

            // Create DatabaseEntry
            $this->addFile($dplanFile);

            // Set String to be used in other Entities
            $this->setFileString($dplanFile->getFileString());
        } catch (Throwable $e) {
            $this->logger->warning('File could not be saved', [$e, $e->getMessage()]);
            // versuche ggf die angelegte Datei zu löschen
            if (isset($hash)) {
                $filesPath = $this->globalConfig
                    ->getFileServiceFilePathAbsolute();
                @unlink($filesPath.'/'.$hash);
            }

            throw $e;
        }
    }

    /**
     * Add File entity to Database.
     *
     * @throws Exception
     */
    public function addFile(File $file): void
    {
        $this->fileRepository->addObject($file);

        // Set String to be used in other Entities
        $this->setFileString($file->getFileString());
    }

    /**
     * Adds a file Container.
     *
     * @param string $entityId
     * @param string $fileId
     * @param string $fileString
     */
    public function addStatementFileContainer($entityId, $fileId, $fileString): ?FileContainer
    {
        return $this->addFileContainer($entityId, Statement::class, $fileId, $fileString);
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
    public function addFileContainer($entityId, string $entityClass, $fileId, $fileString)
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

            return $this->fileContainerRepository->addObject($fileContainer);
        } catch (Exception $e) {
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
     * @return bool
     *
     * @throws Exception
     */
    public function deleteFile($hash)
    {
        // try to delete File physically
        $fs = new DemosFilesystem();
        try {
            // try to delete File. When File is not found an exception will
            // be thrown (and catched) here. Orphaned file will be cleaned up by
            // removeOrphanedFiles() called in daily maintenance task
            $file = $this->getFileInfo($hash);
            $this->getLogger()->info('Try to remove File', ['hash' => $file->getHash(), 'absolutePath' => $file->getAbsolutePath()]);
            if (is_file($file->getAbsolutePath())) {
                $fs->remove($file->getAbsolutePath());
                $this->getLogger()->info('Removed File', ['hash' => $file->getHash(), 'absolutePath' => $file->getAbsolutePath()]);
            } else {
                $this->getLogger()->warning('File not found for deletion', ['hash' => $file->getHash(), 'absolutePath' => $file->getAbsolutePath()]);
            }
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
        } catch (FileNotFoundException $e) {
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

        // create a new temporary file that will be stored correctly by saveTemporaryFile()
        $newHash = $this->createHash();
        $newPath = $fileToCopy->getPath().'/'.$newHash;

        $fs = new DemosFilesystem();
        $fs->copy($fileToCopy->getFilePathWithHash(), $newPath);

        $procedureId = null;
        // when specific target procedureId is given this shall win
        if (null !== $targetProcedureId) {
            $procedureId = $targetProcedureId;
        } elseif ($fileToCopy->getProcedure() instanceof Procedure) {
            $procedureId = $fileToCopy->getProcedure()->getId();
        }

        return $this->saveTemporaryFile(
            $newPath,
            $fileToCopy->getFilename(),
            null,
            $procedureId,
            self::VIRUSCHECK_NONE
        );
    }

    /**
     * @return string PHP handles binary data as a string
     */
    public function getContent(File $file): string
    {
        $filePath = $this->getAbsolutePath($file->getFilePathWithHash());

        return file_get_contents($filePath);
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
    public function checkMimeTypeAllowed($mimeType, $temporaryFilePath)
    {
        $allowedMimeTypes = $this->globalConfig->getAllowedMimeTypes();
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            @unlink($temporaryFilePath);
            $this->logger->warning(
                'MimeType is not allowed. Given MimeType: '.$mimeType
            );
            throw new FileException('MimeType "'.$mimeType.'" is not allowed', 20);
        }
    }

    /**
     * Move temporary file to the files destination.
     *
     * @param \Symfony\Component\HttpFoundation\File\File $file File or UploadedFile
     * @param string                                      $path
     *
     * @return string
     */
    protected function moveFile(\Symfony\Component\HttpFoundation\File\File $file, $path = '', ?string $existingHash = null)
    {
        // Generate a unique name for the file before saving it
        $hash = $existingHash ?? $this->createHash();

        // Move the file to the directory where files are stored
        $file->move($path, $hash);

        return $hash;
    }

    /**
     * Generate a unique name for the file.
     *
     * @return string
     */
    public function createHash()
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
        if (0 === strpos($path, '.')) {
            $path = realpath($this->globalConfig->getInstanceAbsolutePath().'/'.$path);
        }

        return $path;
    }

    /**
     * Scan a specific file for a virus.
     * The path of the file, will be send to a service, which execute the viruscheck.
     *
     * @return bool - true if the file was successfully checked and it was no virus found, otherwise false
     *
     * @throws TimeoutException|Exception
     */
    protected function virusCheck(\Symfony\Component\HttpFoundation\File\File $file): bool
    {
        $payload = [
            'path' => $file->getRealPath(),
        ];

        $msg = Json::encode($payload);

        // Füge Message zum Request hinzu
        try {
            $routingKey = $this->globalConfig->getProjectPrefix();
            if ($this->globalConfig->isMessageQueueRoutingDisabled()) {
                $routingKey = '';
            }

            // Anfrage absenden
            $this->logger->info('Path of file for virusCheck: '.$file->getRealPath().', with routingKey: '.$routingKey);
            $this->client->addRequest($msg, 'virusCheckDemosPlanLocal', 'virusCheck', $routingKey, 300);

            $replies = $this->client->getReplies();

            if (strlen($replies['virusCheck']) > 0) {
                $this->logger->info('Incoming message size:'.strlen($replies['virusCheck']));
            }
            $vCheckResult = Json::decodeToArray($replies['virusCheck']);
            if (true == $vCheckResult['result']) {
                return true;
            } else {
                $this->removeRequestFiles();
                $this->getLogger()->warning('File could not be checked. Response: '.DemosPlanTools::varExport($replies, true));
            }
        } catch (AMQPTimeoutException $e) {
            $this->getLogger()->error('Fehler in virusCheck:', [$e]);
            throw new TimeoutException($e->getMessage());
        } catch (Exception $e) {
            $fs = new DemosFilesystem();
            $fs->remove($file->getPathname());
            $this->removeRequestFiles();
            $this->getLogger()->error('Fehler in virusCheck:', [$e]);
            throw $e;
        }

        return false;
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
        switch ($scale) {
            case 'B':
                $returnValue = $value;
                break;
            case 'KB':
                $returnValue = $value / 1024;
                break;
            case 'MB':
                $returnValue = $value / 1048576;
                break;
            case 'GB':
                $returnValue = $value / 1073741824;
                break;
            case 'TB':
                $returnValue = $value / 1099511627776;
                break;
            default:
                throw new InvalidArgumentException("Unsupported scale: {$scale}");
        }

        return round($returnValue, 2).' '.$scale;
    }

    /**
     * Gib einen lesbaren MimeType aus.
     *
     * @param string $value
     *
     * @return mixed
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
     * @param RpcClient $client
     */
    public function setClient($client)
    {
        $this->client = $client;
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

    /**
     * @param string $fileId
     *
     * @return File|null
     */
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
            function ($singleDocument) {
                return $this->getFileIdFromSingleDocument($singleDocument);
            },
            $singleDocuments);
    }

    /**
     * Given a SingleDocument (array format) returns its corresponding File id.
     *
     * @return mixed
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
        if (isset($fileStringParts[1])) {
            return $fileStringParts[1];
        }

        return '';
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
            ->getFileInfo($fileStringParts[1]);
    }

    public function sanitizeFileName(string $filename): string
    {
        $filename = str_ireplace(self::INVALID_FILENAME_CHARS, '', $filename);

        return str_ireplace(' ', '_', $filename);
    }
}
