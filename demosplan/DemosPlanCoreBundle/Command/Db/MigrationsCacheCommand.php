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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationsCacheCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:migrations:cache';
    protected static $defaultDescription = 'Clears the Doctrine cache';

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
