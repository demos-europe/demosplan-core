<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Document;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Logger\ApiLoggerInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\ResourceObject;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapHandler;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\MessageSerializable;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\Transformers\Document\DocumentDashboardTransformer;
use Doctrine\Persistence\ManagerRegistry;
use EDT\JsonApi\RequestHandling\MessageFormatter;
use EDT\JsonApi\Validation\FieldsValidator;
use EDT\Wrapping\TypeProviders\PrefilledTypeProvider;
use EDT\Wrapping\Utilities\SchemaPathProcessor;
use Exception;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class DemosPlanDocumentDashboardAPIController extends APIController
{
    public function __construct(
        ApiLoggerInterface $apiLogger,
        FieldsValidator $fieldsValidator,
        PrefilledTypeProvider $resourceTypeProvider,
        TranslatorInterface $translator,
        private readonly ObjectPersisterInterface $objectPersister,
        LoggerInterface $logger,
        GlobalConfigInterface $globalConfig,
        MessageBagInterface $messageBag,
        MessageFormatter $messageFormatter,
        SchemaPathProcessor $schemaPathProcessor,
        private ManagerRegistry $managerRegistry
    ) {
        parent::__construct(
            $apiLogger,
            $resourceTypeProvider,
            $fieldsValidator,
            $translator,
            $logger,
            $globalConfig,
            $messageBag,
            $schemaPathProcessor,
            $messageFormatter
        );
    }

    /**
     * @DplanPermissions("area_admin")
     * Manages the display of the dashboard on load.
     */
    #[Route(path: '/api/1.0/documents/{procedureId}/dashboard', methods: ['GET'], name: 'dp_api_documents_dashboard_get', options: ['expose' => true])]
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

        // T13708: workaround to handle invalid date string in DB:
        $dateString = str_replace('Planstand ', '', (string) $procedureSettings->getPlanText());
        try {
            $validDateString = Carbon::createFromFormat('d.m.Y', $dateString)->format('d.m.Y');
        } catch (Exception) {
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
     * @DplanPermissions("area_admin")
     * Manages some updates performed from the dashboard.
     */
    #[Route(path: '/api/1.0/documents/{procedureId}/dashboard', methods: ['PATCH'], name: 'dp_api_documents_dashboard_update', options: ['expose' => true])]
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
            $successMessages[] = new MessageSerializable('confirm', 'confirm.field.changes.saved', ['fieldName' => 'Planstand']);
        }

        if ($documentDashboardData->isPresent('planningArea') && $permissions->hasPermission(
            'feature_procedure_planning_area_match'
        )) {
            $procedureSettings->setPlanningArea($documentDashboardData['planningArea']);
            $successMessages[] = new MessageSerializable('confirm', 'confirm.field.changes.saved', ['fieldName' => 'Planungsbereich']);
        }

        /** @var ProcedureRepository $procedureRepository */
        $procedureRepository = $this->managerRegistry->getRepository(Procedure::class);
        $procedure->setSettings($procedureSettings);
        $updatedProcedure = $procedureRepository->updateObject($procedure);
        // always update elasticsearch as changes that where made only in
        // ProcedureSettings not automatically trigger an ES update
        $this->objectPersister->replaceOne($updatedProcedure);
        foreach ($successMessages as $message) {
            $this->messageBag->addObject($message);
        }

        return $this->renderSuccess();
    }
}
