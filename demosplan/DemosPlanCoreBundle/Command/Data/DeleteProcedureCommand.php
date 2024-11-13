<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureDeleter;
use demosplan\DemosPlanCoreBundle\Services\Queries\SqlQueriesService;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DeleteProcedureCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:procedure:delete';
    protected static $defaultDescription = 'Deletes a procedure including all related content like statements, tags, News, etc.';

    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly ProcedureDeleter $procedureDeleter,
        private readonly SqlQueriesService $queriesService,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }

    public function configure(): void
    {
        $this->addArgument(
            'procedureIds',
            InputArgument::REQUIRED,
            'The IDs of the procedures you want to delete.'
        );

        $this->addOption(
            'dry-run',
            '',
            InputOption::VALUE_NONE,
            'Initiates a dry run with verbose output to see what would happen.'
        );

        $this->addOption(
            'without-repopulate',
            'wrp',
            InputOption::VALUE_NONE,
            'Ignores repopulating the ES. This should only be used for debugging purposes!',
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $isDryRun = (bool) $input->getOption('dry-run');
        $withoutRepopulate = (bool) $input->getOption('without-repopulate');
        $procedureIds = $input->getArgument('procedureIds');
        $procedureIds = explode(',', $procedureIds);
        try {
            $retrievedProceduresIds = array_column(
                $this->queriesService->fetchFromTableByParameter(['_p_id'], '_procedure', '_p_id', $procedureIds),
                '_p_id'
            );
        } catch (Exception $exception) {
            $output->error('could not retrieve procedure(s) '.$exception);

            return Command::FAILURE;
        }

        $missedIdsArray = array_diff($procedureIds, $retrievedProceduresIds);
        if (0 !== count($missedIdsArray)) {
            $missedIdsString = implode(' ', $missedIdsArray);
            $output->warning("Matching procedure(s) not found for id(s) $missedIdsString");
        }

        if (0 === count($retrievedProceduresIds)) {
            $output->info('no procedure(s) found to delete');

            return Command::FAILURE;
        }

        try {
            $output->info('Procedures id(s) to delete: '.implode(',', $retrievedProceduresIds));
            $output->info($isDryRun ? 'Dry-run: true' : 'Dry-run: false');

            $this->procedureDeleter->beginTransactionAndDisableForeignKeyChecks();
            $this->procedureDeleter->deleteProcedures($retrievedProceduresIds, $isDryRun);
            $this->procedureDeleter->commitTransactionAndEnableForeignKeyChecks();
        } catch (Exception $exception) {
            // rollback all changes
            $this->queriesService->rollbackTransaction();
            $output->error('Rolled back transaction '.$exception->getMessage());
            $output->error($exception->getTraceAsString());

            return Command::FAILURE;
        }
        try {
            // repopulate Elasticsearch
            if (!$isDryRun && !$withoutRepopulate) {
                $output->info('Repopulate Elasticsearch');
                $this->repopulateElasticsearch($output);
            }
        } catch (Exception $exception) {
            $output->error('An Error occurred repopulating Elasticsearch: '.$exception->getMessage());
            $output->error($exception->getTraceAsString());

            return Command::FAILURE;
        }

        $output->info('procedure(s) with id(s) '.implode(',', $retrievedProceduresIds).' are deleted successfully');

        return Command::SUCCESS;
    }

    /**
     * @throws Exception
     */
    private function repopulateElasticsearch(OutputInterface $output): void
    {
        $env = $this->parameterBag->get('kernel.environment');
        $output->writeln("Repopulating ES with env: $env");

        $repopulateEsCommand = 'dev' === $env ? 'dplan:elasticsearch:populate' : 'dplan:elasticsearch:populate -e prod --no-debug';
        Batch::create($this->getApplication(), $output)
            ->add($repopulateEsCommand)
            ->run();
    }
}
