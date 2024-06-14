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
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This Command is the parent class for enable and disable permission commands.
 */
abstract class PermissionForCustomerOrgaRoleCommand extends CoreCommand
{
    public function configure(): void
    {
        $this->addArgument(
            'customerId',
            InputArgument::REQUIRED,
            'The ID of the customer you want to adjust the permission.'
        );

        $this->addArgument(
            'roleId',
            InputArgument::REQUIRED,
            'The ID of the role you want to adjust the permission.'
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

    protected function displayOutcome( OutputInterface $output, $dryRun, array $updatedOrgas, CustomerInterface $customerChoice, RoleInterface $roleChoice, string $action): void
    {

        $output->writeln('******************************************************');
        $output->writeln($dryRun ? 'This is a dry run. No changes have been made to the database.' : 'Changes have been applied to the database.');
        $output->writeln('******************************************************');
        $output->writeln('Permission has been ' . $action . ' for '. count($updatedOrgas).' orgas');
        $output->writeln('Permission has been ' . $action . ' for mentioned orgas on:');
        $output->writeln('Customer '.$customerChoice->getId().' '.$customerChoice->getName());
        $output->writeln('Role '.$roleChoice->getId().' '.$roleChoice->getName());

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
}
