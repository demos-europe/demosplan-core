<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Map;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Exception\MapValidationException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementHandler;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapHandler;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\Map\ServiceStorage;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\MasterTemplateService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ServiceStorage as ProcedureServiceStorage;
use demosplan\DemosPlanCoreBundle\Services\Breadcrumb\Breadcrumb;
use demosplan\DemosPlanCoreBundle\Services\Map\GetFeatureInfo;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Exception;
use InvalidArgumentException;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Kartenbereich und -funktionen.
 */
class DemosPlanMapController extends BaseController
{
    /**
     * //improve T12925
     * Karte zum Verwalten der Karteneigenschaften wie BoundingBox & Startkartenausschnitt.
     *
     * @DplanPermissions("area_admin_map")
     *
     * @param string $procedureId
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_map_administration_map', path: '/verfahren/{procedureId}/verwalten/globaleGisEinstellungen', options: ['expose' => true])]
    public function mapAdminAction(
        Breadcrumb $breadcrumb,
        TranslatorInterface $translator,
        ProcedureService $procedureService,
        $procedureId,
    ) {
        // reichere die breadcrumb mit extraItem an
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('drawing.admin.gis.layers', [], 'page-title'),
                'url'   => $this->generateUrl(
                    'DemosPlan_map_administration_gislayer',
                    ['procedureId' => $procedureId]
                ),
            ]
        );

        $procedure = $procedureService->getProcedure($procedureId);

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanMap/map_admin.html.twig',
            ['procedure' => $procedureId, 'isMaster' => $procedure?->getMaster(), 'title' => 'drawing.admin.adjustments.gis']
        );
    }

    /**
     * Formularanzeige neuer Gislayer.
     *
     * Warning: This action needs to be situated in front of DemosPlan_map_administration_gislayer_edit
     * otherwise "/neu" would be interpreted as "/{gislayerID}"
     *
     * @DplanPermissions("area_admin_map")
     *
     * @param string $procedure
     *
     * @return Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_map_administration_gislayer_new', path: '/verfahren/{procedure}/verwalten/gislayer/neu', options: ['expose' => true])]
    public function mapAdminGislayerNewAction(
        Breadcrumb $breadcrumb,
        FileUploadService $fileUploadService,
        Request $request,
        ServiceStorage $serviceStorage,
        TranslatorInterface $translator,
        $procedure,
    ) {
        $templateVars = [];
        try {
            $templateVars['procedure'] = $procedure;
            $requestPost = $request->request;
            $templateVars['inData'] = [];

            if ($requestPost->has('saveLayer')) {
                $inData = $this->prepareIncomingData($request, 'gislayernew');
                $inData['r_legend'] = $fileUploadService->prepareFilesUpload($request, 'r_legend');
                // Storage Formulardaten übergeben
                $storageResult = $serviceStorage->administrationGislayerNewHandler($procedure, $inData);
                $templateVars['inData'] = $inData;

                // Erfolgreich gespeichert
                if (false != $storageResult
                    && array_key_exists('ident', $storageResult)
                    && !array_key_exists('mandatoryfieldwarning', $storageResult)
                ) {
                    // Erfolgsmeldung
                    $this->getMessageBag()->add('confirm', 'confirm.saved');

                    return $this->redirectToRoute(
                        'DemosPlan_map_administration_gislayer',
                        [
                            'procedureId' => $procedure,
                        ]
                    );
                }
            }
            // reichere die breadcrumb mit extraItem an
            $breadcrumb->addItem(
                [
                    'title' => $translator->trans('drawing.admin.gis.layers', [], 'page-title'),
                    'url'   => $this->generateUrl('DemosPlan_map_administration_gislayer', ['procedureId' => $procedure]),
                ]
            );

            $title = 'drawing.admin.gis.layer.new';
            $templateVars['contextualHelpBreadcrumb'] = $breadcrumb->getContextualHelp($title);

            $templateVars['availableProjections'] = $this->globalConfig->getMapAvailableProjections();

            return $this->renderTemplate(
                '@DemosPlanCore/DemosPlanMap/map_admin_gislayer_new.html.twig',
                [
                    'templateVars' => $templateVars,
                    'procedure'    => $procedure,
                    'title'        => $title,
                ]
            );
        } catch (MapValidationException) {
            $this->getMessageBag()->add('error', 'error.save');

            return $this->redirect($request->headers->get('referer'));
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Anzeige des Layer-Editformulars.
     *
     * @DplanPermissions("area_admin_map")
     *
     * @param string $procedure
     * @param string $gislayerID
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_map_administration_gislayer_edit', path: '/verfahren/{procedure}/verwalten/gislayer/{gislayerID}', options: ['expose' => true])]
    public function mapAdminGislayerEditAction(
        Breadcrumb $breadcrumb,
        FileUploadService $fileUploadService,
        MapService $mapService,
        ProcedureService $procedureService,
        Request $request,
        ServiceStorage $serviceStorage,
        TranslatorInterface $translator,
        $procedure,
        $gislayerID,
    ) {
        try {
            // Storage und Output initialisieren
            $requestPost = $request->request;

            if ($requestPost->has('saveLayer')) {
                $inData = $this->prepareIncomingData($request, 'gislayeredit');
                $inData['r_legend'] = $fileUploadService->prepareFilesUpload($request, 'r_legend');
                // Storage Formulardaten übergeben
                $storageResult = $serviceStorage->administrationGislayerEditHandler($procedure, $inData);

                // Erfolgreich gespeichert
                if (false != $storageResult
                    && array_key_exists('ident', $storageResult)
                    && !array_key_exists('mandatoryfieldwarning', $storageResult)
                ) {
                    // Erfolgsmeldung
                    $this->getMessageBag()->add('confirm', 'confirm.saved');

                    return $this->redirectToRoute(
                        'DemosPlan_map_administration_gislayer',
                        ['procedureId' => $procedure]
                    );
                }
            }

            // reichere die breadcrumb mit extraItem an
            $breadcrumb->addItem(
                [
                    'title' => $translator->trans('drawing.admin.gis.layers', [], 'page-title'),
                    'url'   => $this->generateUrl(
                        'DemosPlan_map_administration_gislayer',
                        ['procedureId' => $procedure]
                    ),
                ]
            );

            // Template Variable aus Storage Ergebnis erstellen(Output)
            $gisLayer = $mapService->getGisLayer($gislayerID);

            $templateVars = ['gislayer' => $gisLayer];

            $templateVars['availableProjections'] = $this->globalConfig->getMapAvailableProjections();

            return $this->renderTemplate(
                '@DemosPlanCore/DemosPlanMap/map_admin_gislayer_edit.html.twig',
                [
                    'templateVars' => $templateVars,
                    'procedure'    => $procedure,
                    'title'        => 'drawing.admin.gis.layer.edit',
                ]
            );
        } catch (MapValidationException) {
            $this->getMessageBag()->add('error', 'error.save');

            return $this->redirect($request->headers->get('referer'));
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Anzeige des LayerCategory-Newformulars.
     *
     *  @DplanPermissions({"area_admin_map","feature_map_category"})
     *
     * @param string $procedureId
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_map_administration_gislayer_category_new', path: '/verfahren/{procedureId}/verwalten/gislayergroup/new-category', options: ['expose' => true])]
    public function mapAdminGislayerCategoryNewAction(MapHandler $mapHandler, Request $request, $procedureId)
    {
        $request = $request->request->all();
        $categoriesOfProcedure = $mapHandler->getRootLayerCategoryForProcedure($procedureId);
        $relatedCategoryId = $categoriesOfProcedure->getId();
        // create new if necessary data is given:
        if (array_key_exists('r_layerCategoryName', $request)) {
            $childrenHidden = array_key_exists('r_layerWithChildrenHidden', $request) ? true : false;
            $name = $request['r_layerCategoryName'];
            // set custom category if given, else use rootCategory
            if (array_key_exists('r_layerCategoryCategory', $request)) {
                $relatedCategoryId = $request['r_layerCategoryCategory'];
            }

            try {
                $resultCategory = $mapHandler->addGisLayerCategory([
                    'name'                    => $name,
                    'parentId'                => $relatedCategoryId,
                    'procedureId'             => $procedureId,
                    'layerWithChildrenHidden' => $childrenHidden,
                ]);

                if ($resultCategory instanceof GisLayerCategory) {
                    $this->getMessageBag()->add('confirm', 'confirm.saved');

                    return $this->redirectToRoute(
                        'DemosPlan_map_administration_gislayer',
                        ['procedureId' => $procedureId]
                    );
                }
            } catch (InvalidArgumentException $exception) {
                $this->logger->warning('Exception on GisLayerCategory adding.', ['exception' => $exception]);
                $this->messageBag->add('warning', 'error.name');
            }
        }

        $categoriesOfProcedure = $mapHandler->getRootLayerCategoryForProcedure($procedureId);
        $templateVars = [
            'gislayerCategory'      => [
                'layerWithChildrenHidden' => false,
            ],
            'categoriesOfProcedure' => $categoriesOfProcedure,
        ];

        // show empty formular if no data is given:
        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanMap/map_admin_gislayer_category_edit.html.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedureId,
                'title'        => 'drawing.admin.gis.layer.group.new',
            ]
        );
    }

    /**
     * Anzeige des Layer-Kagetorie-Editformulars.
     *
     * @DplanPermissions({"area_admin_map","feature_map_category"})
     *
     * @param string $procedureId
     * @param string $gislayerCategoryId
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_map_administration_gislayer_category_edit', path: '/verfahren/{procedureId}/verwalten/gislayergroup/{gislayerCategoryId}/edit', options: ['expose' => true])]
    public function mapAdminGisLayerCategoryEditAction(MapHandler $mapHandler, Request $request, $procedureId, $gislayerCategoryId)
    {
        try {
            $currentCategory = $mapHandler->getGisLayerCategory($gislayerCategoryId);
        } catch (Exception) {
            $this->getMessageBag()->add('error', 'error.gislayerCategory.get');

            return $this->redirectToRoute(
                'DemosPlan_map_administration_gislayer',
                ['procedureId' => $procedureId]
            );
        }

        $inData = $this->prepareIncomingData($request, 'gislayerCategorynew');
        if (array_key_exists('r_layerCategoryName', $inData)) {
            try {
                $childrenHidden = array_key_exists('r_layerWithChildrenHidden', $inData);
                $name = $inData['r_layerCategoryName'];
                $mapHandler->updateGisLayerCategory(
                    $gislayerCategoryId,
                    ['name' => $name, 'layerWithChildrenHidden' => $childrenHidden]
                );

                return $this->redirectToRoute(
                    'DemosPlan_map_administration_gislayer',
                    ['procedureId' => $procedureId]
                );
            } catch (Exception) {
                $this->getMessageBag()->add('error', 'error.gislayerCategory.update');
            }
        }

        $templateVars = [
            'gislayerCategory' => [
                'name'                    => $currentCategory->getName(),
                'layerWithChildrenHidden' => $currentCategory->isLayerWithChildrenHidden(),
                'id'                      => $gislayerCategoryId,
            ],
        ];

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanMap/map_admin_gislayer_category_edit.html.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedureId,
                'title'        => 'drawing.admin.gis.layer.group.edit',
            ]
        );
    }

    /**
     * //improve T12925
     * Planzeichnung verwalten.
     *
     * @DplanPermissions("area_admin_map")
     *
     * @param string $procedureId
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_map_administration_gislayer', path: '/verfahren/{procedureId}/verwalten/gislayer', options: ['expose' => true])]
    public function mapAdminGislayerAction(
        Breadcrumb $breadcrumb,
        CurrentProcedureService $currentProcedureService,
        ElementHandler $elementHandler,
        FileUploadService $fileUploadService,
        MapHandler $mapHandler,
        MapService $mapService,
        ProcedureService $procedureService,
        ProcedureServiceStorage $procedureServiceStorage,
        Request $request,
        $procedureId,
    ) {
        $mapOfProcedure = $elementHandler->mapHandler($procedureId);
        $requestPost = $request->request->all();
        $procedure = $currentProcedureService->getProcedureArray();

        if (array_key_exists('manualsort', $requestPost)) {
            $manualSort = $requestPost['manualsort'];
            if ('' !== $manualSort && 'delete' !== $manualSort) {
                $layerIds = explode(', ', $requestPost['manualsort']);
                $mapService->reOrder($layerIds);
            }
        }

        if (array_key_exists('action', $requestPost) && 'updatePlan' === $requestPost['action']) {
            if ($request->request->has('reset_map_hint')) {
                $procedureService->resetMapHint($procedureId);

                return $this->redirectToRoute(
                    'DemosPlan_map_administration_gislayer',
                    ['procedureId' => $procedureId]
                );
            }
            // updatePlanstand
            $inData = $this->prepareIncomingData($request, 'planstand');
            $procedure = $procedureServiceStorage->updatePlanHandler($inData, $procedureId);

            if (false === $procedure) {
                return $this->redirectToRoute(
                    'DemosPlan_map_administration_gislayer',
                    ['procedureId' => $procedureId]
                );
            }

            // upload Planzeichnung
            if ((array_key_exists('uploadedFiles', $requestPost)
                    && '' !== $requestPost['uploadedFiles']['r_planDrawPDF']) || array_key_exists('r_planDrawDelete', $requestPost)) {
                $inData = $this->prepareIncomingData($request, 'plandraw');
                if (array_key_exists('r_planDrawDelete', $requestPost)) {
                    $inData['r_planDrawPDF'] = '';
                } elseif ($fileUploadService->hasUploadedFiles($request, 'r_planDrawPDF')) {
                    $inData['r_planDrawPDF'] = $fileUploadService->prepareFilesUpload($request, 'r_planDrawPDF');
                }
                $procedure = $procedureServiceStorage->updatePlanDrawHandler($inData, $procedureId);
                if (false != $procedure && array_key_exists('ident', $procedure) && !array_key_exists('mandatoryfieldwarning', $procedure)) {
                    // Erfolgsmeldung
                    $this->getMessageBag()->add('confirm', 'confirm.drawing.pdf.edited');
                }
            }

            // upload Planzeichenerklärung
            if ((array_key_exists('uploadedFiles', $requestPost) && '' !== $requestPost['uploadedFiles']['r_planPDF']) || array_key_exists('r_planDelete', $requestPost)) {
                $inData = $this->prepareIncomingData($request, 'planstand');
                if (array_key_exists('r_planDelete', $requestPost)) {
                    $inData['r_planPDF'] = '';
                } elseif ($fileUploadService->hasUploadedFiles($request, 'r_planPDF')) {
                    $inData['r_planPDF'] = $fileUploadService->prepareFilesUpload($request, 'r_planPDF');
                }
                $procedure = $procedureServiceStorage->updatePlanHandler($inData, $procedureId);
                if (false !== $procedure && array_key_exists('ident', $procedure) && !array_key_exists('mandatoryfieldwarning', $procedure)) {
                    // Erfolgsmeldung
                    $this->getMessageBag()->add('confirm', 'confirm.drawing.explanation.pdf.edited');
                }
            }

            if ($request->request->has('submit_item_return_button')) {
                return new RedirectResponse($this->generateUrl('DemosPlan_element_administration', ['procedure' => $procedureId]));
            }

            return $this->redirectToRoute(
                'DemosPlan_map_administration_gislayer',
                ['procedureId' => $procedureId]
            );
        }

        if (!empty($requestPost['gislayerdelete']) && !empty($requestPost['gislayerID'])) {
            $inData = $this->prepareIncomingData($request, 'gislayerdelete');
            // Storage Formulardaten übergeben
            $deleteResult = $mapHandler->deleteGisLayer($inData['gislayerID']);
            // Erfolgreich gelöscht
            if ($deleteResult) {
                // Erfolgsmeldung
                $this->getMessageBag()->add('confirm', 'confirm.entries.marked.deleted');
            }
        }

        $templateVars = [];

        $title = 'drawing.admin.gis.layers';
        $templateVars['procedure'] = $procedure;
        $templateVars['procedure']['map'] = $mapOfProcedure;
        $templateVars['contextualHelp Breadcrumb'] = $breadcrumb->getContextualHelp($title);

        return $this->renderTemplate('@DemosPlanCore/DemosPlanMap/map_admin_gislayer_list.html.twig', [
            'templateVars' => $templateVars,
            'procedure'    => $procedureId,
            'title'        => $title,
        ]
        );
    }

    /**
     * Globale GIS-Layer vwewalten.
     *
     * @DplanPermissions("area_admin_gislayer_global_edit")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_map_administration_gislayer_global', path: '/gislayer')]
    public function mapAdminGislayerGlobalAction(
        GetFeatureInfo $getFeatureInfo,
        Request $request,
        ServiceStorage $serviceStorage,
        MapService $mapService,
        MapHandler $mapHandler,
    ) {
        $requestPost = $request->request;
        if ($requestPost->has('gislayerdelete')) {
            $inData = $this->prepareIncomingData($request, 'gislayerdelete');
            // Storage Formulardaten übergeben
            $storageResult = $mapHandler->deleteGisLayer($inData['gislayerID']);
            // Erfolgreich gelöscht
            if ($storageResult) {
                // Erfolgsmeldung
                $this->getMessageBag()->add('confirm', 'confirm.entries.marked.deleted');
            }
        }
        // speichere ggf. die Sachdatenurl
        if ($requestPost->has('saveFeatureInfoUrl')) {
            $inData = $this->prepareIncomingData($request, 'globalFeatureInfoUrl');
            try {
                $serviceStorage->saveGlobalFeatureInfo($inData);

                $this->getMessageBag()->add('confirm', 'confirm.featureinfourl.global.updated');

                return $this->redirectToRoute('DemosPlan_map_administration_gislayer_global');
            } catch (HttpException) {
                // Fehlermeldung
                $this->getMessageBag()->add('error', 'error.featureinfourl.global.updated');
            }
        }

        // Template Variable aus Storage Ergebnis erstellen(Output)
        $globalLayers = $mapService->getGisGlobalList();
        $templateVars = [
            'list' => [
                'gislayerlist' => $mapService->getLayerObjects($globalLayers),
            ],
        ];

        // Füge die Infos zur Sachdatenabfrage hinzu
        $templateVars['featureInfoUrl'] = $getFeatureInfo;

        return $this->renderTemplate('@DemosPlanCore/DemosPlanMap/map_admin_gislayer_global_list.html.twig', [
            'templateVars' => $templateVars,
            'title'        => 'drawing.admin.gis.layers',
        ]);
    }

    /**
     * Globale GIS-Layer bearbeiten.
     *
     * @DplanPermissions("area_admin_gislayer_global_edit")
     *
     * @param string      $type
     * @param string|null $gislayerID
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_map_administration_gislayer_global_new', path: '/gislayer/neu', defaults: ['type' => 'new'])]
    #[Route(name: 'DemosPlan_map_administration_gislayer_global_edit', path: '/gislayer/{gislayerID}', defaults: ['type' => 'edit'])]
    public function mapAdminGislayerGlobalEditAction(
        MapService $mapService,
        Request $request,
        FileUploadService $fileUploadService,
        MasterTemplateService $masterTemplateService,
        ServiceStorage $serviceStorage,
        $type = 'edit',
        $gislayerID = null,
    ) {
        $templateVars = [];
        try {
            $requestPost = $request->request;

            if ($requestPost->has('saveLayer')) {
                $inData = $this->prepareIncomingData($request, 'gislayeredit');
                $inData['r_legend'] = $fileUploadService->prepareFilesUpload($request, 'r_legend');
                // Storage Formulardaten übergeben
                if ('new' === $type) {
                    $storageResult = $serviceStorage->administrationGislayerNewHandler(null, $inData);
                } else {
                    $storageResult = $serviceStorage->administrationGislayerEditHandler(null, $inData);
                    // Die Veränderung der Startkarte muss auch in die Masterblaupause geschrieben werden,
                    // weil diese zur Erstellung von neuen Verfahren herangezogen wird und Änderungen im Flag Startkarte
                    // nicht in die Verfahren übernommen werden, weil Fachplaner diese Einstellung überschreiben dürfen
                    $masterTemplateId = $masterTemplateService->getMasterTemplateId();
                    $adminLayers = $mapService->getGisAdminList($masterTemplateId);
                    $masterBlaupauseGisLayers = $mapService->getLayerObjects($adminLayers);
                    foreach ($masterBlaupauseGisLayers as $masterBlaupauseLayer) {
                        if (array_key_exists('ident', $storageResult)
                            && $storageResult['ident'] == $masterBlaupauseLayer->getGlobalLayerId()
                        ) {
                            // überschreibe die relevanten Werte, damit die Masterblaupause geupdated wird
                            $inDataMaster = $inData;
                            $inDataMaster['r_ident'] = $masterBlaupauseLayer->getIdent();
                            unset($inDataMaster['r_isGlobalLayer']);
                            $serviceStorage->administrationGislayerEditHandler(
                                $masterTemplateId,
                                $inDataMaster
                            );
                            break;
                        }
                    }
                }

                // Erfolgreich gespeichert
                if (false != $storageResult
                    && array_key_exists('ident', $storageResult)
                    && !array_key_exists('mandatoryfieldwarning', $storageResult)
                ) {
                    // Erfolgsmeldung
                    $this->getMessageBag()->add('confirm', 'confirm.saved');

                    return $this->redirectToRoute('DemosPlan_map_administration_gislayer_global');
                }
            }

            // Template Variable aus Storage Ergebnis erstellen(Output)
            $templateVars['gislayer'] = $mapService->gislayerAdminGetGlobalLayer($gislayerID);

            return $this->renderTemplate(
                '@DemosPlanCore/DemosPlanMap/map_admin_gislayer_global_edit.html.twig',
                [
                    'templateVars' => $templateVars,
                    'title'        => 'drawing.admin.gis.layer.global.edit',
                ]
            );
        } catch (MapValidationException) {
            $this->getMessageBag()->add('error', 'error.save');

            return $this->redirect($request->headers->get('referer'));
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Rufe die Sachdateninformationen ab.
     * Via Controller, weil per JavaScript nicht auf andere Domains zugegriffen werden darf.
     *
     * @DplanPermissions("area_map_participation_area")
     *
     * @return Response
     */
    #[Route(name: 'DemosPlan_map_get_feature_info', path: '/getFeatureInfo/{procedure}', options: ['expose' => true])]
    public function getFeatureInfoAjaxAction(GetFeatureInfo $getFeatureInfo, Request $request)
    {
        try {
            // may be initialized without initialize(), as no private information is exposed
            $requestGet = $request->query;

            $getFeatureInfoService = $getFeatureInfo;

            $profilerName = 'getFeatureInfo';
            $this->profilerStart($profilerName);

            // If project has several kind of getFeatureInfo-Requests
            $query = $request->query->all();
            if (array_key_exists('infotype', $query)) {
                $result = $getFeatureInfoService->getFeatureInfoByType($requestGet->all());
            } else {
                $result = $getFeatureInfoService->getFeatureInfo($requestGet->all());
            }
            $this->profilerStop($profilerName);

            // prepare the response
            $response = [
                'code'    => 100,
                'success' => true,
                'body'    => $result,
            ];
            // return result as JSON
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not successfully perform getFeatureinfo: ', [$e]);
            $response = [
                'code'    => 200,
                'success' => false,
            ];
        }

        return new Response(Json::encode($response));
    }

