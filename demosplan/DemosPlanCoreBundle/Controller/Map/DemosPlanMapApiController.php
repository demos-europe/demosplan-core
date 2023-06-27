<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Map;

use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Transformers\Map\MapOptionsTransformer;
use Exception;
use Symfony\Component\Routing\Annotation\Route;

class DemosPlanMapApiController extends APIController
{
    /**
     * @DplanPermissions("area_admin")
     *
     * @throws Exception
     */
    #[Route(path: '/api/1.0/map/options/admin/{procedureId}', methods: ['GET'], name: 'dplan_api_map_options_admin', options: ['expose' => true])]
    public function optionsAdminAction(MapService $mapService, string $procedureId): APIResponse
    {
        // @improve T14122
        $mapOptions = $mapService->getMapOptions($procedureId);

        return $this->renderItem($mapOptions, MapOptionsTransformer::class);
    }

    /**
     * @DplanPermissions("area_demosplan")
     *
     * @throws Exception
     */
    #[Route(path: '/api/1.0/map/options/public/{procedureId}', methods: ['GET'], name: 'dplan_api_map_options_public', options: ['expose' => true])]
    public function optionsPublicAction(MapService $mapService, string $procedureId): APIResponse
    {
        // @improve T14122
        $mapOptions = $mapService->getMapOptions($procedureId);

        return $this->renderItem($mapOptions, MapOptionsTransformer::class);
    }
}
