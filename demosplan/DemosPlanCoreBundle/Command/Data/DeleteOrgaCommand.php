<?php

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureDeleter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\DBAL\Exception;

class DeleteOrgaCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:orga:delete';
    protected static $defaultDescription = 'Deletes a organisation including all related content like procedures, statements, tags, etc.';

    public function __construct(EntityManagerInterface $em, ParameterBagInterface $parameterBag, private readonly ProcedureDeleter $procedureDeleter, string $name = null)
    {
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

        $orgaIds = $input->getArgument('orgaIds');
        try {
            $orgasProceduresIds = $this->procedureDeleter->fetchFromTableByParameter(['_p_id'], '_procedure', '_o_id', explode(",", $orgaIds));
        } catch (Exception $e) {
            $output->error("could not retrieve procedures Ids");
        }

        //$this->procedureDeleter->setProcedureIds($orgasProceduresIds);
        $isDryRun = (bool) $input->getOption('dry-run');
        //$this->procedureDeleter->setIsDryRun($isDryRun);
        $withoutRepopulate = (bool) $input->getOption('without-repopulate');
        //$this->procedureDeleter->setRepopulate($withoutRepopulate);

        $output->info("Organisations ids to delete: $orgasProceduresIds");
        $output->info("Dry-run: $isDryRun");

        //return $this->procedureDeleter->deleteProcedures();
        return 1;
    }
}
