<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Permission;

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * This Command is used to enable a specific permission for a given customer, organization, and role.
 */
class EnablePermissionForCustomerOrgaRoleCommand extends PermissionForCustomerOrgaRoleCommand
{
    protected static $defaultName = 'dplan:permission:enable:customer-orga-role';
    protected static $defaultDescription = 'Enables a specific permission for a given customer, organization, and role';

    public function __construct(
        ParameterBagInterface $parameterBag,
        CustomerService $customerService,
        RoleService $roleService,
        private readonly AccessControlService $accessControlPermissionService,
        ?string $name = null
    ) {
        parent::__construct($parameterBag, $customerService, $roleService, $name);
    }

    protected function doExecuteAction(string $permissionChoice, CustomerInterface $customerChoice, RoleInterface $roleChoice, mixed $dryRun): array
    {
        return $this->accessControlPermissionService->enablePermissionCustomerOrgaRole(
            $permissionChoice,
            $customerChoice,
            $roleChoice,
            $dryRun
        );
    }
}
