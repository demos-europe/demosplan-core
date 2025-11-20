<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Db;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'dplan:migrations:cache', description: 'Clears the Doctrine cache')]
class MigrationsCacheCommand extends CoreCommand
{
    /**
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = $input->getOption('env');

        $batch = Batch::create($this->getApplication(), $output);
        $batch->add("doctrine:cache:clear-metadata --env={$env}")
            ->add("doctrine:cache:clear-query --env={$env}")
            ->add("doctrine:cache:clear-result --env={$env}");

        $batch->run();
        $allExitCodes = $batch->getAllReturnCodes();

        // Check if ANY command failed
        return in_array(Command::FAILURE, $allExitCodes) ? Command::FAILURE : Command::SUCCESS;
    }
}
