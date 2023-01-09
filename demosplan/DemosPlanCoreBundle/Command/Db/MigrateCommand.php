<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Db;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * dplan:migrate.
 *
 * Simplify running core and project migrations
 *
 * This basically does
 *
 * sf doctrine:migrate:migrate && sf doctrine:migrate:migrate -C /vendor/demosplan/DemosPlanCoreBundle/Resources/config/project_migrations.yml
 */
class MigrateCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:migrate';
    protected static $defaultDescription = 'Run core and project migrations in correct order';

    public function configure(): void
    {
        $this->addOption('db', null, InputOption::VALUE_REQUIRED, 'Use Database configuration');
    }

    /**
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = $input->getOption('env');
        $db = $input->getOption('db');

        if (null !== $db) {
            $db = '--conn='.$db;
        }


        $commands = [
            "dplan:migrations:cache --env={$env}",
            "doctrine:migrations:sync-metadata-storage {$db} --env={$env}",
        ];

        $migrationsConfigurationPath = DemosPlanPath::getProjectPath('app/config/project_migrations.yml');
        $migrationsSyncCommand = 'doctrine:migrations:sync-metadata-storage --configuration ';
        $migrationsCommand = 'doctrine:migrations:migrate --configuration ';
        $lastRollupMigration = 'app/Resources/DemosPlanCoreBundle/DoctrineMigrations/2022/09/Version20220914133419.php';

        // ensure that rollup migrations are applied before core migrations are performed
        if (file_exists(DemosPlanPath::getProjectPath($lastRollupMigration))) {
            $commands[] = $migrationsSyncCommand . $migrationsConfigurationPath . " {$db} --env={$env}";
            $commands[] = $migrationsCommand . $migrationsConfigurationPath .
                    'Application\Migrations\Version20220914133419' . " {$db} --env={$env}";

        }

        $commands[] = "doctrine:migrations:migrate {$db} --env={$env}";
        $commands[] = $migrationsSyncCommand . $migrationsConfigurationPath . " {$db} --env={$env}";
        $commands[] = $migrationsCommand . $migrationsConfigurationPath . " {$db} --env={$env}";

        $batch = Batch::create($this->getApplication(), $output);

        \collect($commands)->map(
            function (string $commandString) {
                $command = collect(sprintf(
                    'bin/%s',
                    $this->getApplication()->getKernel()->getActiveProject(),
                ));

                return $command
                    ->merge(explode(' ', $commandString))
                    // remove empty entries when no $db is given
                    ->filter()
                    ->toArray();
            }
        )->each(
            static function (array $command) use ($batch) {
                $batch->addShell($command, DemosPlanPath::getRootPath());
            }
        );

        return $batch->run();
    }
}
