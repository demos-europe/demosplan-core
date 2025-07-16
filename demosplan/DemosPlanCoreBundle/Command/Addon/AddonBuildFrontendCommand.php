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
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AddonBuildFrontendCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:addon:build-frontend';
    protected static $defaultDescription = 'Build frontend assets for an addon';

    public function __construct(private readonly AddonRegistry $registry, ParameterBagInterface $parameterBag, ?string $name = null)
    {
        parent::__construct($parameterBag, $name);
    }

    protected function configure()
    {
        $this->addArgument('addon-name', InputArgument::OPTIONAL, 'Addon name, du\'h.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);
        $addonName = $input->getArgument('addon-name');
        $env = $input->getOption('env');

        if (null === $addonName) {
            $enabledAddons = $this->registry->getEnabledAddons();
            if (0 === count($enabledAddons)) {
                $output->warning('No addons enabled, nothing to do.');

                return self::SUCCESS;
            }
            $question = new ChoiceQuestion('Which addon do you want to build the assets for? ', array_keys($enabledAddons));
            /** @var QuestionHelper $questionHelper */
            $questionHelper = $this->getHelper('question');
            $addonName = $questionHelper->ask($input, $output, $question);
        }

        $output->writeln("Building frontend assets for {$addonName}");

        $addonInfo = $this->registry[$addonName];

        $addonPath = DemosPlanPath::getRootPath($addonInfo->getInstallPath());
        $consoleReturn = Batch::create($this->getApplication(), $output)
            ->addShell(['yarn', 'install', '--immutable'], $addonPath)
            ->addShell(['yarn', $env], $addonPath)
            ->run();

        return 0 === $consoleReturn ? self::SUCCESS : self::FAILURE;
    }
}
