<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class DataProviderCommand extends CoreCommand
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var SymfonyStyle
     */
    protected $output;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = new SymfonyStyle($input, $output);

        return $this->handle();
    }

    abstract protected function handle(): int;

    protected function hasArgument($name)
    {
        return $this->input->hasArgument($name);
    }

    protected function getArgument($name)
    {
        return $this->input->getArgument($name);
    }

    protected function getOption($name)
    {
        return $this->input->getOption($name);
    }

    protected function hasOption($name): bool
    {
        return $this->input->hasOption($name);
    }

    protected function info($message): void
    {
        $this->line(sprintf("<info>{$message}</info>"));
    }

    protected function line($line = ''): void
    {
        $this->output->writeln($line);
    }

    protected function fatal($message, $code = -1): int
    {
        $this->error($message);

        return $code;
    }

    protected function error($message): void
    {
        $this->line(sprintf("<error>{$message}</error>"));
    }

    /**
     * @param int $amount
     */
    protected function createGeneratorProgressBar($amount): ProgressBar
    {
        $progressBar = new ProgressBar($this->output, $amount);

        $format = '[#%current%/%max%] %bar% (Elapsed: %elapsed%, Estimated: %estimated%)';
        if ($amount > 1) {
            $format = "%message%\n".$format;
        }

        $progressBar->setFormat($format);

        return $progressBar;
    }
}
