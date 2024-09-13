<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Document;

use DemosEurope\DemosplanAddon\Contracts\Entities\ElementsInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Exception\VirusFoundException;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use Exception;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DocumentHandler extends CoreHandler
{
    final public const ACTION_SINGLE_DOCUMENT_NEW = 'singledocumentnew';

    /**
     * @var SingleDocumentHandler
     */
    protected $singleDocumentHandler;
    /**
     * @var ElementsService
     */
    protected $elementsService;

    /**
     * Temporary Element Folder Paths.
     *
     * @var array
     */
    protected $elementsPaths = [];

    public function __construct(
        private readonly CurrentUserService $currentUser,
        private readonly ElementHandler $elementHandler,
        ElementsService $elementsService,
        private readonly FileService $fileService,
        private readonly FilesystemOperator $defaultStorage,
        MessageBagInterface $messageBag,
        private readonly ParagraphService $paragraphService,
        private readonly ProcedureService $procedureService,
        SingleDocumentHandler $singleDocumentHandler,
        private readonly SingleDocumentService $singleDocumentService,
        private readonly TranslatorInterface $translator,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct($messageBag);
        $this->elementsService = $elementsService;
        $this->singleDocumentHandler = $singleDocumentHandler;
    }

    /**
     * @param array  $request
     * @param string $sessionId
     * @param array  $sessionElementImportList
     *
     * @throws Exception
     */
    public function saveElementsFromImport(
        $request,
        $sessionId,
        $sessionElementImportList,
        string $procedure,
        string $importDir,
    ): array {
        // Schreibe den Status des Imports im ein temporäres File
        // local file only, no need for flysystem
        $fs = new Filesystem();
        $statusHash = md5($sessionId.$procedure);
        $status = Json::encode(['bulkImportFilesTotal' => 0, 'bulkImportFilesProcessed' => 0]);
        try {
            $fs->dumpFile('uploads/files/importStatus_'.$statusHash.'.json', $status);
        } catch (IOException $e) {
            $this->logger->warning('Could not dump Statusfile: ', [$e]);
        }

        $this->getSession()->set('bulkImportFilesTotal', 0);
        $this->getSession()->set('bulkImportFilesProcessed', 0);

        $startElementId = null;
        $fileDir = $this->elementImportDirToArray($importDir);

        $errorReport = [];

        // gehe die zwischengespeicherte Liste der importierten Dateien durch
        $this->saveElementsFromDirArray(
            $fileDir,
            $startElementId,
            $sessionId,
            $procedure,
            $request,
            $sessionElementImportList,
            $errorReport
        );

        $this->getSession()->remove('bulkImportFilesTotal');
        $this->getSession()->remove('bulkImportFilesProcessed');

        try {
            $this->defaultStorage->deleteDirectory($importDir);
        } catch (FilesystemException $e) {
            $this->logger->error('Could not delete file: ', [$e]);
        }

        return $errorReport;
    }

    /**
     * Fetches the list of documents for the specified element and procedure.
     *
     * @throws Exception
     */
    public function getParaDocumentAdminList(string $procedureId, string $elementId): array
    {
        return $this->getParagraphService()->getParagraphDocumentAdminListAsObjects($procedureId, $elementId);
    }

    /**
     * Speichere die Elemente, die via Importer importiert werden.
     *
     * @param array       $entries
     * @param string      $elementId
     * @param string      $sessionId
     * @param string      $procedure
     * @param array       $request
     * @param array       $sessionElementImportList
     * @param string|null $category
     *
     * @return array|false
     *
     * @throws Exception
     */
    protected function saveElementsFromDirArray(
        $entries,
        $elementId,
        $sessionId,
        $procedure,
        $request,
        $sessionElementImportList,
        array &$errorReport,
        $category = null,
    ) {
        // used for local files only, no need for flysystem
        $fs = new Filesystem();
        $result = [];

        if (!is_array($errorReport)) {
            $errorReport = [];
        }

        /*
         * Context specific for the current $elementId, i.e. when the recursion steps down into the
         * next level a new index is started for that level.
         */
        $singleDocumentIndex = 0;
        $createdDocuments = [];

        foreach ($entries as $entry) {
            $fileName = utf8_decode((string) $entry['title']);
            if (in_array($entry['path'], $sessionElementImportList)) {
                $keys = array_keys($sessionElementImportList, $entry['path']);
                if (is_array($keys)
                    && isset($request[$keys[0]])
                    && 0 < strlen((string) $request[$keys[0]])
                ) {
                    $fileName = $request[$keys[0]];
                }
            }
            // Ordner werden als neue Elements abgespeichert
            if (true === $entry['isDir']) {
                $element = ['r_title' => $fileName];
                $element['r_publish_categories'] = (bool) ($request['r_publish_categories'] ?? false);
                // Ist es eine Unterkategorie?
                if (null !== $elementId) {
                    $element['r_parent'] = $elementId;
                }
                $result = $this->elementHandler->administrationElementNewHandler($procedure, $element);
                $resultElementId = $result['ident'];
                $category = $result['category'];
                // lege eine Kategorie an und übergebe die aktuelle Kategorie rekursiv
                $this->saveElementsFromDirArray(
                    $entry['entries'],
                    $resultElementId,
                    $sessionId,
                    $procedure,
                    $request,
                    $sessionElementImportList,
                    $errorReport,
                    $category
                );
            } else {
                // Wenn elementId null ist kann kein SingleDocument angelegt werden, deshalb mit dem nächsten Eintrag weiter machen
                if (null === $elementId) {
                    continue;
                }

                // speichere die Datei im Fileservice ab
                try {
                    // Viruscheck has been done for complete zip, so no check needed any more
                    $entry['path'] = $this->fileService->ensureLocalFile($entry['path'], $entry['title']);
                    $this->fileService->saveTemporaryLocalFile($entry['path'], $fileName, $this->currentUser->getUser()->getId(), $procedure, FileService::VIRUSCHECK_NONE);

                    $singleDocument = new SingleDocument();
                    $singleDocument->setTitle($fileName);
                    $singleDocument->setStatementEnabled(false);
                    $singleDocument->setDocument($this->fileService->getFileString());
                    $singleDocument->setProcedure($this->procedureService->getProcedureWithCertainty($procedure));
                    $singleDocument->setCategory($category);
                    $singleDocument->setElement($this->elementsService->getCategoryWithCertainty($elementId));
                    $singleDocument->setVisible(true);
                    $singleDocument->setDeleted(false);
                    $singleDocument->setOrder($singleDocumentIndex);

                    $violations = $this->validator->validate($singleDocument, null, [ResourceTypeService::VALIDATION_GROUP_DEFAULT, SingleDocument::IMPORT_CREATION]);
                    if (0 !== $violations->count()) {
                        throw ViolationsException::fromConstraintViolationList($violations);
                    }

                    // mark the document to be persisted
                    $createdDocuments[] = $singleDocument;
                    ++$singleDocumentIndex;

                    $this->getSession()->set(
                        'bulkImportFilesProcessed',
                        $this->getSession()->get('bulkImportFilesProcessed') + 1
                    );
                } catch (VirusFoundException $e) {
                    $this->getLogger()->error('Virus found in File ', [$e]);
                    $errorReport[] = $this->translator
                        ->trans('warning.virus.found', ['filename' => $e->getMessage()]);
                } catch (Exception $e) {
                    // Wennn eine einzelne Datei nicht hochgeladen werden darf oder ein sonstiger Fehler auftritt
                    // fahre trotzdem mit dem Import fort
                    $errorReport[] = 'Die Datei '.$fileName.' konnte nicht importiert werden.';
                }

                // save all the created documents
                $this->singleDocumentService->persistAndFlushNewPlanningDocumentsFromImport($createdDocuments);

                // Schreibe den Status des Imports im ein temporäres File
                $status = Json::encode(
                    [
                        'bulkImportFilesTotal'     => $this->getSession()->get('bulkImportFilesTotal'),
                        'bulkImportFilesProcessed' => $this->getSession()->get('bulkImportFilesProcessed'),
                    ]
                );
                try {
                    $statusHash = md5($sessionId.$procedure);
                    $fs->dumpFile('uploads/files/importStatus_'.$statusHash.'.json', $status);
                } catch (IOException $e) {
                    $this->logger->warning('could not update Statusfile: ', [$e]);
                }
            }
        }

        return $result;
    }

    /**
     * Liest die Verzeichnisstruktur des Planungsdokumentenimporters in ein Array ein.
     *
     * @param string $dir
     *
     * @return array
     */
    protected function elementImportDirToArray($dir)
    {
        $result = [];

        // Gehe rekursiv alle Verzeichnisse durch. Speichere Ordner als Elements, dateien als Files in den Elements
        $contents = $this->defaultStorage->listContents($dir, true);
        foreach ($contents as $item) {
            if ($item->isDir()) {
                $result[] = [
                    'isDir'   => true,
                    'title'   => basename($item->path()),
                    'path'    => $item->path(),
                    'entries' => $this->elementImportDirToArray(
                        $item->path()
                    ),
                ];
            } else {
                // utf8_decode filename, weil Zip Umlaute kaputt macht
                $filename = utf8_decode(basename($item->path()));

                $result[] = [
                    'isDir'  => false,
                    'title'  => $filename,
                    'path'   => $item->path(),
                ];

                // Speichere die Anzahl der Dateien in die Session
                $this->getSession()->set('bulkImportFilesTotal', $this->getSession()->get('bulkImportFilesTotal') + 1);
            }
        }
        // Sortiere die Elements natürlichsprachig
        usort($result, [self::class, 'sortElementsAlphabetically']);

        return $result;
    }

    /**
     * Sortiere die Titel der Elements natürlichsprachig.
     *
     * @param array $a
     * @param array $b
     *
     * @return int
     */
    public static function sortElementsAlphabetically($a, $b)
    {
        return strnatcasecmp((string) $a['title'], (string) $b['title']);
    }

    /**
     * @param string $procedure
     * @param string $elementId
     *
     * @throws InvalidArgumentException
     */
    public function reOrderParaDocument(array $requestPost, $procedure, $elementId)
    {
        $this->getParagraphService()
            ->reOrderParaDocument($requestPost, $procedure, $elementId);
    }

    /**
     * @param string $userOrgaId
     *
     * @throws Exception
     */
    public function hasProcedureElements(string $procedureId, $userOrgaId): bool
    {
        $procedure = $this->procedureService->getProcedure($procedureId);
        $outputResultElementList = $this->elementsService->getElementsListObjects(
            $procedureId,
            $userOrgaId,
            $userOrgaId === $procedure->getOrgaId()
        );

        $hasProcedureElements = false;

        foreach ($outputResultElementList as $element) {
            if ($element->getEnabled()
                && (ElementsInterface::ELEMENT_CATEGORIES['file'] === $element->getCategory()
                    || ElementsInterface::ELEMENT_CATEGORIES['paragraph'] === $element->getCategory())
            ) {
                $hasProcedureElements = true;
                break;
            }
        }

        return $hasProcedureElements;
    }

    /**
     * Verarbeitet alle Anfragen aus der Listenansicht.
     * Liefert eine Liste von Document.
     *
     * @param string $procedure
     * @param string $elementId
     *
     * @throws ReflectionException
     */
    public function getPublicParaDocuments($procedure, $elementId): array
    {
        $result = $this->getParagraphService()->getParaDocumentList($procedure, $elementId);

        // check whether User may
        if (0 < count($result)) {
            $firstParagraph = $result[0];
            if (array_key_exists('element', $firstParagraph)) {
                $element = $firstParagraph['element'];
                if ($element instanceof Elements && false === $element->getEnabled()) {
                    throw new RuntimeException('Access to this document is forbidden.');
                }
            }
        }

        return $result;
    }

    protected function getParagraphService(): ParagraphService
    {
        return $this->paragraphService;
    }
}
