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
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InvitablePublicAgencyResourceType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class DemosPlanInvitableToebAPIController extends APIController
{
    /**
     * @return JsonResponse
     *
     * @Route(path="/api/1.0/procedure/{procedureId}/InvitableToeb", methods={"GET"}, name="dplan_api_invitable_toeb_list")
     *
     * @DplanPermissions({"area_main_procedures","area_admin_invitable_institution"})
     */
    public function listAction(OrgaService $orgaService)
    {
        $orgaList = $orgaService->getInvitablePublicAgencies();
        $collection = $this->resourceService->makeCollectionOfResources($orgaList, InvitablePublicAgencyResourceType::getName());

        return $this->renderResource($collection);
    }
}
