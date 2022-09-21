<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Plugin;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanPluginBundle\Exception\ManagePluginException;
use demosplan\DemosPlanPluginBundle\Logic\PluginList;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PluginManageCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:plugin:manage';
    protected static $defaultDescription = 'Manage plugins for a project';
    /**
     * @var PluginList
     */
    private $pluginList;

    public function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'enable | disable')
            ->addArgument('pluginName', InputArgument::REQUIRED, 'Valid plugin name')
            ->addOption('skip-cacheClear', null, InputOption::VALUE_NONE, 'Do not clear caches after successful action');
    }

    public function __construct(ParameterBagInterface $parameterBag, PluginList $pluginList, string $name = null)
    {
        parent::__construct($parameterBag, $name);
        $this->pluginList = $pluginList;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getArgument('action');
        $pluginName = $input->getArgument('pluginName');
        $skipClearCaches = $input->getOption('skip-cacheClear');
        $cacheClearNeeded = false;
        $io = new SymfonyStyle($input, $output);

        if (!$this->pluginList->isValidPlugin($pluginName)) {
            $io->warning(sprintf('Plugin %s NOT found', $pluginName));

            return 1;
        }

        // try to enable/disable plugin
        switch ($action) {
            case 'enable':
                $io->text(sprintf('Try to enable %s', $pluginName));
                try {
                    $this->pluginList->enablePlugin($pluginName);
                    $io->success(sprintf('Plugin successfully enabled'));
                    $cacheClearNeeded = true;
                } catch (ManagePluginException $e) {
                    $io->warning($e->getMessage());
                }
                break;
            case 'disable':
                $io->text(sprintf('Try to disable %s', $pluginName));
                try {
                    $this->pluginList->disablePlugin($pluginName);
                    $io->success(sprintf('Plugin successfully disabled'));
                    $cacheClearNeeded = true;
                } catch (ManagePluginException $e) {
                    $io->warning($e->getMessage());
                }
                break;
            default:
                $io->warning(
                    sprintf('Action %s not known. Please use enable or disable', $action)
                );
                break;
        }

        // clear caches if needed this needs to be last action of the command, because
        // clear caches commands "change some class definitions, so running something after them is likely to break."
        // @see https://symfony.com/doc/2.8/console/calling_commands.html
        if (!$skipClearCaches && $cacheClearNeeded) {
            $this->clearCaches($output);
        }

        return 0;
    }

    /**
     * clear caches
     * This needs to be last action of the command, because
     * clear caches commands "change some class definitions, so running something after them is likely to break.".
     *
     * @see https://symfony.com/doc/2.8/console/calling_commands.html
     *
     * @param OutputInterface $output
     */
    protected function clearCaches($output)
    {
        $command = $this->getApplication()->find('cache:clear');

        $argumentsDev = [
            '-e'          => 'dev',
            '--no-warmup' => true,
        ];

        $devCache = new ArrayInput($argumentsDev);
        $command->run($devCache, $output);

        $command2 = $this->getApplication()->find('cache:clear');
        $argumentsProd = [
            '-e'          => 'prod',
            '--no-warmup' => true,
        ];

        $prodCache = new ArrayInput($argumentsProd);
        $command2->run($prodCache, $output);
    }
}
