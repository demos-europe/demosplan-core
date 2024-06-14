<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use demosplan\DemosPlanCoreBundle\Logic\Deployment\StrategyLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * dplan:deploy.
 *
 * Deploy current project
 */
class DeployCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:deploy';
    protected static $defaultDescription = 'Deploy dplan project';

    protected $strategyLoader;

    public function __construct(ParameterBagInterface $parameterBag, StrategyLoader $strategyLoader, ?string $name = null)
    {
        parent::__construct($parameterBag, $name);

        $this->strategyLoader = $strategyLoader;
    }

    public function configure(): void
    {
        $this->addOption(
            'strategy',
            's',
            InputOption::VALUE_REQUIRED,
            'Deployment type',
            'dataport_package'
        )->addOption(
            'reference',
            'r',
            InputOption::VALUE_OPTIONAL,
            'Git reference to create the deployment from. DOES NOT APPLY TO ALL DEPLOYMENTS!',
            null
        )->setHelp("Deploy demosplan.\nUsage:\n\tphp app/console dplan:deploy [dataport_package|sync]");
    }

    /**
     * Deploy demosplan.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $strategy = $this->strategyLoader->get($input->getOption('strategy'));
        $strategy->setApplication($this->getApplication());
        /** @var DemosPlanKernel $kernel */
        $kernel = $this->getApplication()->getKernel();
        $strategy->setProjectName($kernel->getActiveProject());
        $strategy->setGitReference($input->getOption('reference'));
        $strategy->execute($input, $output);

        return Command::SUCCESS;
    }
}
