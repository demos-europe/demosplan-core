<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Logic\Orga\OrgaDeleter;
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

class DeleteOrgaCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:organisation:delete';
    protected static $defaultDescription = 'Deletes an organisation including all related content like procedure, statements, tags, News, etc.';

    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly OrgaDeleter $orgaDeleter,
        private readonly SqlQueriesService $queriesService,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }

    public function configure(): void
    {
        $this->addArgument(
            'orgaIds',
            InputArgument::REQUIRED,
            'The IDs of the organisations you want to delete.'
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
        $orgaIds = $input->getArgument('orgaIds');
        $orgaIds = explode(',', $orgaIds);
        try {
            $retrievedOrgaIds = array_column(
                $this->queriesService->fetchFromTableByParameter(['_o_id'], '_orga', '_o_id', $orgaIds),
                '_o_id'
            );
        } catch (Exception $exception) {
            $output->error('could not retrieve organisation(s) '.$exception);

            return Command::FAILURE;
        }

        $missedIdsArray = array_diff($orgaIds, $retrievedOrgaIds);
        if (0 !== count($missedIdsArray)) {
            $missedIdsString = implode(' ', $missedIdsArray);
            $output->warning("Matching organisation(s) not found for id(s) $missedIdsString");
        }

        if (0 === count($retrievedOrgaIds)) {
            $output->info('no organisation(s) found to delete');

            return Command::FAILURE;
        }

        try {
            $output->info('Organisations id(s) to delete: '.implode(',', $retrievedOrgaIds));
            $output->info("Dry-run: $isDryRun");

            $this->orgaDeleter->beginTransactionAndDisableForeignKeyChecks();
            $this->orgaDeleter->deleteOrganisations($retrievedOrgaIds, $isDryRun);
            $this->orgaDeleter->commitTransactionAndEnableForeignKeyChecks();
        } catch (Exception $exception) {
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

        $output->info('orga(s) with id(s) '.implode(',', $retrievedOrgaIds).' are deleted successfully');

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
