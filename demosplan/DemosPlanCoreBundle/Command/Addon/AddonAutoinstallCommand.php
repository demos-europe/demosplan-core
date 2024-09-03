<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Addon;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


class AddonAutoinstallCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:addon:autoinstall';
    protected static $defaultDescription = 'Installs any addons defined in project configuration addons.yaml';

    public function execute(InputInterface $input, OutputInterface $output): int
    {

        $addons = $this->parameterBag->get('dplan_addons');
        // return if no addons are defined
        if (null === $addons) {
            return Command::SUCCESS;
        }
        return Command::SUCCESS;

    }


}
