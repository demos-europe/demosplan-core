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

/**
 * dplan:migrations:diff.
 *
 * Creates doctrine migration diffs with pruned cache
 */
class MigrationsDiffCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:migrations:diff';
    protected static $defaultDescription = 'Creates doctrine diffs with pruned cache';

    /**
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = $input->getOption('env');

        return Batch::create($this->getApplication(), $output)
            ->add("dplan:migrate --env={$env}")
            ->add("dplan:migrations:cache --env={$env}")
            ->add("doctrine:migrations:diff --env={$env}")
            ->run();
    }
}
