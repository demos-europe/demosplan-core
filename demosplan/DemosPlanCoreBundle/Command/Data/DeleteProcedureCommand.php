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
use demosplan\DemosPlanCoreBundle\Logic\ProcedureDeleter;
use Doctrine\ORM\EntityManagerInterface;
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

    public function __construct(ParameterBagInterface $parameterBag, private readonly ProcedureDeleter $procedureDeleter, string $name = null)
    {
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

        //dd($input->getOption('dry-run'), $input->getOption('without-repopulate'));

        $procedureIds = $input->getArgument('procedureIds');
        $procedureIds = explode(",", $procedureIds);

       try {
            $retrievedProceduresIds = array_column($this->procedureDeleter->fetchFromTableByParameter(['_p_id'], '_procedure', '_p_id', $procedureIds),'_p_id');
       } catch (Exception $exception) {
            $output->error('could not retrieve procedures '.$exception);

            return Command::FAILURE;
       }

        $missedIdsArray = array_diff($procedureIds, $retrievedProceduresIds);
        if (count($missedIdsArray) !== 0) {
            $missedIds = implode(' ', $missedIdsArray);
            $output->warning("Matching procedures not found for ids $missedIds");
            //$output->confirm('do you want to continue and delete the existing procedures', true);
        }

        if (count($retrievedProceduresIds) === 0) {
            $output->info('no procedure found to delete');

            return Command::FAILURE;
        }
        $this->procedureDeleter->setProcedureIds($retrievedProceduresIds);
        $isDryRun = (bool) $input->getOption('dry-run');
        $this->procedureDeleter->setIsDryRun($isDryRun);
        $withoutRepopulate = (bool) $input->getOption('without-repopulate');
        $this->procedureDeleter->setRepopulate($withoutRepopulate);

        $output->info("Procedures ids to delete: ".implode(',', $retrievedProceduresIds));
        $output->info("Dry-run: $isDryRun");

        try {
            return $this->procedureDeleter->deleteProcedures();
        } catch (Exception $exception) {
            $output = new SymfonyStyle($input, $output);
            $output->error('Rolled back transaction '.$exception->getMessage());
            $output->error($exception->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
