<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Runs psalm checks https://psalm.dev
 * Class PsalmCommand.
 */
class PsalmCommand extends CoreCommand
{
    private const PSALM_CONFIG_PATH = 'config/linters/psalm.template.xml';

    protected static $defaultName = 'dplan:psalm';
    protected static $defaultDescription = 'Run psalm code analysis';

    public function configure(): void
    {
        $this->addOption(
            'level',
            'l',
            InputOption::VALUE_REQUIRED,
            'Psalm Level. 8 is loosest, 5 would be fine',
            '6'
        );

        $this->addOption(
            'ci',
            '',
            InputOption::VALUE_NONE,
            'Run in CI mode'
        );

        $this->addOption(
            'alter',
            '',
            InputOption::VALUE_NONE,
            'alter code'
        );

        $this->addOption(
            'dry-run',
            '',
            InputOption::VALUE_NONE,
            'dry run'
        );

        $this->addOption(
            'issues',
            '',
            InputOption::VALUE_OPTIONAL,
            'define issues to alter'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $configFilePath = DemosPlanPath::getTemporaryPath(uniqid('', true).'psalm.xml');

        $level = $input->getOption('level');

        // replace template placeholder
        $config = str_replace(
            ['{{ project_prefix }}', '{{ error_level }}', '{{ project_folder }}', '{{ root }}'],
            [
                $this->parameterBag->get('project_prefix'),
                $level,
                $this->parameterBag->get('project_folder'),
                DemosPlanPath::getRootPath(),
            ],
            // uses local file, no need for flysystem
            file_get_contents(DemosPlanPath::getRootPath(self::PSALM_CONFIG_PATH))
        );

        // local file is valid, no need for flysystem
        file_put_contents($configFilePath, $config);

        $command = ['vendor/bin/psalm', '-c', $configFilePath];

        if ($input->getOption('alter')) {
            $command[] = '--alter';
        }

        if ($input->getOption('dry-run')) {
            $command[] = '--dry-run';
        }

        if ($input->getOption('issues')) {
            $command[] = '--issues='.$input->getOption('issues');
        }

        $output->writeln(\implode(' ', $command));

        $process = new Process($command);

        $process->setWorkingDirectory(DemosPlanPath::getRootPath());

        if (!$input->getOption('ci')) {
            $process->setTty(true);
        }

        $process->setPty(true);
        $process->setTimeout(0);
        $process->enableOutput();

        $process->run();

        if (!$input->getOption('ci')) {
            $output->write($process->getOutput());
        }

        return (int) $process->getExitCode();
    }
}
