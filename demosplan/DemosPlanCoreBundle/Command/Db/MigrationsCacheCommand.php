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

        return Batch::create($this->getApplication(), $output)
            ->add("doctrine:cache:clear-metadata --env={$env}")
            ->add("doctrine:cache:clear-query --env={$env}")
            ->add("doctrine:cache:clear-result --env={$env}")
            ->run();
    }
}
