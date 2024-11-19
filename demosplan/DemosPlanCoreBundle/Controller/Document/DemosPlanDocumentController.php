<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Document;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\ElementsAdminListSaveEventInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\Document\AdministrateParagraphElementEvent;
use demosplan\DemosPlanCoreBundle\Event\Document\ElementsAdminListSaveEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\DemosFilesystem;
use demosplan\DemosPlanCoreBundle\Logic\Document\DocumentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphHandler;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use demosplan\DemosPlanCoreBundle\Logic\Document\SingleDocumentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Document\SingleDocumentService;
use demosplan\DemosPlanCoreBundle\Logic\EditorService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\Statement\CountyService;
use demosplan\DemosPlanCoreBundle\Logic\User\BrandingService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Services\Breadcrumb\Breadcrumb;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use DirectoryIterator;
use Exception;
use League\Flysystem\FilesystemOperator;
use Pagerfanta\Adapter\ArrayAdapter;
use Patchwork\Utf8;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use ZipArchive;
use ZipStream\ZipStream;

use function array_key_exists;
use function array_merge;
use function compact;
use function explode;
use function is_array;
use function set_time_limit;

/**
 * Seitenausgabe Planunterlagen.
 */
class DemosPlanDocumentController extends BaseController
{
    public function __construct(
        private readonly ElementHandler $elementHandler,
        private readonly ElementsService $elementsService,
        private readonly FileService $fileService,
        private readonly FilesystemOperator $defaultStorage,
        private readonly PermissionsInterface $permissions,
        private readonly SingleDocumentService $singleDocumentService,
    ) {
    }

    /**
     * @DplanPermissions("area_admin_paragraphed_document")
     *
     * @param string $procedure
     * @param string $elementId
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_plandocument_administration_element', path: '/verfahren/{procedure}/verwalten/element/{elementId}')]
    public function paragraphAdminSaveAction(
        DocumentHandler $documentHandler,
        ElementHandler $elementHandler,
        FileUploadService $fileUploadService,
        ParagraphService $paragraphService,
        Request $request,
        ServiceImporter $serviceImporter,
        $procedure,
        $elementId,
    ) {
        $route = 'DemosPlan_elements_administration_edit';

        $elementService = $this->elementsService;
        $element = $elementService->getElement($elementId);

        $requestPost = $request->request->all();

        if (array_key_exists('action', $requestPost) && 'uploadImportFile' === $requestPost['action']) {
            try {
                $uploadedFile = $fileUploadService->prepareFilesUpload($request, 'r_upload');
                $serviceImporter->uploadImportFile($element['id'], $procedure, $uploadedFile);
            } catch (Exception) {
                // message bag has already been filled in uploadImportFile
            }
        }

        // Formulardaten verarbeiten
        if (array_key_exists('delete_item', $requestPost) && array_key_exists('document_delete', $requestPost)) {
            $inData = $this->prepareIncomingData($request, 'documentdelete');
            // Storage Formulardaten übergeben
            $storageResult = $paragraphService->deleteParaDocument($inData['document_delete']);
            // generiere eine Erfolgsmeldung
            if ($storageResult) {
                $this->getMessageBag()->add('confirm', 'confirm.paragraph.marked.deleted');

                return $this->redirectToRoute($route, compact('procedure', 'elementId'));
            }
        }

        if (array_key_exists('r_action', $requestPost) && 'updateParagraphPDF' === $requestPost['r_action']) {
            $inData = $this->prepareIncomingData($request, 'updateParagraphPDF');
            $inData['PDF'] = $fileUploadService->prepareFilesUpload($request, 'r_planPDF');
            $storageResult = $elementHandler->updateParagraphElementFile($elementId, $inData);
            // generiere eine Erfolgsmeldung
            if (false !== $storageResult && !array_key_exists('fault', $storageResult)) {
                $this->getMessageBag()->add('confirm', 'confirm.file.updated');

                return $this->redirectToRoute($route, compact('procedure', 'elementId'));
            }
        }

        if (array_key_exists('r_moveUp', $requestPost) || array_key_exists('r_moveDown', $requestPost)) {
            try {
                $documentHandler->reOrderParaDocument(
                    $requestPost,
                    $procedure,
                    $elementId
                );
            } catch (InvalidArgumentException) {
                $this->getMessageBag()->add('warning', 'warning.paragraph.ordering.level.mismatch');
            }
        }

        return $this->redirectToRoute('DemosPlan_elements_administration_edit', ['procedure' => $procedure, 'elementId' => $elementId]);
    }

    /**
     * Generates the requried data for paragraph_admin_list.html.twig.
     *
     * @return array|RedirectResponse|Response
     *
     * @throws Exception
     */
    protected function generateDataForAdminList(
        Breadcrumb $breadcrumb,
        Request $request,
        CurrentProcedureService $currentProcedureService,
        FileUploadService $fileUploadService,
        ParagraphService $paragraphService,
        ServiceImporter $serviceImporter,
        ElementHandler $elementHandler,
        string $procedure,
        array $element,
    ) {
        try {
            $templateVars = [];
            $templateVars['procedureCurrentElementId'] = $element['id'];
            $requestPost = $request->request->all();

            if (array_key_exists('action', $requestPost) && 'uploadImportFile' === $requestPost['action']) {
                $uploadedFile = $fileUploadService->prepareFilesUpload($request, 'r_upload');
                try {
                    $serviceImporter->uploadImportFile($element['id'], $procedure, $uploadedFile);
                } catch (Exception) {
                    // message bag has already been filled in uploadImportFile
                }
            }

            // @improve T16805
            // Formulardaten verarbeiten
            if (array_key_exists('document_delete', $requestPost)) {
                $inData = $this->prepareIncomingData($request, 'documentdelete');
                $storageResult = $paragraphService->deleteParaDocument($inData['document_delete']);
                // generiere eine Erfolgsmeldung
                if ($storageResult) {
                    $this->getMessageBag()->add('confirm', 'confirm.paragraph.marked.deleted');
                }
            }

            // @improve T16805
            if (array_key_exists('r_action', $requestPost) && 'updateParagraphPDF' === $requestPost['r_action']) {
                if ('' === $requestPost['uploadedFiles'] && !array_key_exists('r_planDelete', $requestPost)) {
                    $this->getMessageBag()->add('warning', 'explanation.file.noupload');
                } else {
                    $inData = $this->prepareIncomingData($request, 'updateParagraphPDF');
                    $inData['PDF'] = $fileUploadService->prepareFilesUpload($request, 'r_planPDF');
                    $storageResult = $elementHandler->updateParagraphElementFile($element['id'], $inData);
                    // generiere eine Erfolgsmeldung

                    if (false !== $storageResult && !array_key_exists('fault', $storageResult)) {
                        $this->getMessageBag()->add('confirm', 'confirm.file.updated');
                    }

                    $element['file'] = $storageResult['file'];
                }
            }

            // Template Variable aus Storage Ergebnis erstellen(Output)
            $sResult = $paragraphService->getParaDocumentAdminList($procedure, $element['id'], null, true, true);
            $templateVars['list'] = [
                'documentlist' => $sResult['result'],
                'filters'      => $sResult['filterSet'],
                'sort'         => $sResult['sortingSet'],
            ];
            $templateVars['procedure'] = $currentProcedureService->getProcedureArray();
            $templateVars['elementFile'] = $element['file'];
            $templateVars['contextualHelpBreadcrumb'] = $breadcrumb->getContextualHelp($element['title']);
            $templateVars['category'] = 'paragraph';
            $templateVars['deleteEnable'] = true;

            return [
                'templateVars' => $templateVars,
                'procedure'    => $procedure,
                'elementId'    => $element['id'],
                'category'     => 'paragraph',
                'title'        => $element['title'],
            ];
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Planunterlagen Absatz Edit.
     *
     * @DplanPermissions("area_admin_paragraphed_document")
     *
     * @param string $procedure
     * @param string $documentID
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_plandocument_administration_paragraph_edit', path: '/verfahren/{procedure}/verwalten/paragraph/{documentID}')]
    public function paragraphAdminEditAction(
        Breadcrumb $breadcrumb,
        DocumentHandler $documentHandler,
        EditorService $editorService,
        ParagraphHandler $paragraphHandler,
        ParagraphService $paragraphService,
        Request $request,
        TranslatorInterface $translator,
        $procedure,
        $documentID,
    ) {
        // Storage und Output initialisieren
        $paragraphDocument = $paragraphService->getParaDocument($documentID);
        $elementId = $paragraphDocument['elementId'];
        $title = $paragraphDocument['element']->getTitle();

        $requestPost = $request->request->all();
        $tmpImagePlaceholder = $translator->trans('image.placeholder');

        // Formulardaten verarbeiten
        if (!empty($requestPost['action']) && 'documentedit' === $requestPost['action']) {
            $inData = $this->prepareIncomingData($request, 'documentedit');

            // Ersetze den Platzhalter für hochgeladene Bilder vor dem Speichern
            $inData['r_text'] = str_replace($tmpImagePlaceholder, '', (string) $inData['r_text']);

            $inData['r_text'] = $editorService->replaceAlternativeTextPlaceholderByHTMLTag($inData['r_text']);
            // Storage Formulardaten übergeben
            if (null !== $inData) {
                if (!$this->permissions->hasPermission('field_paragraph_lock_statement')
                 && array_key_exists('r_visible', $inData)
                 && '2' === $inData['r_visible']) {
                    throw new Exception("Tried to set a paragraph to 'locked'-state despite the locked permission not being active");
                }
                $storageResult = $paragraphHandler->administrationDocumentEditHandler($procedure, $inData, $elementId);

                // Wenn Storage erfolgreich: zurueck zur Liste
                if (is_array($storageResult)
                    && array_key_exists('ident', $storageResult)
                    && !array_key_exists('mandatoryfieldwarning', $storageResult)
                ) {
                    $this->getMessageBag()->add('confirm', 'confirm.all.changes.saved');

                    return $this->redirectToRoute(
                        'DemosPlan_elements_administration_edit',
                        [
                            'procedure' => $procedure,
                            'elementId' => $elementId,
                        ]
                    );
                }
            }
        }

        // Ausgabe des Formulars

        $templateVars = ['document' => $paragraphDocument];
        // get all documents/paragraphs of procedure:
        $templateVars['relatedDocuments'] = $documentHandler->getParaDocumentAdminList($procedure, $elementId);

        // Falls ein Bild importiert wurde, stelle einen Platzhalter dar
        $templateVars['document']['text'] = $editorService->addImagePlaceholdersToStringFromDatabase($templateVars['document']['text']);
        // Falls das Bild alt Text hat, ersetze den HTML-Tag mit einem Editor-Tag
        $templateVars['document']['text'] = $editorService->replaceHtmlAltTextTagByAlternativeTextPlaceholder($templateVars['document']['text']);

        // reichere die breadcrumb mit extraItem an (kategorie)

        $breadcrumb->addItem(
            [
                'title' => $title,
                'url'   => $this->generateUrl(
                    'DemosPlan_plandocument_administration_element',
                    ['procedure' => $procedure, 'elementId' => $elementId]
                ),
            ]
        );

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanDocument/paragraph_admin_edit.html.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedure,
                'category'     => 'paragraph',
                'elementId'    => $elementId,
                'title'        => $translator->trans('paragraph.edit', [], 'page-title'),
            ]
        );
    }

