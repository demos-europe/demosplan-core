<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Application;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ConsoleApplication extends Application
{
    public function __construct(KernelInterface $kernel, private readonly bool $isDeprecatedFrontController)
    {
        parent::__construct($kernel);

        /* @var DemosPlanKernel $kernel */
        $this->setName('demosplan.'.$kernel->getActiveProject().' on Symfony');
    }

    public function doRun(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $this->addProjectFolderConsoleDeprecationNotice($output);

        $res = parent::doRun($input, $output);

        $this->addProjectFolderConsoleDeprecationNotice($output);

        return $res;
    }

    private function addProjectFolderConsoleDeprecationNotice(OutputInterface $output): void
    {
        if ($this->isDeprecatedFrontController) {
            /** @var DemosPlanKernel $kernel */
            $kernel = $this->getKernel();
            $activeProject = $kernel->getActiveProject();
            $message = "Warning, this console is moving to bin/{$activeProject}, please adjust your usage accordingly.";

            $output->write("\e[31m{$message}\e[0m\n");
        }
    }

    /**
     * @return DemosPlanKernel|KernelInterface
     */
    public function getKernel(): KernelInterface
    {
        return parent::getKernel();
    }
}
