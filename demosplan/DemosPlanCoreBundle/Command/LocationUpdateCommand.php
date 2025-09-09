<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\Logic\LocationUpdateService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Update Location table with current data.
 */
class LocationUpdateCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:location:repopulate';
    protected static $defaultDescription = 'Repopulate location table with current Data';

    public function configure(): void
    {
        $this->addOption(
            'includeOnly',
            'i',
            InputOption::VALUE_OPTIONAL,
            'Comma separated list of Bundeslandcodes to include exclusively'
        );
    }

    public function __construct(private readonly LocationUpdateService $locationUpdate, ParameterBagInterface $parameterBag, string $name = null)
    {
        parent::__construct($parameterBag, $name);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $includeOnly = [];
        if ($input->getOption('includeOnly')) {
            $includeOnly = explode(',', (string) $input->getOption('includeOnly'));
        }

        $this->locationUpdate->repopulateDatabase($includeOnly);

        return Command::SUCCESS;
    }
}