    /**
     * Planunterlagen Absatz - Neu.
     *
     * @DplanPermissions("area_admin_paragraphed_document")
     *
     * @param string $procedure
     * @param string $elementId
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_plandocument_administration_paragraph_new', path: '/verfahren/{procedure}/verwalten/paragraph/neu/{elementId}')]
    public function paragraphAdminNewAction(
        Breadcrumb $breadcrumb,
        DocumentHandler $documentHandler,
        ParagraphHandler $paragraphHandler,
        Request $request,
        TranslatorInterface $translator,
        $procedure,
        $elementId,
    ) {
        // get Element -> get Title
        $elementService = $this->elementsService;
        $element = $elementService->getElement($elementId);

        $title = $element['title'];
        $category = 'paragraph';

        $templateVars = [];
        $templateVars['procedure'] = $procedure;

        // Formulardaten verarbeiten
        $requestPost = $request->request->all();

        $requestGet = $request->query->all();
        if (isset($requestGet['elementId'])) {
            $templateVars['procedureCurrentElementId'] = $requestGet['elementId'];
        }

        // Formulardaten verarbeiten
        if (!empty($requestPost['r_action']) && 'documentnew' === $requestPost['r_action']) {
            $inData = $this->prepareIncomingData($request, 'documentnew');

            // Storage Formulardaten übergeben
            if (null !== $inData) {
                $storageResult = $paragraphHandler->administrationDocumentNewHandler($procedure, $category, $inData, $elementId);

                // Wenn Storage erfolgreich: zurueck zur Liste
                if (array_key_exists('ident', $storageResult) && !array_key_exists('mandatoryfieldwarning', $storageResult)) {
                    // Erfolgsmeldung
                    $this->getMessageBag()->add('confirm', 'confirm.paragraph.new');

                    return $this->redirectToRoute('DemosPlan_elements_administration_edit', [
                        'procedure' => $procedure,
                        'elementId' => $elementId,
                    ]);
                }
            }
        }

        // get all documents/paragraphs of procedure:

        $templateVars['relatedDocuments'] = $documentHandler->getParaDocumentAdminList($procedure, $elementId);

        // reichere die breadcrumb mit extraItem an (kategorie)
        // da hier keine breadcrumb-Items, auch noch die element.list.admin transation hinzufügen:
        $breadcrumb->addItem(
            ['title'  => $translator->trans('element.list.admin', [], 'page-title'),
                'url' => $this->generateUrl('DemosPlan_element_administration', ['procedure' => $procedure]), ]);

        $breadcrumb->addItem(
            ['title'  => $title,
                'url' => $this->generateUrl('DemosPlan_plandocument_administration_element', ['procedure' => $procedure, 'elementId' => $elementId]), ]);

        // Ausgabe
        return $this->renderTemplate('@DemosPlanCore/DemosPlanDocument/paragraph_admin_new.html.twig', [
            'templateVars' => $templateVars,
            'procedure'    => $procedure,
            'category'     => $category,
            'elementId'    => $elementId,
            'title'        => $translator->trans('paragraph.new', [], 'page-title'),
        ]);
    }

    /**
     * Planunterlagen Einzeldokument Neu.
     *
     * @DplanPermissions("area_admin_single_document")
     *
     * @param string $procedure
     * @param string $elementId
     * @param string $category
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_singledocument_administration_new', path: '/verfahren/{procedure}/verwalten/planunterlagen/dokument/{elementId}/neu/{category}')]
    public function singleDocumentAdminNewAction(
        Breadcrumb $breadcrumb,
        FileUploadService $fileUploadService,
        Request $request,
        SingleDocumentHandler $singleDocumentHandler,
        TranslatorInterface $translator,
        $procedure,
        $elementId,
        $category,
    ) {
        $templateVars = [];
        $templateVars['procedure'] = $procedure;

        $requestPost = $request->request->all();
        $templateVars['request'] = $requestPost;

        if (!empty($requestPost['action']) && DocumentHandler::ACTION_SINGLE_DOCUMENT_NEW === $requestPost['action']) {
            $inData = $this->prepareIncomingData($request, DocumentHandler::ACTION_SINGLE_DOCUMENT_NEW);
            $inData['r_document'] = $fileUploadService->prepareFilesUpload($request, 'r_document');
            // Storage Formulardaten übergeben
            if (null !== $inData) {
                $inData = $this->calculateSingleDocumentTitleNewFile($inData);
                $storageResult = $singleDocumentHandler->administrationDocumentNewHandler($procedure, $category, $elementId, $inData);

                // Wenn Storage erfolgreich: zurueck zur Liste
                if (array_key_exists('ident', $storageResult) && !array_key_exists('mandatoryfieldwarning', $storageResult)) {
                    // Bestätigungsnachricht
                    $this->getMessageBag()->add('confirm', 'confirm.plandocument.saved');

                    return $this->redirectToRoute(
                        'DemosPlan_elements_administration_edit',
                        compact('procedure', 'elementId')
                    );
                }
            }
        }
        // Reichere die breadcrumb mit extraItem an (Planungsdokumente)
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('element.list.admin', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_element_administration', ['procedure' => $procedure]),
            ]
        );
        $elementService = $this->elementsService;
        $element = $elementService->getElement($elementId);
        $breadcrumb->addItem(
            [
                'title' => $element['title'],
                'url'   => $this->generateUrl('DemosPlan_elements_administration_edit', ['procedure' => $procedure, 'elementId' => $elementId]),
            ]
        );

        return $this->renderTemplate('@DemosPlanCore/DemosPlanDocument/single_document_admin_new.html.twig', [
            'templateVars' => $templateVars,
            'procedure'    => $procedure,
            'category'     => $category,
            'title'        => 'element.detail.document.add',
            'elementId'    => $elementId,
        ]);
    }

    /**
     * Planunterlagen Einzeldokument Edit.
     *
     * @DplanPermissions("area_admin_single_document")
     *
     * @param string $procedure
     * @param string $documentID
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_singledocument_administration_edit', path: '/verfahren/{procedure}/verwalten/planunterlagen/dokument/{documentID}/edit', options: ['expose' => true])]
    public function singleDocumentAdminEditAction(
        Breadcrumb $breadcrumb,
        FileUploadService $fileUploadService,
        PermissionsInterface $permissions,
        Request $request,
        SingleDocumentHandler $singleDocumentHandler,
        TranslatorInterface $translator,
        $procedure,
        $documentID,
    ) {
        $templateVars = [];
        $templateVars['procedure'] = $procedure;

        // Formulardaten verarbeiten
        $requestPost = $request->request->all();

        $singleDocumentService = $this->singleDocumentService;
        $templateVars['document'] = $singleDocumentService->getSingleDocument($documentID);
        // Formulardaten verarbeiten

        if (!empty($requestPost['r_action']) && 'singledocumentedit' === $requestPost['r_action']) {
            $inData = $this->prepareIncomingData($request, 'singledocumentedit');
            $inData['r_document'] = $fileUploadService->prepareFilesUpload($request, 'r_document');
            // Storage Formulardaten übergeben
            if (null !== $inData) {
                if (!$permissions->hasPermission('field_procedure_single_document_title')) {
                    unset($inData['r_title']);
                }

                $inData['r_ident'] = $documentID;

                $storageResult = $singleDocumentHandler->administrationDocumentEditHandler($inData);

                // Wenn Storage erfolgreich: zurueck zur Liste
                if (array_key_exists('ident', $storageResult) && !array_key_exists('mandatoryfieldwarning', $storageResult)) {
                    // Bestätigungsnachricht
                    $this->getMessageBag()->add('confirm', 'confirm.plandocument.updated');

                    return $this->redirectToRoute(
                        'DemosPlan_elements_administration_edit',
                        [
                            'procedure' => $procedure,
                            'elementId' => $storageResult['elementId'],
                        ]
                    );
                }
            }
        }

        // Reichere die breadcrumb mit extraItem an (Planungsdokumente)
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('element.list.admin', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_element_administration', ['procedure' => $procedure]),
            ]
        );
        $singleDocumentService = $this->singleDocumentService;
        $singleDocument = $singleDocumentService->getSingleDocument($documentID);
        if ($singleDocument['element'] instanceof Elements) {
            $breadcrumb->addItem(
                [
                    'title' => $singleDocument['element']->getTitle(),
                    'url'   => $this->generateUrl(
                        'DemosPlan_elements_administration_edit',
                        [
                            'procedure' => $procedure,
                            'elementId' => $singleDocument['element']->getId(),
                        ]
                    ),
                ]
            );
        }

        // Ausgabe
        return $this->renderTemplate('@DemosPlanCore/DemosPlanDocument/single_document_admin_edit.html.twig', [
            'templateVars' => $templateVars,
            'procedure'    => $procedure,
            'title'        => 'element.detail.document.edit',
            'documentID'   => $documentID,
        ]);
    }

    /**
     * reset document title from filename if title could not be explicitly set.
     *
     * @param array $inData
     */
    protected function calculateSingleDocumentTitleNewFile($inData)
    {
        if (!$this->permissions->hasPermission('field_procedure_single_document_title')) {
            $serviceSingleDocument = $this->singleDocumentService;
            $inData['r_title'] = $serviceSingleDocument->convertSingleDocumentTitle($inData['r_document']);
        }

        return $inData;
    }

