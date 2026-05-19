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
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Exception\AttachedChildException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Class DemosPlanProcedureLayerCategoryAPIController.
 */
#[Route(path: '/api/1.0/GisLayerCategory', options: ['expose' => true])]
class DemosPlanProcedureLayerCategoryAPIController extends APIController
{
    /**
     * Delete a specific GisLayerCategory.
     *
     * @return $this|JsonResponse
     *
     * @throws MessageBagException
     */
    #[DplanPermissions(['area_admin_map', 'feature_map_category'])]
    #[Route(path: '/{layerCategoryId}', methods: ['DELETE'], name: 'dplan_api_procedure_layer_category_delete')]
    public function layerCategoryDelete(string $layerCategoryId, MapService $mapService)
    {
        try {
            $mapService->deleteGisLayerCategory($layerCategoryId);
            $this->messageBag->add('confirm', 'confirm.gislayerCategory.delete');

            return $this->renderDelete();
        } catch (AttachedChildException $e) {
            $this->messageBag->add(
                'warning',
                'warning.gisLayerCategory.delete.because.of.children',
                ['categoryName' => $e->getName()]
            );

            return $this->handleApiError($e);
        } catch (Exception $e) {
            $this->messageBag->add('error', 'error.gislayerCategory.delete');

            return $this->handleApiError($e);
        }
    }
}
