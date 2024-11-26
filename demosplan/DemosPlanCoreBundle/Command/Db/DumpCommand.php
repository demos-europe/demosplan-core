<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Db;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class DumpCommand extends DatabaseManagementCommand
{
    protected static $defaultName = 'dplan:db:dump';
    protected static $defaultDescription = 'Dump the currently configured db into a sql file';

    public function configure(): void
    {
        parent::configure();

        $this->setHelp('For this command to work, mysqlutils need to be available');

        $this->addArgument(
            'file',
            InputArgument::OPTIONAL,
            'The sql file to dump to',
            'dump.sql'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $file = $input->getArgument('file');

        // local file only, no need for flysystem
        $fs = new Filesystem();

        try {
            $fs->touch($file);
        } catch (Exception) {
            $output->error("Cannot write to {$file}");

            return Command::FAILURE;
        }

        $databaseName = $this->getDatabaseName($input);
        $databaseUser = $this->getDatabaseUser($input);
        $databaseHost = $this->getDatabaseHost($input);
        $databasePassword = $this->getDatabasePassword($input);

        $mysqldump = new Process([
            'mysqldump',
            '--no-create-db',
            '--databases',
            $databaseName,
            '--user',
            $databaseUser,
            '--host',
            $databaseHost,
            sprintf('--password=%s', '' === $databasePassword ? '""' : $databasePassword),
        ]);
        $mysqldump->setTimeout(null);

        $output->comment("Beginning db dump of {$databaseUser}@{$databaseHost}:{$databaseName}");

        $mysqldump->run();

        $sql = $mysqldump->getOutput();
        $sql = preg_replace('/USE `.+`;/', '', $sql);

        $fs->dumpFile($file, $sql);

        $output->success('Done.');

        return Command::SUCCESS;
    }
}