    /**
     * Planunterlagen Kategorie Adminliste.
     *
     * @DplanPermissions("area_admin_single_document")
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_element_administration', path: '/verfahren/{procedure}/verwalten/planunterlagen', options: ['expose' => true])]
    public function elementAdminListAction(
        Breadcrumb $breadcrumb,
        CurrentUserInterface $currentUser,
        CurrentProcedureService $currentProcedureService,
        MapService $mapService,
        ProcedureHandler $procedureHandler,
        Request $request,
        string $procedure,
        TranslatorInterface $translator)
    {
        $templateVars = [];
        $session = $request->getSession();
        $title = $translator->trans('elements.dashboard');

        $currentProcedureArray = $currentProcedureService->getProcedureArray();

        /*
         * Remove files from the session when import was cancelled
         * @see DemosPlanDocumentController::importElementDirToArraySaveHashInSession
         * */
        if ($session->has('element_import_list')) {
            $this->cleanElementImport($request, $currentProcedureArray['id'], $currentUser->getUser());
        }

        /**
         * Collect errors, if any, produced during the saving of imported elements
         * and store them in a variable to display in the UI.
         *
         * @see DemosPlanDocumentController::saveImportedElementsAdminAction
         * */
        $errorReports = $session->getFlashBag()->get('errorReports');
        $templateVars['errorReport'] = [];

        if ((is_countable($errorReports) ? count($errorReports) : 0) > 0) {
            $templateVars['errorReport'] = $errorReports[0];
        }

        $templateVars['procedure'] = $procedureHandler->getProcedure($procedure);

        $templateVars['contextualHelpBreadcrumb'] = $breadcrumb->getContextualHelp($title);
        $mapOptions = $mapService->getMapOptions($procedure);
        $templateVars['procedureDefaultInitialExtent'] = $mapOptions->getProcedureDefaultInitialExtent();

