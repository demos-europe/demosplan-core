<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\Logic\DemosFilesystem;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * dplan:update.
 *
 * Update current project
 */
class CacheClearCommand extends CoreCommand
{
    final public const APCU_CLEAR_SCHEDULE_FILE = 'web/uploads/scheduled-apcu-clear';

    protected static $defaultName = 'dplan:cache:clear';
    protected static $defaultDescription = 'Clear apcu and op caches';

    public function configure(): void
    {
        $this->addOption(
            'no-app-cache',
            '',
            InputOption::VALUE_NONE,
            'If this is set, only apcu and opcache will be cleared, otherwise cache:clear will be called too'
        );
        $this->addOption('force', '', InputOption::VALUE_NONE, 'Delete cache directory directly');
    }

    /**
     * Update demosplan.
     *
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $force = $input->getOption('force');
        if ($force) {
            $output->warning('Force option set. Clearing cache directory directly');
            $this->forceClear($output);

            return Command::SUCCESS;
        }

        $output->comment('Clearing CLI APCu and OpCache and any app cache');
        try {
            // clear any app cache
            $cachePoolClearCommand = $this->getApplication()->get('cache:pool:clear');
            $cachePoolClearCommand->run(new StringInput('cache:pool:clear cache.global_clearer'), $output);

            DemosPlanTools::cacheClear();
            $output->success('Cleared CLI APCu and OpCache');
        } catch (Exception $e) {
            $output->error('Failed to clear CLI APCu and OpCache, force delete it');
            $output->error($e->getMessage());

            $this->forceClear($output);

            return Command::FAILURE;
        }

        if ('test' !== $this->getApplication()->getKernel()->getEnvironment()) {
            $this->scheduleWebApcuClear($output);
        }

        if (!$input->getOption('no-app-cache')) {
            $this->handleAppCacheClear($input, $output);
        }

        return (int) Command::SUCCESS;
    }

    private function scheduleWebApcuClear(SymfonyStyle $output): void
    {
        $output->comment('Schedule clearing of Web APCu');

        $fs = new DemosFilesystem();
        $file = DemosPlanPath::getProjectPath(self::APCU_CLEAR_SCHEDULE_FILE);

        // in case of our dev servers the file would be put into src folder,
        // but needs to be in the htdocs web/uploads folder
        if (str_contains($file, 'src/projects')) {
            $file = str_replace('src/projects', 'htdocs/projects', $file);
            $output->writeln('Recognized dev server environment. Adjust path for scheduled file');
            $output->writeln('Filepath: '.$file);
        }

        $fs->touch($file);
    }

    private function handleAppCacheClear(InputInterface $input, SymfonyStyle $output): void
    {
        $cacheClearCommand = $this->getApplication()->get('cache:clear');
        if ($input->hasOption('env')) {
            $commandWithSignature = new StringInput('cache:clear --env='.$input->getOption('env'));
        } else {
            $commandWithSignature = new StringInput('cache:clear');
        }

        $cacheClearCommand->run(new StringInput($commandWithSignature), $output);
    }

    private function forceClear(SymfonyStyle $output)
    {
        $output->warning('Removing cache directory directly as a fallback');
        $fs = new Filesystem();
        $fs->remove($this->getApplication()->getKernel()->getCacheDir());
    }
}
