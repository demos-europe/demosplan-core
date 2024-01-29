<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use EFrane\ConsoleAdditions\Output\FileOutputInterface;
use EFrane\ConsoleAdditions\Output\MultiplexedOutput;
use EFrane\ConsoleAdditions\Output\NativeFileOutput;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

abstract class CoreCommand extends Command
{
    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;

    public function __construct(ParameterBagInterface $parameterBag, ?string $name = null)
    {
        parent::__construct($name);
        $this->parameterBag = $parameterBag;
    }

    public function getApplication(): ConsoleApplication
    {
        $application = parent::getApplication();

        if (!($application instanceof ConsoleApplication)) {
            throw new RuntimeException('Core commands must be registered to '.ConsoleApplication::class);
        }

        return $application;
    }

    /**
     * Multiplexes the output into a logfile
     * The logfile will not be appended but overwritten on each php process start.
     *
     * @param bool   $enableLogging
     * @param string $logFilename
     *
     * @return MultiplexedOutput|OutputInterface
     */
    protected function getLoggingOutput(OutputInterface $output, $enableLogging, $logFilename = 'output.log')
    {
        if (!$enableLogging) {
            return $output;
        }

        try {
            $output = new MultiplexedOutput(
                [
                $output,
                    new NativeFileOutput(
                        DemosPlanPath::getRootPath("logs/{$logFilename}"),
                        FileOutputInterface::WRITE_MODE_RESET
                    ),
                ]
            );
        } catch (Exception) {
            $output->writeln('<warning>Output might not be logged!</warning>');
        }

        return $output;
    }

    /**
     * @param bool   $enableLogging
     * @param string $logFilename
     */
    protected function setupIo(
        InputInterface $input,
        OutputInterface $output,
        $enableLogging = true,
        $logFilename = 'command.log'
    ): SymfonyStyle {
        $output = $this->getLoggingOutput($output, $enableLogging, $logFilename);

        return new SymfonyStyle($input, $output);
    }
}