        $procedureSettings = $currentProcedureArray['settings'];

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanDocument/elements_admin_list.html.twig',
            compact('templateVars', 'title', 'procedureSettings')
        );
    }

    /**
     * Save imported elements and redirect to route: @see elementAdminListAction.
     *
     * @DplanPermissions("area_admin_single_document")
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_save_imported_elements_administration', path: '/verfahren/{procedure}/verwalten/planunterlagen/import/speichern', options: ['expose' => true])]
    public function saveImportedElementsAdminAction(
        CurrentUserInterface $currentUser,
        CurrentProcedureService $currentProcedureService,
        DocumentHandler $documentHandler,
        Request $request,
        EventDispatcherInterface $eventDispatcher,
        string $procedure)
    {
        $session = $request->getSession();

        // Set the Max_execution_time for the import
        set_time_limit(3600);

        $currentProcedureArray = $currentProcedureService->getProcedureArray();
        $requestPost = $request->request->all();

        if ($request->isMethod('POST')) {
            // if you need the event, this method returns it :)
            $eventDispatcher->dispatch(
                new ElementsAdminListSaveEvent($request),
                ElementsAdminListSaveEventInterface::class
            );
        }

        $sessionElementImportList = $session->get('element_import_list');
        $errorReport = $documentHandler->saveElementsFromImport(
            $requestPost,
            $session->get('sessionId'),
            $sessionElementImportList,
            $procedure,
            $this->getElementImportDir($currentProcedureArray['id'], $currentUser->getUser())
        );

        // Redirect so that the documents are not recharged with a reload and the files are displayed immediately
        $session->getFlashBag()->add('errorReports', $errorReport);

        return $this->redirectToRoute('DemosPlan_element_administration', ['procedure' => $procedure]);
    }

    /**
     * Importer für die Planungsdokumentenkategorien und Dateien.
     *
     * @DplanPermissions({"area_admin_single_document","feature_admin_element_import"})
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_element_import', path: '/verfahren/{procedureId}/verwalten/planunterlagen/import')]
    public function elementAdminImportAction(
        CurrentUserInterface $currentUser,
        Request $request,
        FileUploadService $fileUploadService,
        FileService $fileService,
        string $procedureId,
    ) {
        $templateVars = [];
        $session = $request->getSession();
        $session->remove('element_import_list');
        $fs = new DemosFilesystem();

        $path = DemosPlanPath::getPublicPath('uploads/files');

        // Lösche das alte Statusfile zum Importstatus
        $statusHash = md5($session->getId().$procedureId);
        try {
            $fs->remove($path.'/importStatus_'.$statusHash.'.json');
        } catch (Exception) {
        }

        $uploadedFileArray = $fileUploadService->prepareFilesUpload($request);

        // Prüfe, ob eine Datei hochgeladen wurde
        if (!is_array($uploadedFileArray) || 1 !== count($uploadedFileArray)) {
            // Fehlernachricht
            $this->getMessageBag()->add('error', 'error.elementimport.empty');

            return $this->redirectToRoute('DemosPlan_element_administration', ['procedure' => $procedureId]);
        }

        $uploadedFileInfo = $fileService->getFileInfoFromFileString($uploadedFileArray['r_zipImport']);

        // Prüfe, ob die hochgeladene Datei wirdklich ein zip ist
        if ('application/zip' !== $uploadedFileInfo->getContentType()) {
            // Fehlernachricht
            $this->getMessageBag()->add('error', 'error.elementimport.ziponly');

            return $this->redirectToRoute('DemosPlan_element_administration', ['procedure' => $procedureId]);
        }

        $extractDir = $this->getElementImportDir($procedureId, $currentUser->getUser());
        // zip file needs to be local to extract files. So move it to the import dir
        $uploadedZipFileLocal = $fileService->ensureLocalFileFromHash($uploadedFileInfo->getHash());
        $zip = new ZipArchive();
        $res = $zip->open($uploadedZipFileLocal);
        $successFiles = 0;
        $folderCount = 0;
        if (true === $res) {
            for ($indexInZipFile = 0; $indexInZipFile < $zip->numFiles; ++$indexInZipFile) {
                $filenameOrig = $zip->getNameIndex($indexInZipFile);

                // Nur Dateien müssen behandelt werden, Ordner werden automatisch angelegt
                if (str_ends_with($filenameOrig, '/')) {
                    ++$folderCount;
                    continue;
                }
                // files at top level could not be imported because we need an elementId later on
                if (!str_contains($filenameOrig, '/')) {
                    $this->getMessageBag()->add('warning', 'warning.document.import.toplevel');
                    continue;
                }
                $fileinfo = pathinfo($filenameOrig);

                // T5659 only filter filenames for bad chars, do not translit
                $filename = Utf8::filter($fileinfo['basename']);
                $dirname = Utf8::filter($fileinfo['dirname']);

                // T8843 zip-slip: check whether path is in valid location
                $destination = $extractDir.'/'.$dirname;
                // if path contains any relative path immediately skip file
                if (0 !== mb_substr_count($destination, '../')) {
                    $this->getLogger()->error('Possible Zip-slip-Attack. File not extracted. Destination:'.DemosPlanTools::varExport($destination, true));
                    continue;
                }

                // Falls gar kein valider Filename ermittelt werden konnte, lieber einen Hash als nix
                if ('' == $filename) {
                    $filename = md5(random_int(0, 9999));
                    $this->getLogger()->warning('Es konnte via kein gültiger Name gefunden werden. RandomHash: '.DemosPlanTools::varExport($filename, true));
                } else {
                    ++$successFiles;
                }

                $this->getLogger()->info('DocumentImport set Filename '.DemosPlanTools::varExport($filename, true).' Dirname: '.DemosPlanTools::varExport($dirname, true).
                    ' Orig base64encoded: '.DemosPlanTools::varExport(base64_encode($filenameOrig), true));
                $zip->renameIndex($indexInZipFile, $dirname.'/'.$filename);
                $zip->extractTo($extractDir, $zip->getNameIndex($indexInZipFile));
            }

            if ($indexInZipFile != $successFiles + $folderCount) {
                $this->getMessageBag()->add('warning', 'error.elementimport.unpacking_failed');
            }

            $templateVars['totalFiles'] = $indexInZipFile - $folderCount;
            $templateVars['importedFiles'] = $successFiles;

            $zip->close();

            // Lösche das hochgeladene Zipfile, es wird nicht mehr benötigt
            $fileService->deleteFile($uploadedFileInfo->getHash());
            $fileService->deleteFile($uploadedZipFileLocal);
        } else {
            $this->logger->warning('Could not open Zip file. Reason: '.$res);
            $this->getMessageBag()->add('error', 'error.elementimport.cantopen');

            // Lösche das hochgeladene Zipfile
            $fileService->deleteFile($uploadedFileInfo->getHash());

            return $this->redirectToRoute('DemosPlan_element_administration', ['procedure' => $procedureId]);
        }
        $fileDir = $this->importElementDirToArraySaveHashInSession($extractDir, $session);

        $templateVars['procedure'] = $procedureId;
        $templateVars['statusHash'] = $statusHash;
        $templateVars['basePath'] = $request->getBasePath();

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanDocument/elements_admin_import.html.twig',
            [
                'entries'      => $fileDir,
                'templateVars' => $templateVars,
            ]
        );
    }

    /**
     * Create Variables needed for pagination of paragraphLists.
     *
     * @param array<string,mixed> $templateVars
     *
     * @return array
     */
    protected function paginateParagraphList(Request $request, array $templateVars)
    {
        $adapter = new ArrayAdapter($templateVars['list']['documentlist']);
        $documentlistPager = new DemosPlanPaginator($adapter);
        $documentlistPager->setLimits([1, 3, 10, 25]);
        // current Page must be at least 1
        $currentPage = $request->get('page', 1) > 0 ? $request->get('page', 1) : 1;

        try {
            $documentlistPager->setMaxPerPage((int) $request->get('r_limit', 3));
            $documentlistPager->setCurrentPage($currentPage);
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not set paginate: ', [$e]);

            // use default Values to avoid corrupt interface
            $documentlistPager->setMaxPerPage(3);
            $documentlistPager->setCurrentPage(1);
        }
        $templateVars['pager'] = $documentlistPager;
        $templateVars['totalResults'] = is_countable($templateVars['list']['documentlist']) ? count($templateVars['list']['documentlist']) : 0;
        $templateVars['limitResults'] = $documentlistPager->getMaxPerPage();
        // pass only rootlevel paragraphs to toc. It is generated recursively
        $documentlistTocRootOnly = [];
        foreach ($templateVars['list']['documentlist'] as $paragraph) {
            if (is_null($paragraph['parent'])) {
                $documentlistTocRootOnly[] = $paragraph;
            }
        }
        $templateVars['list']['documentlistToc'] = $this->generateTocStructure($documentlistTocRootOnly);
        $templateVars['list']['documentlist'] = $documentlistPager->getCurrentPageResults();

        return $templateVars;
    }

    /**
     * Generate array with all paragraphs and children to be displayed in toc.
     *
     * @param array $paragraphs
     *
     * @return array
     */
    protected function generateTocStructure($paragraphs)
    {
        $returnParagraphs = [];
        foreach ($paragraphs as $paragraph) {
            // atm we have legcyarrays at first level and objects below
            if ($paragraph instanceof Paragraph) {
                if (0 == $paragraph->getVisible()) {
                    continue;
                }
                if (0 < (is_countable($paragraph->getChildren()) ? count($paragraph->getChildren()) : 0)) {
                    $paragraph->setChildren($this->generateTocStructure($paragraph->getChildren()));
                }
            } else {
                if (0 == $paragraph['visible']) {
                    continue;
                }
                if (0 < (is_countable($paragraph['children']) ? count($paragraph['children']) : 0)) {
                    $paragraph['children'] = $this->generateTocStructure($paragraph['children']);
                }
            }

            $returnParagraphs[] = $paragraph;
        }

        return $returnParagraphs;
    }

    /**
     * Liest die Verzeichnisstruktur des Planungsdokumentenimporters in ein Array ein.
     *
     * @param string $dir
     *
     * @return array
     *
     * @throws Exception
     */
    protected function importElementDirToArraySaveHashInSession($dir, Session $session)
    {
        $result = [];

        // Gehe rekursiv alle Verzeichnisse durch. Speichere Ordner als Elements, dateien als Files in den Elements
        // at this point local files need to be used, no flysystem needed
        $iter = new DirectoryIterator($dir);
        foreach ($iter as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }
            // Speichere einen Hash für die Datei in der Session, damit auf einer Zwischenseite der Name
            // der Datei geändert werden kann

            if ($fileInfo->isDir()) {
                $hash = 'folder_'.random_int(1, 99_999_999);
                $result[] = [
                    'isDir'   => true,
                    'title'   => $fileInfo->getFilename(),
                    'hash'    => $hash,
                    'entries' => $this->importElementDirToArraySaveHashInSession(
                        $fileInfo->getPathname(),
                        $session
                    ),
                ];
                $this->defaultStorage->createDirectory($fileInfo->getPathname());
            } else {
                $hash = 'file_'.random_int(1, 99_999_999);
                // T5659 only filter filenames, do not translit
                $filename = Utf8::filter($fileInfo->getFilename());

                $result[] = [
                    'isDir' => false,
                    'title' => $filename,
                    'hash'  => $hash,
                ];
                $stream = fopen($fileInfo->getPathname(), 'rb+');
                $this->defaultStorage->writeStream($fileInfo->getPathname(), $stream);
                fclose($stream);
            }
            $sessionImportList = $session->get('element_import_list');
            $sessionImportList[$hash] = $fileInfo->getPathname();
            $session->set('element_import_list', $sessionImportList);
        }
        $fs = new Filesystem();
        $fs->remove($dir);

        // Sortiere die Elements natürlichsprachig
        usort($result, [DocumentHandler::class, 'sortElementsAlphabetically']);

        return $result;
    }

    /**
     * Clean all artefacts used by the element Importer.
     */
    protected function cleanElementImport(Request $request, string $procedureId, User $user)
    {
        try {
            $request->getSession()->remove('element_import_list');
            if (is_dir($this->getElementImportDir($procedureId, $user))) {
                DemosPlanPath::recursiveRemoveLocalPath($this->getElementImportDir($procedureId, $user));
            }
        } catch (Exception) {
        }
    }

    /**
     * Planunterlagen Kategorie Admin-Edit.
     *
     * @DplanPermissions("area_admin_single_document")
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_elements_administration_edit', path: '/verfahren/{procedure}/verwalten/planunterlagen/{elementId}/edit', options: ['expose' => true])]
    public function elementAdminEditAction(
        Breadcrumb $breadcrumb,
        CurrentProcedureService $currentProcedureService,
        ElementHandler $elementHandler,
        FileUploadService $fileUploadService,
        ParagraphService $paragraphService,
        Request $request,
        ServiceImporter $serviceImporter,
        ServiceOutput $serviceOutput,
        TranslatorInterface $translator,
        EventDispatcherPostInterface $eventDispatcherPost,
        string $procedure,
        string $elementId,
    ) {
        // Storage und Output initialisieren
        $elementService = $this->elementsService;
        $singleDocumentService = $this->singleDocumentService;
        $requestPost = $request->request->all();

        if (!empty($requestPost['r_action']) && 'singledocumentdelete' === $requestPost['r_action'] && array_key_exists('document_delete', $requestPost)) {
            // Storage Formulardaten übergeben
            $storageResult = $singleDocumentService->deleteSingleDocument($requestPost['document_delete']);
            if (true === $storageResult) {
                // Erfolgsmeldung
                $this->getMessageBag()->add('confirm', 'confirm.plandocument.deleted');
            }
        }

        if (!empty($requestPost['r_action']) && 'saveSort' === $requestPost['r_action'] && array_key_exists('r_sorting', $requestPost)) {
            // Storage Formulardaten übergeben
            $sortArray = explode(', ', (string) $requestPost['r_sorting']);
            $storageResult = $singleDocumentService->sortDocuments($sortArray);
            if ($storageResult) {
                // Erfolgsmeldung
                $this->getMessageBag()->add('confirm', 'confirm.plandocument.sorted');
            }
        }

        if (!empty($requestPost['r_action']) && 'elementedit' === $requestPost['r_action']) {
            $inData = $this->prepareIncomingData($request, 'elementedit');

            // Storage Formulardaten übergeben
            if (null !== $inData) {
                $inData['r_picture'] = $fileUploadService->prepareFilesUpload($request);

                if (array_key_exists('deleteCategory', $requestPost)) {
                    if (!$this->permissions->hasPermission('feature_admin_element_edit')) {
                        $this->getMessageBag()->add('error', 'error.without.authorization');

                        return $this->redirectToRoute('DemosPlan_element_administration', compact('procedure'));
                    }

                    $storageResult = $elementHandler->administrationElementDeleteHandler($inData['r_ident']);
                    if ($storageResult) {
                        $this->getMessageBag()->add('confirm', 'confirm.plandocument.deleted');
                    }

                    return $this->redirectToRoute('DemosPlan_element_administration', compact('procedure'));
                } else {
                    $storageResult = $elementHandler->administrationElementEditHandler($procedure, $inData);
                    // Wenn Storage erfolgreich: Erfolgsmeldung
                    if (array_key_exists('ident', $storageResult) && !array_key_exists('mandatoryfieldwarning', $storageResult)) {
                        $this->getMessageBag()->add('confirm', 'confirm.plandocument.category.saved');
                    }
                }
            }
        }

        $element = $elementService->getElement($elementId);
        $templateVars = ['element' => $element];
        $templateVars['orgasOfProcedure'] = $serviceOutput->getMembersOfProcedure($procedure);

        // speicher die Ids der berechtigten Organisationen für Kategorien in einem array
        $authorisedOrgas = [];
        if (isset($templateVars['element']['organisation'])) {
            foreach ($templateVars['element']['organisation'] as $orga) {
                $authorisedOrgas[] = $orga['ident'];
            }
        }
        $templateVars['authorisedOrgas'] = $authorisedOrgas;
        $templateVars['documents'] = [];
        $templateVars['documentEnable'] = false;

        // wenn elementtyp == file:
        if ('file' === $templateVars['element']['category']) {
            $templateVars['documentEnable'] = true;
            $templateVars['deleteEnable'] = true;
            if (is_array($templateVars['element']['documents']) && 0 < count($templateVars['element']['documents'])) {
                $templateVars['documents'] = $templateVars['element']['documents'];
            }
        }

        // Reichere die breadcrumb mit extraItem an (Planungsdokumente)
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('element.list.admin', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_element_administration', ['procedure' => $procedure]),
            ]
        );

        // Füge die kontextuelle Hilfe dazu
        $templateVars['contextualHelpBreadcrumb'] = $breadcrumb->getContextualHelp('element.admin.category');

        $view = '@DemosPlanCore/DemosPlanDocument/elements_admin_edit.html.twig';
        $renderData = [
            'templateVars' => $templateVars,
            'procedure'    => $procedure,
            'category'     => $templateVars['element']['category'],
            'title'        => $templateVars['element']['title'],
        ];

        if ('paragraph' === $templateVars['element']['category']) {
            // add hook to modify paragraph list
            $event = new AdministrateParagraphElementEvent($request, $procedure, $elementId);
            try {
                $eventDispatcherPost->post($event);
            } catch (Exception $e) {
                $this->logger->warning('Could not successfully handle paragraph element ', [$e]);
            }

            $renderData = $this->generateDataForAdminList(
                $breadcrumb,
                $request,
                $currentProcedureService,
                $fileUploadService,
                $paragraphService,
                $serviceImporter,
                $elementHandler,
                $procedure,
                $templateVars['element']
            );
            // It may occur that generateDataForAdminList renders a fully qualified exception response.
            if (!is_array($renderData) && null !== $renderData) {
                return $renderData;
            }
            $renderData['templateVars'] = array_merge($renderData['templateVars'], $templateVars);

            $view = '@DemosPlanCore/DemosPlanDocument/paragraph_admin_list.html.twig';
        } elseif ('file' === $templateVars['element']['category']) {
            $view = '@DemosPlanCore/DemosPlanDocument/single_document_admin_list.html.twig';
        }

        return $this->renderTemplate($view, $renderData);
    }

    /**
     * Neue Kategorien anlegen.
     *
     * @DplanPermissions({"area_admin_single_document","feature_admin_element_edit"})
     *
     * @param string $procedure
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_elements_administration_new', path: '/verfahren/{procedure}/verwalten/planunterlagen/new')]
    public function elementAdminNewAction(
        Breadcrumb $breadcrumb,
        ElementHandler $elementHandler,
        Request $request,
        ServiceOutput $serviceOutput,
        TranslatorInterface $translator,
        $procedure,
    ) {
        $title = 'element.admin.category.new';
        $inData = $this->prepareIncomingData($request, 'elementnew');
        if (is_array($inData) && 0 < count($inData)) {
            if (array_key_exists('r_title', $inData) && '' === trim((string) $inData['r_title'])) {
                $this->getMessageBag()->add('warning', 'error.mandatoryfields');

                return $this->renderTemplate(
                    '@DemosPlanCore/DemosPlanDocument/elements_admin_edit.html.twig',
                    [
                        'procedure' => $procedure,
                        'title'     => $title,
                    ]
                );
            }

            $storageResult = $elementHandler->administrationElementNewHandler($procedure, $inData);

            // Wenn Storage erfolgreich: zurueck zur Liste
            if (array_key_exists('ident', $storageResult)
                && !array_key_exists('mandatoryfieldwarning', $storageResult)
            ) {
                $this->getMessageBag()->add('confirm', 'confirm.plandocument.category.saved');

                return $this->redirectToRoute('DemosPlan_elements_administration_edit', [
                    'procedure' => $procedure,
                    'elementId' => $storageResult['ident'],
                ]);
            }
        }

        $requestGet = $request->query->all();
        $templateVars = [];
        if (isset($requestGet['parentElement'])) {
            $templateVars['parent'] = $requestGet['parentElement'];
        }

        // Reichere die breadcrumb mit extraItem an (Planungsdokumente)
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('element.list.admin', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_element_administration', ['procedure' => $procedure]),
            ]
        );
        $templateVars['orgasOfProcedure'] = $serviceOutput->getMembersOfProcedure($procedure);

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanDocument/elements_admin_edit.html.twig',
            [
                'procedure'    => $procedure,
                'templateVars' => $templateVars,
                'title'        => $title,
            ]
        );
    }

    /**
     * öffentliche Planunterlagen Kategorie Einzeldokumente Liste.
     *
     * This action is called via render() in public detail
     *
     * @param string $procedure
     * @param string $title
     *
     * @throws Exception
     *
     * @DplanPermissions("area_public_participation")
     */
    public function publicDocumentListAction(
        CurrentProcedureService $currentProcedureService,
        CurrentUserInterface $currentUser,
        ElementsService $elementsService,
        Request $request,
        $procedure,
        $title,
    ): Response {
        $elements = $elementsService->getEnabledFileAndParagraphElements(
            $procedure,
            $currentUser->getUser()->getOrganisationId(),
            $this->permissions->ownsProcedure()
        );

        $templateVars = [
            'list'      => [
                'elementlist' => $elements,
            ],
            'procedure' => $currentProcedureService->getProcedureArray(),
        ];

        // edit Statement?
        if ($request->get('draftStatementId')) {
            $templateVars['draftStatementId'] = $request->get('draftStatementId');
        }

        return $this->renderTemplate('@DemosPlanCore/DemosPlanDocument/public_elements_list.html.twig', [
            'procedure'    => $procedure,
            'templateVars' => $templateVars,
            'title'        => $title,
        ]);
    }

    /**
     * Anzeige der Begründung/Verordnung in der Beteiligungsebene.
     *
     * @param string $procedure
     * @param string $elementId
     * @param string $category
     *
     * @return RedirectResponse|Response
     *
     * @DplanPermissions("area_public_participation")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_public_plandocument_paragraph', path: '/verfahren/{procedure}/public/paragraph/{elementId}', defaults: ['category' => 'paragraph', 'type' => 'all'], options: ['expose' => true])]
    public function publicParagraphListAction(
        BrandingService $brandingService,
        CountyService $countyService,
        CurrentProcedureService $currentProcedureService,
        DocumentHandler $documentHandler,
        EditorService $editorService,
        ElementsService $elementsService,
        Request $request,
        $procedure,
        $elementId,
        $category,
    ) {
        // @improve T14613
        $procedureId = $procedure;
        unset($procedure);

        $elementService = $this->elementsService;
        $documentList = [];

        try {
            $documentList = $documentHandler->getPublicParaDocuments($procedureId, $elementId);
        } catch (RuntimeException $e) {
            if ('Access to this document is forbidden.' === $e->getMessage()) {
                $templateVars = [];

                if ($this->permissions instanceof Permissions
                    && $this->permissions->hasPermission('area_combined_participation_area')
                ) {
                    $templateVars['procedureLayer'] = 'participation';
                }

                return $this->renderTemplate('@DemosPlanCore/DemosPlanDocument/public_paragaph_not_allowed.html.twig', [
                    'procedure'    => $procedureId,
                    'templateVars' => $templateVars,
                    'title'        => 'element.paragraph',
                    'category'     => $category,
                ]);
            }
        }

        $templateVars = [
            'list'      => [
                'documentlist' => $documentList,
            ],
            'procedure' => $currentProcedureService->getProcedureArray(),
            'elementId' => $elementId,
        ];

        $templateVars = $this->paginateParagraphList($request, $templateVars);

        $templateVars['list']['documentlist'] = $this->replaceDocumentImagePlaceholdersImg(
            $editorService,
            $templateVars['list']['documentlist']
        );

        // get Element -> get Title
        $element = $elementService->getElement($elementId);

        $templateVars['element'] = $element;

        //  get form options for statement form
        $templateVars['formOptions']['userGroup'] = $this->getFormParameter('statement_user_group');
        $templateVars['formOptions']['userPosition'] = $this->getFormParameter('statement_user_position');
        $templateVars['formOptions']['userState'] = $this->getFormParameter('statement_user_state');

        // edit existing draftStatement?
        if ($request->get('draftStatementId')) {
            $templateVars['draftStatementId'] = $request->get('draftStatementId');
        }

        // Display as participationLayer
        if ($this->permissions instanceof Permissions
            && $this->permissions->hasPermission('area_combined_participation_area')
        ) {
            $templateVars['procedureLayer'] = 'participation';
        }

        if ($this->permissions->hasPermission('field_statement_location')) {
            // @improve T14122
            $templateVars['counties'] = $countyService->getCounties();
        }

        // orga Branding
        if ($this->permissions->hasPermission('area_orga_display')) {
            $orgaBranding = $brandingService->createOrgaBrandingFromProcedureId($procedureId);
            $templateVars['orgaBranding'] = $orgaBranding;
        }

        // is the negative statement plannindocument category enabled?
        $templateVars['planningDocumentsHasNegativeStatement'] =
            $elementsService->hasNegativeReportElement($procedureId);

        $templateVars['procedure'] = $currentProcedureService->getProcedure();

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanDocument/public_paragraph_document.html.twig',
            [
                'procedure'    => $procedureId,
                'templateVars' => $templateVars,
                'title'        => $element['title'],
                'category'     => $category,
            ]
        );
    }

    /**
     * Verarbeitung der eingehenden Parameter aus den Formularposts.
     *
     * @param string $action
     */
    private function prepareIncomingData(Request $request, $action): array
    {
        $result = [];

        $incomingFields = [
            'documentnew'                               => [
                'r_action',
                'r_title',
                'r_text',
                'r_visible',
                'r_elementId',
                'r_parentId',
            ],
            'documentdelete'                            => [
                'r_action',
                'document_delete',
            ],
            'documentedit'                              => [
                'action',
                'r_ident',
                'r_title',
                'r_text',
                'r_visible',
                'r_lockReason',
                'r_parentId',
            ],
            'elementedit'                               => [
                'r_action',
                'r_autoSwitchState',
                'r_designatedSwitchDate',
                'r_ident',
                'r_text',
                'r_title',
                'r_orga',
                'r_permission',
            ],
            'elementnew'                                => [
                'r_text',
                'r_autoSwitchState',
                'r_designatedSwitchDate',
                'r_title',
                'r_category',
                'r_parent',
                'r_orga',
            ],
            DocumentHandler::ACTION_SINGLE_DOCUMENT_NEW => [
                'action',
                'r_title',
                'r_text',
                'r_statement_enabled',
                'r_visible',
            ],
            'singledocumentedit'                        => [
                'r_action',
                'r_title',
                'r_text',
                'r_statement_enabled',
                'r_visible',
            ],
            'singledocumentdelete'                      => [
                'r_action',
                'document_delete',
                'r_sorting',
            ],
            'onoffswitch'                               => [
                'r_action',
                'r_onoffswitch',
            ],
            'updateParagraphPDF'                        => [
                'r_action',
                'r_planDelete',
            ],
        ];

        $request = $request->request->all();

        foreach ($incomingFields[$action] as $key) {
            if (array_key_exists($key, $request)) {
                $result[$key] = $request[$key];
            }
        }

        return $result;
    }

    public function getElementImportDir(string $procedureId, UserInterface $user): string
    {
        // import dir is used as real local file path but as well as path "name" in flysystem
        $tmpDir = DemosPlanPath::getTemporaryPath($user->getId().'/'.$procedureId);
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $tmpDir));
        }

        return $tmpDir;
    }

    /**
     * Calculate Image size from file.
     *
     * @return array [$width, $height]
     */
    protected function calculateImgSize(string $hash)
    {
        try {
            $fileInfo = $this->fileService->getFileInfo($hash);
            if ($this->defaultStorage->fileExists($fileInfo->getAbsolutePath())) {
                $sizeArray = getimagesizefromstring($this->defaultStorage->read($fileInfo->getAbsolutePath()));

                return [$sizeArray[0], $sizeArray[1]];
            }
        } catch (Exception) {
            // return default value
        }

        return [0, 0];
    }

    /**
     * Stelle importierte Bilder in den Kapiteln dar
     * Ersetze das im Texte gespeicherte Pattern <!-- #Image-[filehash] -->
     * durch einen Imagetag.
     *
     * @param array $documentList
     *
     * @return array
     */
    protected function replaceDocumentImagePlaceholdersImg(EditorService $editorService, $documentList)
    {
        // Stelle importierte Bilder in den Kapiteln dar
        $this->profilerStart('ImageReplacement');
        if (0 < count($documentList)) {
            // Ersetze das im Texte gespeicherte Pattern <!-- #Image-[filehash] -->
            // durch einen Imagetag
            $imageRegex = '|[.*]?'.$editorService::IMAGE_ID_OPENING_TAG.'([a-z0-9&=\-].*?) '.
                $editorService::IMAGE_ID_CLOSING_TAG.'[.*]?|';
            $imagePath = $this->generateUrl('core_logo', ['hash' => 'replaceme']);
            $imageHtml = '<img src="'.$imagePath.'" SIZE ALTTEXT/>';
            // gehe alle Kapitel durch
            foreach ($documentList as $docKey => $document) {
                // und suche nach einem Bild
                preg_match_all(
                    $imageRegex,
                    (string) $document['text'],
                    $matches,
                    PREG_PATTERN_ORDER
                );
                // Wenn du kein Bild gefunden hast, durchsuche den nächsten Absatz
                if (0 === count($matches[1])) {
                    continue;
                }
                // Wenn du ein oder mehrere Bilder gefunden hast gehe sie durch
                foreach ($matches[1] as $matchKey => $match) {
                    $sizeCommand = '';
                    $parts = explode('&', $match);
                    $fileHash = $parts[0];
                    // Bestimme die Größe des Bildes
                    if (isset($parts[1]) && isset($parts[2])) {
                        $widthParts = explode('=', $parts[1]);
                        $width = $widthParts[1] ?? null;
                        $heightParts = explode('=', $parts[2]);
                        $height = isset($heightParts[1]) ? str_replace('\\\\', '', $heightParts[1]) : null;
                        // try to get real image size on the fly if something
                        // happened during import
                        if ('0' === $height || '0' === $width) {
                            [$width, $height] = $this->calculateImgSize($fileHash);
                        }
                        $sizeCommand = ' width="'.$width.'" height="'.$height.'" ';
                    }
                    // und ersetze den Platzhalter durch das Imagetag mit dem korrkten Hash
                    $currentImageHtml = str_replace('replaceme', $fileHash, $imageHtml);
                    $documentList[$docKey]['text'] = preg_replace(
                        '|'.$matches[0][$matchKey].'|',
                        $currentImageHtml,
                        (string) $documentList[$docKey]['text']
                    );

                    // second try to find by str_replace
                    // should only find & replace in case of preg_replace() does not already found & replace
                    $documentList[$docKey]['text'] = str_replace($matches[0][$matchKey], $currentImageHtml, $documentList[$docKey]['text']);

                    // setze die Größe
                    $documentList[$docKey]['text'] = preg_replace(
                        '|SIZE|',
                        $sizeCommand,
                        $documentList[$docKey]['text']
                    );

                    if (isset($parts[3])) {
                        // check if this part is really the alttext!?
                        // setze den alternative Text
                        $documentList[$docKey]['text'] = preg_replace(
                            '|ALTTEXT|',
                            $parts[3],
                            $documentList[$docKey]['text']
                        );
                    }
                }
            }
        }
        $this->profilerStop('ImageReplacement');

        return $documentList;
    }

    /**
     * Method to get from Request the ids and paths for the documents to be zipped.
     *
     * @throws ReflectionException
     */
    private function getFilesRequestInfo(Request $request): array
    {
        $singleDocumentService = $this->singleDocumentService;
        $docIds = [];
        foreach ($request->request->all() as $key => $id) {
            $keyArray = explode(':', (string) $key);
            $type = $keyArray[0];
            if ('documentSelected' === $type) {
                $singleDocument = $singleDocumentService->getSingleDocument($id, false);
                $elementsPath = $this->getElementIdsPath($singleDocument->getElementId());
                $docIds[] = [
                    'id'   => $id,
                    'path' => $elementsPath,
                ];
            }
        }

        return $docIds;
    }

    /**
     * Given an Elements id, returns an array with its ascendants' ids, sortiert nach Ebene.
     * Erste array Elements ist root Elements' id, zweite its child's id, dritte its grandchild's id...
     *
     * @throws Exception
     */
    private function getElementIdsPath(?string $elementsId): array
    {
        $elementIdsPath = [];
        if (null !== $elementsId) {
            $elementHandler = $this->elementHandler;
            $elementIdsPath[] = $elementsId;
            $elements = $elementHandler->getElement($elementsId);
            $elementIdsPath = array_merge($elementIdsPath, $this->getElementIdsPath($elements->getElementParentId()));
        }

        return $elementIdsPath;
    }

    /**
     * @param string $procedureId
     *
     * @throws ReflectionException
     */
    private function getAllProcedureFilesInfo($procedureId): array
    {
        $singleDocumentService = $this->singleDocumentService;
        $procedureSingleDocs = $singleDocumentService->getSingleDocumentList($procedureId, null, false);

        return array_map(
            function (SingleDocument $singleDocument) {
                $elementsPath = $this->getElementIdsPath($singleDocument->getElementId());

                return [
                    'id'   => $singleDocument->getId(),
                    'path' => $elementsPath,
                ];
            },
            $procedureSingleDocs
        );
    }

    /**
     * Prepares the necessary info structure to zip and download a set of files.
     *
     * @throws ReflectionException
     * @throws InvalidDataException
     * @throws JsonException
     * @throws Exception
     */
    private function getFilesInfo(Request $request, string $procedureId): array
    {
        // @improve T14122
        $fileService = $this->fileService;
        $elementHandler = $this->elementHandler;
        $filesRequestInfo = $this->getFilesRequestInfo($request);
        $filesToZip = empty($filesRequestInfo)
                        ? $this->getAllProcedureFilesInfo($procedureId)
                        : $filesRequestInfo;
        $filesToZip = $this->validatefilesToZip($filesToZip, $procedureId);
        $fileInfo = [];
        foreach ($filesToZip as $fileRequestInfo) {
            $singleDocId = $fileRequestInfo['id'];
            $fileId = $fileService->getFileIdFromSingleDocumentId($singleDocId);
            $fileEntity = $fileService->getFileById($fileId);
            if (null === $fileEntity) {
                $this->logger->error("No File Entity found for id: $fileId");
                throw new \InvalidArgumentException('error.generic');
            }
            $fileName = $fileEntity->getFilename();
            $fileFullPath = $fileEntity->getFilePathWithHash();
            if (!$this->defaultStorage->fileExists($fileFullPath)) {
                $this->getLogger()->warning('Could not find file to add to zip', [$fileEntity->getId()]);
                continue;
            }
            // $fileName might be an empty string. If for some reasons it is empty, better use a random string than fail
            $fileName = '' === $fileName ? random_bytes(10) : $fileName;
            $fileNamedPath = $elementHandler->getFileNamedPath($fileRequestInfo['path'], $fileName);
            $fileInfo[$singleDocId] = [
                'fullPath'  => $fileFullPath,
                'namedPath' => $fileNamedPath,
            ];
        }

        return $fileInfo;
    }

    /**
     * Validates that all files to be zipped:
     *      - Belong to the procedure
     *      - Belong to enabled Elements
     * Otherwise an Exception will be thrown.
     *
     * @throws JsonException
     */
    private function validatefilesToZip(array $filesInfo, string $procedureId): array
    {
        // Validate that all files to be zipped belong to the procedure
        $singleDocumentService = $this->singleDocumentService;
        $filesToZipIds = array_map(
            static fn ($fileInfo) => $fileInfo['id'],
            $filesInfo
        );
        $otherProcedureFileIds = $singleDocumentService->getSingleDocumentsNotInProcedure($filesToZipIds, $procedureId);
        if (!empty($otherProcedureFileIds)) {
            $this->logger->error('SingleDocuments '.Json::encode(array_values($otherProcedureFileIds)).' are not in procedure '.$procedureId.' and can\'t be downloaded');
            throw new \InvalidArgumentException('files.download.error.try_later');
        }

        // Validate that none of the files to be zipped are set as not visible
        $invisibleFileIds = $singleDocumentService->getNotVisibleSingleDocuments($filesToZipIds, $procedureId);
        if (!empty($invisibleFileIds)) {
            $this->logger->error('SingleDocuments '.Json::encode(array_values($invisibleFileIds)).' are disabled and can\'t be downloaded.');
            throw new \InvalidArgumentException('files.download.error.try_later');
        }

        // Validate that all files to be zipped belong to enabled Elements
        $elementsHandler = $this->elementHandler;
        $enabledElementIds = $elementsHandler->getElementIdsByEnabledStatus($procedureId, true);
        $elementsToZip = array_map(
            static fn ($fileInfo) => $fileInfo['path'],
            $filesInfo
        );
        $elementsToZip = array_unique(array_merge([], ...$elementsToZip));
        $disabledElementsToZip = array_diff($elementsToZip, $enabledElementIds);
        if (!empty($disabledElementsToZip)) {
            // remove files from zip that have disabled element (parents)
            $filesInfo = collect($filesInfo)->reject(static function ($file) use ($disabledElementsToZip) {
                $disabledElementsInPath = array_intersect($file['path'], $disabledElementsToZip);

                return 0 < count($disabledElementsInPath);
            })->toArray();
        }

        return $filesInfo;
    }

    /**
     * Receives an array of File entity ids, zips the correspondent files and starts the download of the zip file.
     *
     * @DplanPermissions("feature_element_export")
     *
     * @return RedirectResponse|StreamedResponse
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_document_zip_files', path: '/verfahren/{procedureId}/planunterlagen/zipfiles', options: ['expose' => true])]
    public function zipFilesAction(Request $request, TranslatorInterface $translator, string $procedureId)
    {
        try {
            $filesInfo = $this->getFilesInfo($request, $procedureId);

            return new StreamedResponse(function () use ($filesInfo, $translator) {
                $zip = new ZipStream(
                    sendHttpHeaders: true,
                    outputName: $translator->trans('plandocument.zip.file.name'),
                    contentDisposition: 'attachment',
                    contentType: 'application/zip'
                );
                foreach ($filesInfo as $fileInfo) {
                    try {
                        $streamRead = $this->defaultStorage->readStream($fileInfo['fullPath']);
                        $zip->addFileFromStream(Utf8::toAscii($fileInfo['namedPath']), $streamRead);
                    } catch (Exception $e) {
                        $this->getLogger()->error($e->getMessage(), $e->getTrace());
                    }
                }

                $zip->finish();
            });
        } catch (Exception $e) {
            $message = 'error.generic';
            if ($e instanceof \InvalidArgumentException) {
                $message = $e->getMessage();
            }
            $this->getMessageBag()->add('error', $message);

            return $this->redirectToRoute('DemosPlan_news_news_public', ['procedure' => $procedureId]);
        }
    }
}
