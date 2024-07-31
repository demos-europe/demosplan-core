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
 * This Command is the parent class for enable and disable permission commands.
 */
abstract class PermissionForCustomerOrgaRoleCommand extends CoreCommand
{
    public function configure(): void
    {
        $this->addArgument(
            'customerIds',
            InputArgument::REQUIRED,
            'The Ids of the customer you want to adjust the permission, comma separated.'
        );

        $this->addArgument(
            'roleIds',
            InputArgument::REQUIRED,
            'The Ids of the role you want to adjust the permission, comma separated.'
        );

        $this->addArgument(
            'permission',
            InputArgument::REQUIRED,
            'The name of the permission to be adjusted.'
        );

        $this->addOption(
            'dry-run',
            '',
            InputOption::VALUE_NONE,
            'Initiates a dry run with verbose output to see what would happen.'
        );
    }

    public function __construct(
        ParameterBagInterface $parameterBag,
        protected readonly CustomerService $customerService,
        protected readonly RoleService $roleService,
        ?string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function displayOutcome(OutputInterface $output, $dryRun, array $updatedOrgas, CustomerInterface $customerChoice, RoleInterface $roleChoice, string $action): void
    {
        $output->writeln('******************************************************');
        $output->writeln($dryRun ? 'This is a dry run. No changes have been made to the database.' : 'Changes have been applied to the database.');
        $output->writeln('******************************************************');
        $output->writeln('Permission has been '.$action.' for '.count($updatedOrgas).' orgas');
        $output->writeln('Permission has been '.$action.' for mentioned orgas on:');
        $output->writeln('Customer '.$customerChoice->getId().' '.$customerChoice->getName());
        $output->writeln('Role '.$roleChoice->getId().' '.$roleChoice->getName());
    }

    /**
     * @throws RoleNotFoundException
     * @throws CustomerNotFoundException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $customerIdsString = $input->getArgument('customerIds');
        $roleIdsString = $input->getArgument('roleIds');
        $permissionName = $input->getArgument('permission');
        $dryRun = $input->getOption('dry-run');

        $roleIds = explode(',', $roleIdsString);
        $customerIds = explode(',', $customerIdsString);
        foreach ($roleIds as $roleId) {
            foreach ($customerIds as $customerId) {
                $customerChoice = $this->customerService->findCustomerById(trim($customerId));
                $roleChoice = $this->roleService->getRole(trim($roleId));
                $permissionChoice = $this->getConstantValueByName($permissionName);

                // Return Exception for RoleChoice as Customer already throws exception if null, and permission exception is handled in getConstantValueByName
                if (null === $roleChoice) {
                    throw new RoleNotFoundException('Role not found');
                }

                $updatedOrgas = $this->doExecuteAction($permissionChoice, $customerChoice, $roleChoice, $dryRun);

                $this->displayUpdatedOrgas($output, $updatedOrgas);

                $this->displayOutcome($output, $dryRun, $updatedOrgas, $customerChoice, $roleChoice, $this->getActionName());
            }
        }

        return Command::SUCCESS;
    }

    protected function displayUpdatedOrgas(OutputInterface $output, array $updatedOrgas): void
    {
        foreach ($updatedOrgas as $orga) {
            $output->writeln('Orga ID: '.$orga->getId());
            $output->writeln('Orga Name: '.$orga->getName());
        }
    }

    protected function getConstantValueByName($constantName): string
    {
        $className = AccessControlService::class;
        $constantFullName = $className.'::'.$constantName;
        if (defined($constantFullName)) {
            return constant($constantFullName);
        }

        throw new InvalidArgumentException('Permission does not exit');
    }

    abstract protected function doExecuteAction(string $permissionChoice, CustomerInterface $customerChoice, RoleInterface $roleChoice, mixed $dryRun): array;

    abstract protected function getActionName(): string;
}