    /**
     * Get procedure by procedureType and clicked coordinate
     * proxy request through controller to avoid cors issues.
     *
     * @DplanPermissions("feature_procedure_planning_area_match")
     *
     * @param string $procedure
     *
     * @return JsonResponse
     */
    #[Route(name: 'DemosPlan_map_get_planning_area', path: '/getPlanningArea/{procedure}', options: ['expose' => true])]
    public function getPlanningAreaAjaxAction(GetFeatureInfo $getFeatureInfo, ProcedureHandler $procedureHandler, Request $request, TranslatorInterface $translator, $procedure)
    {
        try {
            $procedureObject = $procedureHandler->getProcedureWithCertainty($procedure);

            // only need to get planningArea if procedure enforces specific area
            $options = data_get($this->globalConfig->getFormOptions(), 'project_planning_areas');
            $noSpecificAreaVal = '';
            if (is_array($options) && array_key_exists('all', $options)) {
                $noSpecificAreaVal = $translator->trans($options['all']['value']);
            }
            if ($noSpecificAreaVal === $procedureObject->getPlanningArea()) {
                $this->getLogger()->debug('Do not check for specific planning area');

                // what should be the correct return value? tbd when transforming to jsonApi
                return new JsonResponse([
                    'code'    => 100,
                    'success' => true,
                    'body'    => [
                        'id'   => $procedure,
                        'name' => '',
                    ],
                ]);
            }

            $getFeatureInfoService = $getFeatureInfo;
            $profilerName = 'getFeatureInfo';
            $this->profilerStart($profilerName);

            // If project has several kind of getFeatureInfo-Requests
            $query = $request->query->all();
            $query['infotype'] = 'plain';
            $response = $getFeatureInfoService->getFeatureInfoByType($query);

            $this->profilerStop($profilerName);

            $planningArea = 'all';
            if (200 == $response['responseCode'] && false === stripos('<ExceptionReport', (string) $response['body'])) {
                $xml = new SimpleXMLElement($response['body'], null, null, 'http://www.opengis.net/wfs');
                $xml->registerXPathNamespace('wfs', 'http://www.opengis.net/wfs');
                $xml->registerXPathNamespace('gml', 'http://www.opengis.net/gml');
                $xml->registerXPathNamespace('app', 'http://www.deegree.org/app');

                $fieldIsNullArray = $xml->xpath('/wfs:FeatureCollection/gml:boundedBy/gml:null');
                if (is_array($fieldIsNullArray) && 1 === count($fieldIsNullArray)) {
                    $this->getLogger()->debug('Response getFeatureInfo planningArea failed: '.DemosPlanTools::varExport($response, true));
                }

                $fieldKeyArray = $xml->xpath('/wfs:FeatureCollection/gml:featureMember/app:planungsraeume_flaechen/app:plr');
                if (is_array($fieldKeyArray) && 1 === count($fieldKeyArray)) {
                    $planningArea = (string) $fieldKeyArray[0];
                }
            } else {
                $this->getLogger()->warning('Response getFeatureInfo planningArea failed: '.DemosPlanTools::varExport($response, true));
            }

            // as long as correct check for procedure versions is not implemented
            // it should be enough to check whether planningAreas equals
            if ($planningArea == $procedureObject->getPlanningArea()) {
                $this->getLogger()->debug('User clicked in allowed area');
                $response = [
                    'code'    => 100,
                    'success' => true,
                    'body'    => [
                        'id'   => $procedure,
                        'name' => '',
                    ],
                ];
            } else {
                $this->getLogger()->debug('User clicked outside an allowed area');
                $response = [
                    'code'    => 100,
                    'success' => true,
                    'body'    => [
                        'id'   => '',
                        'name' => '',
                    ],
                ];
            }
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not successfully perform getFeatureinfo: ', [$e]);
            $response = [
                'code'    => 200,
                'success' => false,
            ];
        }

        return new JsonResponse($response);
    }

