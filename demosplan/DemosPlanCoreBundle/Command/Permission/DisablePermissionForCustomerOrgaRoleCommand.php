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

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\RoleNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleService;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * This Command is used to disable a specific permission for a given customer, organization, and role.
 */
class DisablePermissionForCustomerOrgaRoleCommand extends PermissionForCustomerOrgaRoleCommand
{
    protected static $defaultName = 'dplan:permission:disable:customer-orga-role';
    protected static $defaultDescription = 'Disables a specific permission for a given customer, organization, and role';

    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly CustomerService $customerService,
        private readonly RoleService $roleService,
        private readonly AccessControlService $accessControlPermissionService,
        ?string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }


    /**
     * @throws RoleNotFoundException
     * @throws CustomerNotFoundException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $customerId = $input->getArgument('customerId');
        $roleId = $input->getArgument('roleId');
        $permissionName = $input->getArgument('permission');
        $dryRun = $input->getOption('dry-run');

        $customerChoice = $this->customerService->findCustomerById($customerId);
        $roleChoice = $this->roleService->getRole($roleId);
        $permissionChoice = $this->getConstantValueByName($permissionName);

        // Return Exception for RoleChoice as Customer already throws exception if null, and permission exception is handled in getConstantValueByName
        if (null === $roleChoice) {
            throw new RoleNotFoundException('Role not found');
        }

        $updatedOrgas = $this->accessControlPermissionService->disablePermissionCustomerOrgaRole($permissionChoice, $customerChoice, $roleChoice, $dryRun);

        $this->displayUpdatedOrgas($output, $updatedOrgas);

        $this->displayOutcome($output, $dryRun, $updatedOrgas, $customerChoice, $roleChoice, 'disabled');

        return Command::SUCCESS;
    }

}
