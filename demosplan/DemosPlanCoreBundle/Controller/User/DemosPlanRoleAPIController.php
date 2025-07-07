<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\RoleResourceType;
use Exception;
use Symfony\Component\Routing\Annotation\Route;

class DemosPlanRoleAPIController extends APIController
{
    /**
     * @DplanPermissions("area_manage_users")
     */
    #[Route(path: '/api/1.0/role', methods: ['GET'], name: 'dplan_api_role_list', options: ['expose' => true])]
    public function listAction(RoleService $roleService, OrgaService $orgaService, CurrentUserInterface $currentUser): APIResponse
    {
        try {
            $user = $currentUser->getUser();
            $customer = $user->getCurrentCustomer();
            $orga = $user->getOrga();
            $acceptedOrgaTypes = $orgaService->getAcceptedOrgaTypes($orga, $customer);
            $roles = $roleService->getGivableRoles($acceptedOrgaTypes);
            $collection = $this->resourceService->makeCollectionOfResources(
                $roles,
                RoleResourceType::getName()
            );

            return $this->renderResource($collection);
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
}
