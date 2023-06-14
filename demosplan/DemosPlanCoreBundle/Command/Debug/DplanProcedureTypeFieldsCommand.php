<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Debug;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureTypeService;
use Exception;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DplanProcedureTypeFieldsCommand extends CoreCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'dplan:debug:procedure-type-fields';
    protected static $defaultDescription = 'Shows info regarding the fields for the different ProcedureTypes';

    /**
     * @var ProcedureTypeService
     */
    private $procedureTypeService;

    public function __construct(
        ProcedureTypeService $procedureTypeService,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);

        $this->procedureTypeService = $procedureTypeService;
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'Procedure Type Id',
                InputArgument::OPTIONAL,
                'Id of a ProcedureType so only its fields will be shown. If none, all fields for all Procedure Types will be shown'
            )
            ->addOption(
                'enabled',
                'E',
                InputOption::VALUE_OPTIONAL,
                'Shows only enabled fields for the Procedure Type (default).',
                false
            )
            ->addOption(
                'all',
                'A',
                InputOption::VALUE_OPTIONAL,
                'Shows all fields, including those which are disabled for the Procedure Type.',
                false
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $procedureTypeId = $input->getArgument('Procedure Type Id');
            if (null !== $procedureTypeId) {
                $procedureType = $this->procedureTypeService->getProcedureType($procedureTypeId);
                $this->printProcedureTypeInfo($input, $output, $procedureType);
            } else {
                $this->printProcedureTypesInfo($input, $output, $this->procedureTypeService->findAll());
            }
        } catch (Exception $e) {
            return -1;
        }

        return 0;
    }

    /**
     * @param array<int, ProcedureType> $procedureTypes
     */
    private function printProcedureTypesInfo(
        InputInterface $input,
        OutputInterface $output,
        array $procedureTypes
    ): void {
        foreach ($procedureTypes as $procedureType) {
            $this->printProcedureTypeInfo($input, $output, $procedureType);
        }
    }

    private function printProcedureTypeInfo(
        InputInterface $input,
        OutputInterface $output,
        ProcedureType $procedureType
    ) {
        if (false === $input->getOption('all')) {
            $this->printEnabledFields($output, $procedureType);
        } else {
            $this->printAllFields($output, $procedureType);
        }
    }

    private function printAllFields(OutputInterface $output, ProcedureType $procedureType): void
    {
        $this->printHeader($output, $procedureType, 'all fields');

        $table = new Table($output);
        $table->setHeaders(['Order', 'Field Name', 'Required', 'Enabled']);

        $fields = $procedureType->getStatementFormDefinition()->getFieldDefinitions();
        foreach ($fields as $field) {
            $table->addRow(
                [
                   $field->getOrderNumber(),
                   $field->getName(),
                   $field->isRequired(),
                   $field->isEnabled(),
                ]
            );
        }

        $table->render();
    }

    private function printEnabledFields(OutputInterface $output, ProcedureType $procedureType): void
    {
        $this->printHeader($output, $procedureType, 'only enabled fields');

        $table = new Table($output);
        $table->setHeaders(['Order', 'Field Name', 'Required']);

        $fields = $procedureType->getStatementFormDefinition()->getEnabledFieldDefinitions();
        foreach ($fields as $field) {
            $table->addRow(
                [
                    $field->getOrderNumber(),
                    $field->getName(),
                    $field->isRequired(),
                ]
            );
        }

        $table->render();
    }

    private function printHeader(OutputInterface $output, ProcedureType $procedureType, string $note): void
    {
        $name = $procedureType->getName();
        $id = $procedureType->getId();
        $output->writeln("\n<fg=green;options=bold>$name#$id</> ($note)");
    }
}
