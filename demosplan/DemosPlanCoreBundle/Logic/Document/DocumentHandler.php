<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Document;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\ElementsInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ElementImportJob;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Exception\VirusFoundException;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use DirectoryIterator;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DocumentHandler extends CoreHandler
{
    final public const ACTION_SINGLE_DOCUMENT_NEW = 'singledocumentnew';
    private const POSSIBLE_ENCODINGS = 'UTF-8, ISO-8859-1, ISO-8859-15';

    /**
     * How many imported documents are collected before they are written to the database.
     *
     * Trades memory held in the unit of work against the number of flushes; 200 keeps both
     * small enough for imports with tens of thousands of files.
     */
    private const IMPORT_FLUSH_BATCH_SIZE = 200;

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
        private readonly EntityManagerInterface $entityManager,
        private readonly FileService $fileService,
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
     * @param array            $request
     * @param array            $sessionElementImportList
     * @param ElementImportJob $job                      progress is reported on this row, which is what
     *                                                   the browser polls: the import runs in a worker,
     *                                                   so there is neither a session to keep counters
     *                                                   in nor a request to return them from
     *
     * @throws Exception
     */
    public function saveElementsFromImport(
        $request,
        $sessionElementImportList,
        string $procedure,
        string $importDir,
        ElementImportJob $job,
    ): array {
        $this->getSession()->set('bulkImportFilesTotal', 0);
        $this->getSession()->set('bulkImportFilesProcessed', 0);

        $startElementId = null;
        $fileDir = $this->elementImportDirToArray($importDir);

        $errorReport = [];

        // gehe die zwischengespeicherte Liste der importierten Dateien durch
        $this->saveElementsFromDirArray(
            $fileDir,
            $startElementId,
            $job,
            $procedure,
            $request,
            $sessionElementImportList,
            $errorReport
        );

        $this->getSession()->remove('bulkImportFilesTotal');
        $this->getSession()->remove('bulkImportFilesProcessed');

        // The extracted archive stays on local disk between extraction and this method, so the
        // leftovers (empty directories, and files of entries that could not be imported) are
        // removed locally rather than through flysystem.
        try {
            DemosPlanPath::recursiveRemoveLocalPath($importDir);
        } catch (Exception $e) {
            $this->logger->error('Could not delete import directory: ', [$e]);
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
        ElementImportJob $job,
        $procedure,
        $request,
        $sessionElementImportList,
        array &$errorReport,
        $category = null,
    ) {
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
        $createdFiles = [];

        foreach ($entries as $entry) {
            $fileName = $this->resolveImportFileName($entry, $sessionElementImportList, $request);

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
                    $job,
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
                    // $entry['path'] already points at the extracted file on local disk, so it can
                    // be handed to the file service directly. Viruscheck has been done for the
                    // complete zip, so no check is needed any more.
                    // flush: false — the File rows are written by the batch flush below. Letting
                    // saveTemporaryLocalFile() flush per file made every one of tens of thousands
                    // of files walk the whole unit of work.
                    $createdFiles[] = $this->fileService->saveTemporaryLocalFile(
                        $entry['path'],
                        $fileName,
                        $this->currentUser->getUser()->getId(),
                        $procedure,
                        FileService::VIRUSCHECK_NONE,
                        null,
                        false
                    );

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

                if (self::IMPORT_FLUSH_BATCH_SIZE <= count($createdDocuments)) {
                    $this->flushImportedDocuments($createdDocuments, $createdFiles, $job);
                }
            }
        }

        // Flush whatever is left over from the last, incomplete batch of this recursion level.
        $this->flushImportedDocuments($createdDocuments, $createdFiles, $job);

        return $result;
    }

    /**
     * Persist the documents buffered so far and publish the progress the browser polls.
     *
     * Both operations are batched rather than done per file. This method is reached from a
     * loop over every entry of the import, and the previous implementation persisted the
     * whole (growing) buffer on each iteration, making the database work quadratic in the
     * number of files — an import of ~37.000 files spent most of its runtime here.
     *
     * Both buffers are taken by reference so the caller's state is emptied together with the
     * flush; forgetting to reset them is what made the work quadratic in the first place.
     *
     * @param list<SingleDocument> $createdDocuments
     * @param list<File>           $createdFiles
     */
    private function flushImportedDocuments(array &$createdDocuments, array &$createdFiles, ElementImportJob $job): void
    {
        if ([] !== $createdDocuments) {
            $this->singleDocumentService->persistAndFlushNewPlanningDocumentsFromImport($createdDocuments);
        }

        $job->setFilesTotal((int) $this->getSession()->get('bulkImportFilesTotal'));
        $job->setFilesProcessed((int) $this->getSession()->get('bulkImportFilesProcessed'));
        $job->setModifiedDate(new DateTime());
        $this->entityManager->flush();

        $this->detachWrittenRows($createdDocuments, $createdFiles);
    }

    /**
     * Remove the rows just written from Doctrine's identity map.
     *
     * Batching the flushes alone does not make the import linear: every flush computes change
     * sets for everything the entity manager still manages, and that set keeps growing as the
     * import proceeds. Neither the documents nor the files are read again once written, so they
     * are detached, which keeps each flush proportional to the batch size instead of to the
     * number of files imported so far.
     *
     * Only these two types are detached rather than clearing the entity manager entirely: the
     * job, the procedure and the element categories are needed for the rest of the import and
     * would otherwise have to be re-fetched.
     *
     * @param list<SingleDocument> $createdDocuments
     * @param list<File>           $createdFiles
     */
    private function detachWrittenRows(array &$createdDocuments, array &$createdFiles): void
    {
        foreach ($createdDocuments as $createdDocument) {
            $this->entityManager->detach($createdDocument);
        }

        foreach ($createdFiles as $createdFile) {
            $this->entityManager->detach($createdFile);
        }

        $createdDocuments = [];
        $createdFiles = [];
    }

    /**
     * Resolves the final filename/folder name to use during import.
     *
     * Checks if the user provided a custom name for this entry during the import process.
     * If a user-adjusted name exists in the request data, it will be used instead of
     * the original filename. All names are normalized to UTF-8 encoding.
     *
     * @param array $entry                    The file/folder entry being processed (contains 'title' and 'path')
     * @param array $sessionElementImportList Session mapping of hashes to file paths
     * @param array $request                  The request data containing user-provided custom names
     *
     * @return string The resolved filename - either user-adjusted or original filename
     */
    private function resolveImportFileName(array $entry, array $sessionElementImportList, array $request): string
    {
        $fileName = (string) $entry['title'];
        // Ensure the string is properly encoded to UTF-8
        $fileName = mb_convert_encoding($fileName, 'UTF-8', mb_detect_encoding($fileName, self::POSSIBLE_ENCODINGS, true));
        $entryPath = '/'.ltrim($entry['path'], '/'); // Ensure leading slash
        if (in_array($entryPath, $sessionElementImportList)) {
            $keys = array_keys($sessionElementImportList, $entryPath);
            if (is_array($keys)
                && isset($request[$keys[0]])
                && 0 < strlen((string) $request[$keys[0]])
            ) {
                $fileName = $request[$keys[0]]; // here the name is taken from the request
                // Also ensure the string from request is properly encoded to UTF-8
                $fileName = mb_convert_encoding($fileName, 'UTF-8',
                    mb_detect_encoding($fileName, self::POSSIBLE_ENCODINGS, true));
            }
        }

        return $fileName;
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

        // Recursively go through all directories. Save folders as Elements, files as Files in the
        // Elements. The extracted archive is read from local disk: copying it into flysystem after
        // extraction only to read it back out here meant every file was moved twice for nothing.
        foreach (new DirectoryIterator($dir) as $item) {
            if ($item->isDot()) {
                continue;
            }

            if ($item->isDir()) {
                $result[] = [
                    'isDir'   => true,
                    'title'   => $item->getFilename(),
                    'path'    => $item->getPathname(),
                    'entries' => $this->elementImportDirToArray(
                        $item->getPathname()
                    ),
                ];
            } else {
                // Ensure proper UTF-8 encoding for filenames
                $filename = $item->getFilename();
                $filename = mb_convert_encoding($filename, 'UTF-8',
                    mb_detect_encoding($filename, self::POSSIBLE_ENCODINGS, true));

                $result[] = [
                    'isDir'  => false,
                    'title'  => $filename,
                    'path'   => $item->getPathname(),
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