    /**
     * @param string $action
     */
    protected function prepareIncomingData(Request $request, $action): array
    {
        $result = [];

        $incomingFields = [
            'gislayernew'          => [
                'action',
                'r_bplan',
                'r_contextualHelpText',
                'r_default_visibility',
                'r_layerProjection',
                'r_layers',
                'r_layerVersion',
                'r_layerTemplate',
                'r_legend',
                'r_name',
                'r_opacity',
                'r_print',
                'r_scope',
                'r_serviceType',
                'r_tileMatrixSet',
                'r_type',
                'r_url',
                'r_user_toggle_visibility',
                'r_enabled',
                'r_xplan',
                'r_xplanDefaultlayers',
                'r_category',
            ],
            'gislayerdelete'       => [
                'action',
                'gislayerID',
            ],
            'plandraw'             => [
                'action',
                'r_planDrawText',
                'r_planDrawDelete',
            ],
            'planstand'            => [
                'action',
                'r_planText',
                'r_planDelete',
                'r_planningArea',
                'r_mapHint',
            ],
            'statementnewpolygon'  => [
                'action',
                'r_polygon',
                'r_ident',
            ],
            'mapglobals'           => [
                'action',
                'r_currentMapExtent',
                'r_mapExtent',
                'r_boundingBox',
                'r_informationUrl',
                'r_defaultLayer',
                'r_startScale',
                'r_scales',
                'r_enable_layer_groups_alternate_visibility',
                'r_copyright',
                'r_coordinate',
                'r_territory',
            ],
            'gislayeredit'         => [
                'action',
                'delete_legend',
                'r_bplan',
                'r_contextualHelpText',
                'r_default_visibility',
                'r_ident',
                'r_isGlobalLayer',
                'r_layerProjection',
                'r_layers',
                'r_layerVersion',
                'r_legend',
                'r_name',
                'r_opacity',
                'r_print',
                'r_scope',
                'r_serviceType',
                'r_tileMatrixSet',
                'r_type',
                'r_url',
                'r_enabled',
                'r_xplan',
                'r_category',
                'r_user_toggle_visibility',
            ],
            'mapterritory'         => [
                'action',
                'r_territory',
                'submit_item_return_button',
            ],
            'globalFeatureInfoUrl' => [
                'r_featureInfoUrl',
                'r_featureInfoUrlProxyEnabled',
            ],
            'gislayerCategorynew'  => [
                'action',
                'r_layerCategoryName',
                'r_layerWithChildrenHidden',
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
}
