<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Deployment;

use demosplan\DemosPlanCoreBundle\Logic\DemosFilesystem;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class Strategy implements StrategyInterface
{
    /**
     * @var Application
     */
    protected $application;

    /**
     * @var DemosFilesystem
     */
    protected $filesystem;

    /**
     * @var string
     */
    protected $tempDir;

    /**
     * @var string
     */
    protected $rootDir;

    /**
     * @var string
     */
    protected $projectName;

    /**
     * @var string
     */
    protected $gitReference;

    public function __construct()
    {
        $this->filesystem = new DemosFilesystem();
        $this->tempDir = DemosPlanPath::getTemporaryPath('dplanDeploy');
        $this->rootDir = DemosPlanPath::getRootPath();
    }

    /**
     * Runs a shell command.
     *
     * @param array|string    $command
     * @param OutputInterface $output
     * @param string          $workingDir Working directory
     * @param bool            $silent
     * @param int             $timeout
     *
     * @return Process
     */
    protected function runProcess($command, $output, $workingDir = null, $silent = false, $timeout = 600)
    {
        $process = new Process($command);
        $process->setTimeout($timeout);
        if (!is_null($workingDir)) {
            $process->setWorkingDirectory($workingDir);
        }
        try {
            $process->run(
                function ($type, $buffer) use ($silent, $output) {
                    if (!$silent) {
                        $currentVerbosity = $output->getVerbosity();
                        $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
                        $output->write($buffer);
                        $output->setVerbosity($currentVerbosity);
                    }
                }
            );
        } catch (Exception $e) {
            if (\is_array($command)) {
                $command = \implode(' ', $command);
            }

            $output->writeln('Some error in '.$command.' occurred '.$e->getMessage());
        }

        return $process;
    }

    public function getProjectName()
    {
        return $this->projectName;
    }

    public function setProjectName($projectName)
    {
        $this->projectName = $projectName;
    }

    /**
     * @return string
     */
    public function getGitReference()
    {
        return $this->gitReference;
    }

    /**
     * @param string $gitReference
     */
    public function setGitReference($gitReference)
    {
        $this->gitReference = $gitReference;
    }

    /**
     * @return Application
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @param Application $application
     */
    public function setApplication($application)
    {
        $this->application = $application;
    }
}
