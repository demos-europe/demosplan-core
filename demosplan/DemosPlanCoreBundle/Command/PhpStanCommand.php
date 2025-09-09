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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class PhpStanCommand extends CoreCommand
{
    private const PHPSTAN_CONFIG_PATH = 'config/linters/phpstan.template.neon';

    protected static $defaultName = 'dplan:phpstan';
    protected static $defaultDescription = 'Run PHPStan';

    public function configure(): void
    {
        $this->addOption(
            'level',
            'l',
            InputOption::VALUE_REQUIRED,
            'PHPStan Level',
            0
        );

        $this->addArgument(
            'path',
            InputArgument::OPTIONAL,
            'Path to analyse',
            'demosplan'
        );

        $this->addOption(
            'only-dump-config',
            'C',
            InputOption::VALUE_NONE,
            'Only dump configured phpstan.neon and exit'
        );

        $this->addOption(
            'ci',
            '',
            InputOption::VALUE_NONE,
            'Indicate that the checks will be run in a CI environment'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $configSavePath = $this->writeConfig($input, $output);

        if ($input->getOption('only-dump-config')) {
            return Command::SUCCESS;
        }

        $path = $input->getArgument('path');

        $level = (int) $input->getOption('level');

        return (int) $this->doRunPhpStan($input, $output, $configSavePath, $path, $level);
    }

    protected function writeConfig(InputInterface $input, OutputInterface $output): string
    {
        $configSavePath = 'phpstan.neon';

        // poor dev's twig
        $configLoadPath = self::PHPSTAN_CONFIG_PATH;

        $containerPath = $this->parameterBag->get('debug.container.dump');

        // Convert container paths to work both inside and outside the container
        $rootPath = DemosPlanPath::getRootPath();

        // Handle container path format (/srv/www/...)
        if (str_starts_with($containerPath, '/srv/www/')) {
            $containerPath = substr($containerPath, 9); // remove /srv/www/
        }

        // Handle host path format
        if (str_starts_with($containerPath, $rootPath)) {
            $containerPath = substr($containerPath, strlen($rootPath) + 1);
        }

        // Ensure the container path exists and is accessible
        if (file_exists($rootPath . '/' . $containerPath)) {
            $output->writeln(sprintf('Using container path: %s', $containerPath));
        } elseif (file_exists('/srv/www/' . $containerPath)) {
            $output->writeln(sprintf('Using container path: %s (in container)', $containerPath));
        } else {
            $output->writeln(sprintf('<warning>Warning: Container file not found. Using best guess: %s</warning>', $containerPath));
        }

        $config = str_replace(
            '{{ container_path }}',
            $containerPath,
            // uses local file, no need for flysystem
            file_get_contents($configLoadPath)
        );

        // local file is valid, no need for flysystem
        file_put_contents($configSavePath, $config);

        return $configSavePath;
    }

    protected function doRunPhpStan(
        InputInterface $input,
        OutputInterface $output,
        string $configSavePath,
        string $path,
        int $level,
    ): ?int {
        $isCi = $input->getOption('ci');

        $cmd = [
            'vendor/bin/phpstan',
            'analyse',
            '-c',
            $configSavePath,
            $path,
        ];

        if (0 < $level) {
            $cmd[] = '-l';
            $cmd[] = $level;
        }

        if ($isCi) {
            $cmd[] = '--error-format';
            $cmd[] = 'raw';
        }

        $output->writeln(implode(' ', $cmd));

        $process = new Process($cmd);

        $process->setWorkingDirectory(DemosPlanPath::getRootPath());

        // disable tty in ContinuousIntegration environment
        $process->setTty(!$isCi);
        $process->setPty(true);

        $process->setTimeout(0);
        $process->enableOutput();

        $process->run();

        $output->write($process->getOutput());

        return $process->getExitCode();
    }
}
