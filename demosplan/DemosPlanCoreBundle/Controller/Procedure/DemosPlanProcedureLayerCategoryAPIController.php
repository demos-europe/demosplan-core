<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use DemosEurope\DemosplanAddon\Controller\APIController;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Exception\AttachedChildException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanMapBundle\Logic\MapService;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DemosPlanProcedureLayerCategoryAPIController.
 *
 * @Route(
 *     "/api/1.0/GisLayerCategory",
 *     options={"expose": true}
 * )
 */
class DemosPlanProcedureLayerCategoryAPIController extends APIController
{
    /**
     * Delete a specific GisLayerCategory.
     *
     * @Route(
     *     path="/{layerCategoryId}",
     *     methods={"DELETE"},
     *     name="dplan_api_procedure_layer_category_delete")
     * @DplanPermissions({"area_admin_map","feature_map_category"})
     *
     * @return $this|JsonResponse
     *
     * @throws MessageBagException
     */
    public function layerCategoryDeleteAction(string $layerCategoryId, MapService $mapService)
    {
        try {
            $mapService->deleteGisLayerCategory($layerCategoryId);
            $this->getMessageBag()->add('confirm', 'confirm.gislayerCategory.delete');

            return $this->renderDelete();
        } catch (AttachedChildException $e) {
            $this->getMessageBag()->add(
                'warning',
                'warning.gisLayerCategory.delete.because.of.children',
                ['categoryName' => $e->getName()]
            );

            return $this->handleApiError($e);
        } catch (Exception $e) {
            $this->getMessageBag()->add('error', 'error.gislayerCategory.delete');

            return $this->handleApiError($e);
        }
    }
}
