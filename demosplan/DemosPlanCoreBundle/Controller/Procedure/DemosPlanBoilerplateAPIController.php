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
use demosplan\DemosPlanCoreBundle\ResourceTypes\BoilerplateGroupResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\BoilerplateResourceType;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use Symfony\Component\Routing\Annotation\Route;

class DemosPlanBoilerplateAPIController extends APIController
{
    /**
     * @Route(path="/api/1.0/procedures/{procedureId}/relationships/boilerplates",
     *        methods={"GET"},
     *        name="dplan_api_procedure_boilerplate_list",
     *        options={"expose": true})
     *
     * Returns all Boilerplates(means "Textbausteine"/"_predefined_texts", not "ProcedureBlueprints"!)
     * of a specific procedure, with the category as key in a JsonResponse.
     *
     * @DplanPermissions("area_admin_boilerplates")
     *
     * @param string $procedureId specify the Procedure, whose Boilerplates will be loaded
     */
    public function getProcedureListAction(ProcedureService $procedureService, string $procedureId): APIResponse
    {
        $boilerplates = $procedureService->getBoilerplateList($procedureId);
        $collection = $this->resourceService->makeCollectionOfResources($boilerplates, BoilerplateResourceType::getName());

        return $this->renderResource($collection);
    }

    /**
     * @Route(path="/api/1.0/procedures/{procedureId}/relationships/boilerplate_groups",
     *        methods={"GET"},
     *        name="dplan_api_procedure_boilerplate_group_list",
     *        options={"expose": true})
     *
     * Returns all boilerplateGroups of a specific procedure, as JsonResponse.
     *
     * @param string $procedureId specify the Procedure, whose BoilerplateGroups will be loaded
     */
    public function getProcedureGroupListAction(ProcedureService $procedureService, string $procedureId): APIResponse
    {
        $groups = $procedureService->getBoilerplateGroups($procedureId);
        $collection = $this->resourceService->makeCollectionOfResources(
            $groups,
            BoilerplateGroupResourceType::getName()
        );

        return $this->renderResource($collection);
    }
}
