<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Db;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class RestoreCommand extends DatabaseManagementCommand
{
    protected static $defaultName = 'dplan:db:restore';
    protected static $defaultDescription = 'Restores the currently configured db from a sql file';

    public function configure(): void
    {
        parent::configure();

        $this->setHelp('For this command to work, mysqlutils need to be available');

        $this->addArgument(
            'file',
            InputArgument::OPTIONAL,
            'The sql file to restore from',
            'dump.sql'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $file = $input->getArgument('file');

        $databaseName = $this->getDatabaseName($input);
        $databaseUser = $this->getDatabaseUser($input);
        $databaseHost = $this->getDatabaseHost($input);
        $databasePassword = $this->getDatabasePassword($input);

        $cmd = sprintf(
            'mysql --user %s --host %s --password=%s %s < %s',
            $databaseUser,
            $databaseHost,
            '' === $databasePassword ? '""' : $databasePassword,
            $databaseName,
            $file
        );

        $mysql = new Process($cmd);
        $mysql->setTimeout(null);

        $output->comment("Beginning db restore of {$databaseUser}@{$databaseHost}:{$databaseName}");

        $mysql->run();

        $output->success('Done.');

        return 0;
    }
}
