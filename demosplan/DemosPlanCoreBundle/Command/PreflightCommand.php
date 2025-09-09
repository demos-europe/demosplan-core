<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class PreflightCommand.
 *
 * Preflight is part of the update process. It manages switching git branches,
 * updating the code and runs the required composer and npm commands to set up
 * a usable system.
 *
 * @see readme.md#Updating-a-project
 */
class PreflightCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:preflight';
    protected static $defaultDescription = 'Manages Git branches, updating code and composer+npm updates';

    public function configure(): void
    {
        $this->addOption(
            'branch',
            'b',
            InputOption::VALUE_REQUIRED,
            'Branch to check out and update'
        )
        ->addOption(
            'no-dev',
            null,
            InputOption::VALUE_NONE,
            'Do not provide dev resources'
        )
        ->addOption(
            'no-logging',
            null,
            InputOption::VALUE_NONE,
            'Run this command without logging'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = $this->getLoggingOutput($output, !$input->getOption('no-logging'), 'preflight.log');
        $io = new SymfonyStyle($input, $output);

        $io->title('Update: Preflight');
        /*
         * - git checkout -- .
         * - git
         */
        return Command::SUCCESS;
    }
}
