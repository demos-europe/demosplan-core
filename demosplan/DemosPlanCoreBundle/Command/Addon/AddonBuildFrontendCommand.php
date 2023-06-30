<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Addon;

use demosplan\DemosPlanCoreBundle\Addon\AddonRegistry;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use EFrane\ConsoleAdditions\Batch\Batch;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AddonBuildFrontendCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:addon:build-frontend';

    public function __construct(private readonly AddonRegistry $registry, ParameterBagInterface $parameterBag, string $name = null)
    {
        parent::__construct($parameterBag, $name);
    }

    protected function configure()
    {
        $this->setDescription('Build frontend assets for an addon');
        $this->addArgument('addon-name', InputArgument::REQUIRED, 'Addon name, du\'h.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("Building frontend assets for {$input->getArgument('addon-name')}");

        $addonInfo = $this->registry[$input->getArgument('addon-name')];

        $addonPath = DemosPlanPath::getRootPath($addonInfo->getInstallPath());
        $consoleReturn = Batch::create($this->getApplication(), $output)
            ->addShell(['yarn', 'install', '--frozen-lockfile'], $addonPath)
            ->addShell(['yarn', 'prod'], $addonPath)
            ->run();

        return 0 === $consoleReturn ? self::SUCCESS : self::FAILURE;
    }
}
