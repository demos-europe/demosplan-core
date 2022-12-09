<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use Symfony\Component\Routing\Annotation\Route;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\ResourceTypes\DepartmentResourceType;
use demosplan\DemosPlanCoreBundle\Services\ApiResourceService;
use demosplan\DemosPlanUserBundle\Logic\OrgaHandler;

class DemosPlanDepartmentAPIController extends APIController
{
    /**
     * @Route(path="/api/1.0/{organisationId}/department/",
     *        methods={"GET"},
     *        name="dplan_api_department_list",
     *        options={"expose": true})
     *
     * @DplanPermissions("area_manage_users")
     *
     * @param string $organisationId
     */
    public function listAction(ApiResourceService $apiResourceService, OrgaHandler $orgaHandler, $organisationId): APIResponse
    {
        $orga = $orgaHandler->getOrga($organisationId);
        $departments = $orga->getDepartments();

        $collection = $apiResourceService->makeCollectionOfResources($departments, DepartmentResourceType::getName());

        return $this->renderResource($collection);
    }
}
