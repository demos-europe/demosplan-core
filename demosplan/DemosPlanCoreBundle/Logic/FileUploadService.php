<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\FileUploadServiceInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\VirusFoundException;

class FileUploadService implements FileUploadServiceInterface
{
    /**
     * @var FileService
     */
    private $fileService;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var MessageBagInterface
     */
    private $messageBag;

    public function __construct(FileService $fileService, LoggerInterface $logger, MessageBagInterface $messageBag)
    {
        $this->fileService = $fileService;
        $this->logger = $logger;
        $this->messageBag = $messageBag;
    }

    /**
     * Speichere die hochgeladenen Dateien im Fileservice.
     *
     * Warning: don't omit the $field parameter when accessing multiple fields. The returned array
     * is supposed to contain one entry for each field with the entry containing the file(s) for that
     * field. However in reality one of the entries did contain a file from a different entry.
     *
     * @param string|null $field will return all files if set to null
     *
     * @return array|string Fileservice hash
     *
     * @throws Exception
     */
    public function prepareFilesUpload(Request $request, $field = null, bool $suppressWarning = false)
    {
        $messageBag = $this->messageBag;

        $fileBag = $request->files->all();
        $requestPost = $request->request;

        $savedFiles = [];

        try {
            if (0 < count($fileBag)) {
                $savedFiles = $this->handleFileBag(
                    $field,
                    $fileBag
                );
            }

            if ($requestPost->has('uploadedFiles')) {
                $uploadedFiles = $requestPost->get('uploadedFiles');
                // array if multiupload is active, otherwise string
                if (is_array($uploadedFiles)) {
                    $savedFiles = $this->handleUploadedFiles($uploadedFiles, $field);
                } elseif (0 < strlen($uploadedFiles)) {
                    // Falls kein Feld definiert ist, nutze einen nummeric key
                    $key = $field ?? 0;
                    $uploadedFiles = [$key => $uploadedFiles];
                    $savedFiles = $this->handleUploadedFiles($uploadedFiles, $field);
                }
            }
        } catch (FileException $e) {
            // Falscher MimeType
            if (20 === $e->getCode()) {
                $messageBag->add('warning', 'warning.filetype');
            }

            // Lösche bei einer Exception die Datei aus dem Request
            $request->files->remove($field);
            $this->logger->warning($e);
        } catch (Exception $e) {
            if (false === $suppressWarning) {
                $messageBag->add('error', 'error.fileupload');
            }

            // Lösche bei einer Exception die Datei aus dem Request
            if ($request->files->has($field ?? '')) {
                $request->files->remove($field);
            }
        }

        // wenn nur ein einzelnes Feld gewünscht wird, gib nur das zurück
        if (null !== $field) {
            // Rückwärtskompatibilität: Wenn ein Feld requested wird, wurde ein Leerstring zurückgegeben
            $savedFiles = $savedFiles[$field] ?? '';
        }

        return $savedFiles;
    }

    /**
     * Handle Files uploaded via FileBag.
     *
     * @param string|null      $field
     * @param UploadedFile[][] $fileBag
     *
     * @return mixed
     *
     * @throws MessageBagException
     */
    protected function handleFileBag($field, $fileBag)
    {
        // gehe das Filebag auf übertragene Fileuploadfelder durch
        $filesUploaded = [];
        $savedFiles = [];
        foreach ($fileBag as $fieldname => $files) {
            // gehe die Dateien durch
            if (null === $files) {
                continue;
            }
            // soll nur ein einzelnes Feld übergeben werden?
            if (null !== $field && $field != $fieldname) {
                continue;
            }
            if (!is_array($files)) {
                $files = [$files];
            }
            foreach ($files as $file) {
                if (null === $file) {
                    $filesUploaded = null;
                    continue;
                }
                if (true === $file->isValid()) {
                    try {
                        $this->fileService->saveUploadedFile($file);
                        $filesUploaded[] = $this->fileService->getFileString();
                    } catch (VirusFoundException $e) {
                        $this->messageBag->add('warning', 'warning.virus.found', ['filename' => $e->getMessage()]);
                    } catch (Exception $e) {
                        $this->messageBag->add('warning', 'error.fileupload', ['filename' => $e->getMessage()]);
                    }
                }
            }
            if (1 === count($filesUploaded)) {
                // Speichere die Infos zur gespeicherten Datei
                $savedFiles[$fieldname] = $filesUploaded[0];
            } elseif (1 < count($filesUploaded)) {
                $savedFiles[$fieldname] = $filesUploaded;
            }
        }

        return $savedFiles;
    }

    /**
     * Handle Files uploaded via external Libaray e.g. plupload.
     *
     * @param string|null $field
     *
     * @throws Exception
     */
    protected function handleUploadedFiles(array $uploadedFiles, $field = null): array
    {
        $filesUploaded = [];
        $savedFiles = [];

        foreach ($uploadedFiles as $key => $uploadedFilesString) {
            if (null !== $field && $field != $key) {
                continue;
            }

            $uploadedFiles = explode(',', $uploadedFilesString);
            foreach ($uploadedFiles as $uploadedFileHash) {
                if ('' === $uploadedFileHash) {
                    continue;
                }
                $this->fileService->getFileInfo($uploadedFileHash);
                $filesUploaded[] = $this->fileService->getFileString();
            }

            if (1 === count($filesUploaded)) {
                // Speichere die Infos zur gespeicherten Datei
                $savedFiles[$key] = $filesUploaded[0];
            } elseif (1 < count($filesUploaded)) {
                $savedFiles[$key] = $filesUploaded;
            }
        }

        return $savedFiles;
    }

    /**
     * Wurden in dem Request Dateien hochgeladen?
     *
     * @param string|null $key Uploaded file field name
     */
    public function hasUploadedFiles(Request $request, $key = null): bool
    {
        /** @var FileBag $fileBag */
        $fileBag = $request->files->all();

        if ($request->request->has('uploadedFiles')) {
            $uploadedFiles = $request->request->get('uploadedFiles');
            if (is_array($uploadedFiles)) {
                return array_key_exists($key, $uploadedFiles) && 0 < strlen($uploadedFiles[$key]);
            }

            return true;
        }

        // Prüfe, ob eine Datei mit dem Key korrekt übergeben wurde
        if (isset($key)) {
            return isset($fileBag[$key]) && $fileBag[$key]->isValid();
        }

        return count($fileBag) > 0;
    }
}
