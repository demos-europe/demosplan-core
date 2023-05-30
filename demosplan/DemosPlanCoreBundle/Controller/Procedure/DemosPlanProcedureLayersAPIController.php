<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Exception\GisLayerCategoryTreeTooDeepException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapHandler;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GisLayerCategoryResourceType;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DemosPlanProcedureLayersAPIController.
 *
 * @Route(
 *     path="/api/1.0/procedure/{procedureId}/layers",
 *     name="dplan_api_procedure_layer_",
 *     options={"expose": true}
 * )
 */
class DemosPlanProcedureLayersAPIController extends APIController
{
    /**
     * get params: type.
     *
     * @Route(methods={"GET"}, name="list")
     *
     * @DplanPermissions("area_map_participation_area")
     */
    public function layersListAction(MapHandler $mapHandler, string $procedureId): APIResponse
    {
        $rootLayerCategory = $mapHandler->getRootLayerCategoryForProcedure($procedureId);
        $resource = $this->resourceService->makeItemOfResource($rootLayerCategory, GisLayerCategoryResourceType::getName());

        return $this->renderResource($resource);
    }

    /**
     * @Route(methods={"POST", "PATCH"}, name="update")
     *
     * @DplanPermissions("area_admin_map")
     *
     * @throws MessageBagException
     */
    public function layersUpdateAction(MapHandler $mapHandler): APIResponse
    {
        $rootCategory = $this->getRequestJson('data');
        $messageBag = $this->messageBag;

        try {
            $mapHandler->updateElementsOfRootCategory($rootCategory);
            $messageBag->add('confirm', 'confirm.gislayers.updated');

            return $this->renderEmpty();
        } catch (GisLayerCategoryTreeTooDeepException $e) {
            $maxDepth = $e->getMaxTreeDepth();
            $messageBag->add(
                'warning',
                'error.gislayerCategory.treedepth',
                ['max_depth' => $maxDepth]
            );
        } catch (Exception $e) {
            $messageBag->add('error', 'error.gislayers.updated');
        }

        return $this->handleApiError($e);
    }

    /**
     * Delete a specific GisLayer.
     *
     * @Route(path="{layerId}", methods={"DELETE"}, name="delete")
     *
     * @DplanPermissions("area_admin_map")
     *
     * @return $this|JsonResponse
     *
     * @throws MessageBagException
     */
    public function layerDeleteAction(MapHandler $mapHandler, string $layerId)
    {
        try {
            $mapHandler->deleteGisLayer($layerId);
            $this->messageBag->add('confirm', 'confirm.gislayer.delete');

            return $this->renderDelete();
        } catch (Exception $e) {
            $this->messageBag->add('error', 'error.gislayer.delete');

            return $this->handleApiError($e);
        }
    }
}
