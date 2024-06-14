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
