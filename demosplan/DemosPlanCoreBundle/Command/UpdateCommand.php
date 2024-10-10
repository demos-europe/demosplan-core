<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use Carbon\Carbon;
use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use demosplan\DemosPlanCoreBundle\Exception\UpdateException;
use demosplan\DemosPlanCoreBundle\Logic\DemosFilesystem;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use EFrane\ConsoleAdditions\Batch\Action;
use EFrane\ConsoleAdditions\Batch\Batch;
use EFrane\ConsoleAdditions\Batch\ShellAction;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function register_shutdown_function;

/**
 * dplan:update.
 *
 * Update current project
 */
class UpdateCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:update';
    protected static $defaultDescription = 'Update dplan project';

    public function configure(): void
    {
        $this
            ->addOption('no-dependencies', '', InputOption::VALUE_NONE, 'Skip updating code and external dependencies')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Branch to check out and update')
            ->addOption('deploy', null, InputOption::VALUE_NONE, 'Prepare demosplan for deployment')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force an update (ignore update.lock)')
            ->addOption('no-dependencies', '', InputOption::VALUE_NONE, 'Skip updating external dependencies')
            ->addOption('no-git', '', InputOption::VALUE_NONE, 'Skip updating code')
            ->addOption('no-logging', null, InputOption::VALUE_NONE, 'Disable writing an output log')
            ->addOption('skip-sync', null, InputOption::VALUE_NONE, 'Disable syncing to webserver folder')
            ->setHelp(
                <<<EOT
Update demosplan. Usage:
    php app/console dplan:update       -> Dump dev and prod assets
EOT
            );
    }

    /**
     * Update demosplan.
     *
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $fs = new DemosFilesystem();

        $isDeployment = $input->getOption('deploy');
        // enforce deployment environment when deployed on production
        if (!$isDeployment && $this->parameterBag->get('prod_deployment')) {
            $isDeployment = true;
        }
        $updateLockFile = DemosPlanPath::getRootPath('update.lock');
        $env = $isDeployment ? 'prod' : 'dev';
        $rootPath = DemosPlanPath::getRootPath();
        /** @var DemosPlanKernel $kernel */
        $kernel = $this->getApplication()->getKernel();
        if (null !== $this->getApplication()
            && $kernel->isLocalContainer()) {
            $output->writeln('Will not run in your container to prevent damage.');

            return Command::FAILURE;
        }

        // setup log filename
        $logFilename = sprintf(
            '%supdate.log',
            $isDeployment ? 'deployment-' : '',
        );

        if ($this->parameterBag->get('keep_update_logfiles')) {
            $logFilename = sprintf(
                '%supdate-%s.log',
                $isDeployment ? 'deployment-' : '',
                Carbon::now()->toDateTimeLocalString()
            );
        }

        $output = $this->setupIo($input, $output, !$input->getOption('no-logging'), $logFilename);

        $this->checkUpdateLock($input->getOption('force'), $fs, $updateLockFile, $output);

        register_shutdown_function([$this, 'clearUpdateLock'], $fs, $updateLockFile);

        if (!$input->getOption('no-dependencies')) {
            Batch::create($this->getApplication(), $output)
                // reset git state, pull and checkout the desired branch
                ->addShell(['git', 'reset', '--hard', 'HEAD'], $rootPath)
                ->addShell(['git', 'pull', '--rebase', '--autostash'], $rootPath)
                ->addShell(['git', 'checkout', $input->getOption('branch')], $rootPath)
                ->addShell(['git', '--no-pager', 'log', '-1'], $rootPath)

                ->run();
        }

        if (!$input->getOption('no-dependencies')) {
            $composerCmd = ['composer', 'install', '-o'];
            if ($isDeployment) {
                $composerCmd = ['composer', 'install', '-o', '--no-dev'];
            }

            Batch::create($this->getApplication(), $output)
                // load composer dependencies
                ->addShell($composerCmd, $rootPath)
                // load yarn dependencies
                ->addShell(['yarn', 'install', '--frozen-lockfile'], $rootPath)
                ->run();
        }

        Batch::create($this->getApplication(), $output)
            // schedule web caches to be cleared, do not clear symfony caches yet, otherwise the running container gets destroyed
            ->add("dplan:cache:clear --no-app-cache --env={$env}")
            // build the frontend assets
            ->addAction($this->getFrontendAssetsAction($env))
            // migrate database
            ->add("dplan:migrate --env={$env}")
            ->run();

        // clear caches, avoid strange assetic bug(?) not being able to delete folder de~
        $this->removeCacheData($output, $fs, 'dev');
        $this->removeCacheData($output, $fs, 'prod');

        $fs->remove(DemosPlanPath::getConfigPath('config_dev_container'));
        $fs->remove(DemosPlanPath::getConfigPath('config_dev_container_services'));

        if ($isDeployment) {
            // delete files not suitable for deployment
            $output->writeln('Delete files not suitable for deployment');
            $fs->remove(DemosPlanPath::getProjectPath('web/app_dev.php'));

            // special case:
            // jms/serializer has published its documentation under a CC BY-NC-ND 3.0 license. To avoid problems with
            // the non-commercial license, the documentation should be removed on packaging, which is allowed as
            // jms/serializer itself uses MIT license, which allows modification
            $jmsSerializerDocpath = 'vendor/jms/serializer/doc';
            if ($fs->exists(DemosPlanPath::getRootPath($jmsSerializerDocpath))) {
                $fs->remove($jmsSerializerDocpath);
            }
        }
        /** @var DemosPlanKernel $kernel */
        $kernel = $this->getApplication()->getKernel();
        $bin = $kernel->getActiveProject();

        Batch::create($this->getApplication(), $output)
            // clear any caches and rebuild container before syncing files
            ->addShell(['php'."bin/$bin", 'dplan:cache:clear', '-e', 'prod'], $rootPath)
            ->run();

        // synchronize files to webserver folder, this must be a process as we don't
        // have a compiled symfony container for command lookup at this point
        if (!$input->getOption('skip-sync')) {
            Batch::create($this->getApplication(), $output)
                ->addShell(['php', "bin/$bin", 'dplan:deploy', '--strategy=sync', "--env={$env}"], $rootPath)
                ->run();
        }

        $this->clearUpdateLock($fs, $updateLockFile);

        return Command::SUCCESS;
    }

    /**
     * @throws Exception
     */
    protected function checkUpdateLock(bool $force, DemosFilesystem $fs, string $updateLockFile, SymfonyStyle $io): void
    {
        if ($force && $fs->exists($updateLockFile)) {
            $io->warning('Warning: A previous update may not have been finished, will update anyway.');
            $this->clearUpdateLock($fs, $updateLockFile);
        }

        if ($fs->exists($updateLockFile)) {
            throw UpdateException::alreadyRunning();
        }

        $fs->touch($updateLockFile);
    }

    /**
     * @param string $updateLockFile
     */
    public function clearUpdateLock(DemosFilesystem $fs, $updateLockFile): void
    {
        if ($fs->exists($updateLockFile)) {
            $fs->remove($updateLockFile);
        }
    }

    protected function removeCacheData(SymfonyStyle $output, DemosFilesystem $fs, string $cacheDir): bool
    {
        $alternativeCacheDir = substr($cacheDir, 0, -1).'~';

        try {
            $output->writeln("Delete caches {$cacheDir} and {$alternativeCacheDir} cache entirely");

            $fs->remove(DemosPlanPath::getProjectPath("app/cache/{$cacheDir}"));
            $fs->remove(DemosPlanPath::getProjectPath("app/cache/{$alternativeCacheDir}"));
        } catch (Exception) {
            $output->error("An error occured while clearing {$cacheDir} and {$alternativeCacheDir} cache.");

            return false;
        }

        return true;
    }

    private function getFrontendAssetsAction(string $env): Action
    {
        if (null === $this->getApplication()) {
            throw UpdateException::assetBuildImpossible();
        }
        /** @var DemosPlanKernel $kernel */
        $kernel = $this->getApplication()->getKernel();
        $projectName = $kernel->getActiveProject();

        $feCommand = ['./fe', 'build', $projectName];

        if ('prod' === $env) {
            $feCommand[] = '--prod';
        }

        return new ShellAction($feCommand, DemosPlanPath::getRootPath());
    }
}
