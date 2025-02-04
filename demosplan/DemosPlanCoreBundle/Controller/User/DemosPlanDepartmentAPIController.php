<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaHandler;
use demosplan\DemosPlanCoreBundle\ResourceTypes\DepartmentResourceType;
use demosplan\DemosPlanCoreBundle\Services\ApiResourceService;
use Symfony\Component\Routing\Annotation\Route;

class DemosPlanDepartmentAPIController extends APIController
{
    /**
     * @DplanPermissions("area_manage_users")
     *
     * @param string $organisationId
     */
    #[Route(path: '/api/1.0/{organisationId}/department', methods: ['GET'], name: 'dplan_api_department_list', options: ['expose' => true])]
    public function listAction(ApiResourceService $apiResourceService, OrgaHandler $orgaHandler, $organisationId): APIResponse
    {
        $orga = $orgaHandler->getOrga($organisationId);
        $departments = $orga->getDepartments();

        $collection = $apiResourceService->makeCollectionOfResources($departments, DepartmentResourceType::getName());

        return $this->renderResource($collection);
    }
}
