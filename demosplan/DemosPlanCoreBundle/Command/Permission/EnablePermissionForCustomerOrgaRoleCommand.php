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
 * This Command is used to enable a specific permission for a given customer, organization, and role.
 */
class EnablePermissionForCustomerOrgaRoleCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:permission:enable:customer-orga-role';
    protected static $defaultDescription = 'Enables a specific permission for a given customer, organization, and role';

    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly CustomerService $customerService,
        private readonly RoleService $roleService,
        private readonly AccessControlService $accessControlPermissionService,
        ?string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }

    public function configure(): void
    {
        $this->addArgument(
            'customerId',
            InputArgument::REQUIRED,
            'The ID of the customer you want to enable the permission.'
        );

        $this->addArgument(
            'roleId',
            InputArgument::REQUIRED,
            'The ID of the role you want to enable the permission.'
        );

        $this->addArgument(
            'permission',
            InputArgument::REQUIRED,
            'The name of the permission to be enabled'
        );

        $this->addOption(
            'dry-run',
            '',
            InputOption::VALUE_NONE,
            'Initiates a dry run with verbose output to see what would happen.'
        );
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

        if (null === $customerChoice) {
            throw new CustomerNotFoundException('Customer not found');
        }

        if (null === $roleChoice) {
            throw new RoleNotFoundException('Role not found');
        }

        $updatedOrgas = $this->accessControlPermissionService->enablePermissionCustomerOrgaRole($permissionChoice, $customerChoice, $roleChoice, null, $dryRun);

        foreach ($updatedOrgas as $orga) {
            $output->writeln('Orga ID: '.$orga->getId());
            $output->writeln('Orga Name: '.$orga->getName());
        }

        $output->writeln('******************************************************');
        $output->writeln($dryRun ? 'This is a dry run. No changes have been made to the database.' : 'Changes have been applied to the database.');
        $output->writeln('******************************************************');
        $output->writeln('Permission has been enabled for mentioned orgas on:');
        $output->writeln('Customer '.$customerChoice->getId().' '.$customerChoice->getName());
        $output->writeln('Role '.$roleChoice->getId().' '.$roleChoice->getName());

        return Command::SUCCESS;
    }

    private function getConstantValueByName($constantName): string
    {
        $className = AccessControlService::class;
        $constantFullName = $className.'::'.$constantName;
        if (defined($constantFullName)) {
            return constant($constantFullName);
        }

        throw new InvalidArgumentException('Permission does not exit');
    }
}
