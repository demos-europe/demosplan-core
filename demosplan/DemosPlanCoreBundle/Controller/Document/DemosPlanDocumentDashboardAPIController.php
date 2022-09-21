<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Document;

use Carbon\Carbon;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\APIController;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PrefilledResourceTypeProvider;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceObject;
use demosplan\DemosPlanCoreBundle\Logic\Logger\ApiLogger;
use demosplan\DemosPlanCoreBundle\Logic\Message;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Response\APIResponse;
use demosplan\DemosPlanDocumentBundle\Logic\ElementHandler;
use demosplan\DemosPlanDocumentBundle\Logic\ElementsService;
use demosplan\DemosPlanDocumentBundle\Transformers\DocumentDashboardTransformer;
use demosplan\DemosPlanMapBundle\Logic\MapHandler;
use demosplan\DemosPlanMapBundle\Logic\MapService;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use demosplan\DemosPlanProcedureBundle\Repository\ProcedureRepository;
use Exception;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class DemosPlanDocumentDashboardAPIController extends APIController
{
    /**
     * @var ObjectPersisterInterface
     */
    private $objectPersister;

    public function __construct(
        ApiLogger $apiLogger,
        PrefilledResourceTypeProvider $resourceTypeProvider,
        TranslatorInterface $translator,
        ObjectPersisterInterface $objectPersister
    ) {
        parent::__construct($apiLogger, $resourceTypeProvider, $translator);
        $this->objectPersister = $objectPersister;
    }

    /**
     * @Route(path="/api/1.0/documents/{procedureId}/dashboard",
     *        methods={"GET"},
     *        name="dp_api_documents_dashboard_get",
     *        options={"expose": true})
     *
     * @DplanPermissions("area_admin")
     *
     * Manages the display of the dashboard on load.
     */
    public function showDashboardAction(
        ElementHandler $elementHandler,
        ElementsService $elementsService,
        GlobalConfigInterface $globalConfig,
        MapHandler $mapHandler,
        MapService $mapService,
        ProcedureService $procedureService,
        string $procedureId
    ): APIResponse {
        // @improve T14122
        $procedure = $procedureService->getProcedure($procedureId);
        $mapOptions = $mapService->getMapOptions($procedureId);
        $mapOfProcedure = $elementHandler->mapHandler($procedureId);

        /** @var GisLayerCategory $rootLayerCategory */
        $rootLayerCategory = $mapHandler->getRootLayerCategoryForProcedure($procedureId);

        // only need to get planningArea if procedure enforces specific area
        $availablePlanningAreas = data_get($globalConfig->getFormOptions(), 'project_planning_areas', []);

        $procedureSettings = $procedure->getSettings();

        $documents = $elementsService->getElementsAdminList($procedureId);

        $hasOverlays = false;
        /** @var GisLayer $layer */
        foreach ($rootLayerCategory->getGisLayers() as $layer) {
            if ($layer->isOverlay()) {
                $hasOverlays = true;
                break;
            }
        }

        //T13708: workaround to handle invalid date string in DB:
        $dateString = str_replace('Planstand ', '', $procedureSettings->getPlanText());
        try {
            $validDateString = Carbon::createFromFormat('d.m.Y', $dateString)->format('d.m.Y');
        } catch (Exception $e) {
            $validDateString = '';
        }

        $data = [
            'id'                     => $procedureId,
            'mapOptions'             => $mapOptions,
            'map'                    => $mapOfProcedure,
            'planText'               => $validDateString,
            'hasGisLayers'           => $hasOverlays,
            'planningArea'           => $procedureSettings->getPlanningArea(),
            'availablePlanningAreas' => $availablePlanningAreas,
            'documents'              => $documents,
        ];

        return $this->renderItem($data, DocumentDashboardTransformer::class);
    }

    /**
     * @Route(path="/api/1.0/documents/{procedureId}/dashboard",
     *        methods={"PATCH"},
     *        name="dp_api_documents_dashboard_update",
     *        options={"expose": true})
     *
     * @DplanPermissions("area_admin")
     *
     * Manages some updates performed from the dashboard.
     */
    public function updateDashboardAction(
        PermissionsInterface $permissions,
        ProcedureService $procedureService,
        string $procedureId
    ): Response {
        /** @var ResourceObject $documentDashboardData */
        $documentDashboardData = $this->requestData['DocumentDashboard'][$procedureId];

        $procedure = $procedureService->getProcedure($procedureId);
        $procedureSettings = $procedure->getSettings();

        $successMessages = [];

        if ($documentDashboardData->isPresent('planText')) {
            $procedureSettings->setPlanText($documentDashboardData->get('planText'));
            $successMessages[] = new Message('confirm', 'confirm.field.changes.saved', ['fieldName' => 'Planstand']);
        }

        if ($documentDashboardData->isPresent('planningArea') && $permissions->hasPermission(
                'feature_procedure_planning_area_match'
            )) {
            $procedureSettings->setPlanningArea($documentDashboardData['planningArea']);
            $successMessages[] = new Message('confirm', 'confirm.field.changes.saved', ['fieldName' => 'Planungsbereich']);
        }

        /** @var ProcedureRepository $procedureRepository */
        $procedureRepository = $this->getDoctrine()->getRepository(Procedure::class);
        $procedure->setSettings($procedureSettings);
        $updatedProcedure = $procedureRepository->updateObject($procedure);
        // always update elasticsearch as changes that where made only in
        // ProcedureSettings not automatically trigger an ES update
        $this->objectPersister->replaceOne($updatedProcedure);
        foreach ($successMessages as $message) {
            $this->getMessageBag()->addObject($message);
        }

        return $this->renderSuccess();
    }
}
