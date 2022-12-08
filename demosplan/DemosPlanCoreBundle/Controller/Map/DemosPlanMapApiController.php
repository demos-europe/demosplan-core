<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Map;

use DemosEurope\DemosplanAddon\Controller\APIController;
use Exception;
use Symfony\Component\Routing\Annotation\Route;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Response\APIResponse;
use demosplan\DemosPlanMapBundle\Logic\MapService;
use demosplan\DemosPlanMapBundle\Transformers\MapOptionsTransformer;

class DemosPlanMapApiController extends APIController
{
    /**
     * @Route(path="/api/1.0/map/options/admin/{procedureId}",
     *        methods={"GET"},
     *        name="dplan_api_map_options_admin",
     *        options={"expose": true})
     *
     * @DplanPermissions("area_admin")
     *
     * @throws Exception
     */
    public function optionsAdminAction(MapService $mapService, string $procedureId): APIResponse
    {
        // @improve T14122
        $mapOptions = $mapService->getMapOptions($procedureId);

        return $this->renderItem($mapOptions, MapOptionsTransformer::class);
    }

    /**
     * @Route(path="/api/1.0/map/options/public/{procedureId}",
     *        methods={"GET"},
     *        name="dplan_api_map_options_public",
     *        options={"expose": true})
     *
     * @DplanPermissions("area_demosplan")
     *
     * @throws Exception
     */
    public function optionsPublicAction(MapService $mapService, string $procedureId): APIResponse
    {
        // @improve T14122
        $mapOptions = $mapService->getMapOptions($procedureId);

        return $this->renderItem($mapOptions, MapOptionsTransformer::class);
    }
}
