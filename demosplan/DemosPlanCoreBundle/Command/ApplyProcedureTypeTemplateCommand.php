<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementFormDefinitionInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFieldDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureTypeService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'dplan:procedure-type:apply-template', description: 'Apply predefined configuration templates to procedure types')]
class ApplyProcedureTypeTemplateCommand extends Command
{
    private const TEMPLATE_CONFIGURATIONS = [
        'ewm' => [
            'name'        => 'Einwendungsmanagement',
            'description' => 'Dieser Verfahrenstyp ist für die Auswertung vieler Einwendungen optimiert',
            'fields'      => [
                StatementFormDefinitionInterface::CITIZEN_XOR_ORGA_AND_ORGA_NAME => [
                    'enabled'  => false,
                    'required' => false,
                ],
                StatementFormDefinitionInterface::COUNTY_REFERENCE => [
                    'enabled'  => true,
                    'required' => true,
                ],
                StatementFormDefinitionInterface::NAME => [
                    'enabled'  => true,
                    'required' => true,
                ],
                StatementFormDefinitionInterface::POSTAL_AND_CITY => [
                    'enabled'  => true,
                    'required' => false,
                ],
                StatementFormDefinitionInterface::EMAIL => [
                    'enabled'  => true,
                    'required' => false,
                ],
            ],
        ],
    ];

    public function __construct(
        private readonly ProcedureTypeService $procedureTypeService,
        private readonly ProcedureTypeRepository $procedureTypeRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('template', InputArgument::REQUIRED, 'Template identifier to apply (e.g., "ewm")')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be changed without applying');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $templateId = $input->getArgument('template');
        $isDryRun = $input->getOption('dry-run');

        if (!isset(self::TEMPLATE_CONFIGURATIONS[$templateId])) {
            $io->error(sprintf('Unknown template: "%s"', $templateId));
            $io->note('Available templates: '.implode(', ', array_keys(self::TEMPLATE_CONFIGURATIONS)));

            return Command::FAILURE;
        }

        $config = self::TEMPLATE_CONFIGURATIONS[$templateId];
        $procedureType = $this->selectProcedureTypeInteractively($templateId, $config, $io);

        if (!$procedureType instanceof ProcedureType) {
            return Command::FAILURE;
        }

        return $this->executeTemplateApplication($procedureType, $config, $templateId, $isDryRun, $io);
    }

    private function executeTemplateApplication(ProcedureType $procedureType, array $config, string $templateId, bool $isDryRun, SymfonyStyle $io): int
    {
        if ($isDryRun) {
            $io->section('[DRY RUN] Template Application Preview');
        } else {
            $io->section('Applying Template: '.$templateId);
        }

        $this->displayProcedureTypeInfo($procedureType, $io);
        $changes = $this->analyzeFieldChanges($procedureType, $config['fields'], $io);

        if ([] === $changes) {
            $io->success('No field changes needed. Configuration is already up to date.');

            return Command::SUCCESS;
        }

        if ($isDryRun) {
            $io->note('No changes applied due to dry-run mode.');

            return Command::SUCCESS;
        }

        $this->applyFieldChanges($procedureType, $changes);

        try {
            $this->entityManager->flush();
            $io->success(sprintf('Template "%s" applied successfully to procedure type "%s".', $templateId, $procedureType->getName()));

            return Command::SUCCESS;
        } catch (Exception $e) {
            $io->error('Failed to save configuration changes: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function displayProcedureTypesTable(array $procedureTypes, SymfonyStyle $io): void
    {
        if ([] === $procedureTypes) {
            $io->info('No procedure types found in the database.');

            return;
        }

        $tableData = [];
        foreach ($procedureTypes as $procedureType) {
            $fieldCount = 0;
            $enabledFieldCount = 0;

            if (null !== $procedureType->getStatementFormDefinition()) {
                $fieldDefinitions = $procedureType->getStatementFormDefinition()->getFieldDefinitions();
                $fieldCount = count($fieldDefinitions);
                $enabledFieldCount = count($fieldDefinitions->filter(fn ($field) => $field->isEnabled()));
            }

            $tableData[] = [
                $procedureType->getName(),
                $procedureType->getDescription(),
                $fieldCount,
                $enabledFieldCount,
            ];
        }

        $io->table(
            ['Name', 'Description', 'Total Fields', 'Enabled Fields'],
            $tableData
        );
    }

    private function displayProcedureTypeInfo(ProcedureType $procedureType, SymfonyStyle $io): void
    {
        $io->definitionList(
            ['Name' => $procedureType->getName()],
            ['Description' => $procedureType->getDescription()]
        );
    }

    private function analyzeFieldChanges(ProcedureType $procedureType, array $fieldConfigs, SymfonyStyle $io): array
    {
        $changes = [];
        $formDefinition = $procedureType->getStatementFormDefinition();

        $io->section('Field Configuration Changes:');

        foreach ($fieldConfigs as $fieldName => $desiredConfig) {
            $fieldDefinition = $this->findFieldDefinition($formDefinition, $fieldName);

            if (!$fieldDefinition instanceof StatementFieldDefinition) {
                $changes[$fieldName] = $this->analyzeFieldCreation($fieldName, $desiredConfig, $io);
                continue;
            }

            $updateChange = $this->analyzeFieldUpdate($fieldDefinition, $fieldName, $desiredConfig, $io);
            if ($updateChange) {
                $changes[$fieldName] = $updateChange;
            }
        }

        return $changes;
    }

    private function applyFieldChanges(ProcedureType $procedureType, array $changes): void
    {
        $formDefinition = $procedureType->getStatementFormDefinition();

        foreach ($changes as $fieldName => $change) {
            if ('create' === $change['action']) {
                // Cast interface to concrete class since StatementFieldDefinition constructor requires it
                /** @var StatementFormDefinition $concreteFormDefinition */
                $concreteFormDefinition = $formDefinition;

                $fieldDefinition = new StatementFieldDefinition(
                    $fieldName,
                    $concreteFormDefinition,
                    $this->getNextOrderNumber($formDefinition),
                    $change['config']['enabled'],
                    $change['config']['required']
                );

                $this->entityManager->persist($fieldDefinition);
                $formDefinition->getFieldDefinitions()->add($fieldDefinition);
            } else {
                $fieldDefinition = $change['field'];
                $fieldDefinition->setEnabled($change['config']['enabled']);
                $fieldDefinition->setRequired($change['config']['required']);
            }
        }
    }

    private function findFieldDefinition(StatementFormDefinitionInterface $formDefinition, string $fieldName): ?StatementFieldDefinition
    {
        foreach ($formDefinition->getFieldDefinitions() as $fieldDefinition) {
            if ($fieldDefinition->getName() === $fieldName) {
                return $fieldDefinition;
            }
        }

        return null;
    }

    private function analyzeFieldCreation(string $fieldName, array $desiredConfig, SymfonyStyle $io): array
    {
        $io->text(sprintf('  ✓ %s: Will be created with enabled=%s, required=%s',
            $fieldName,
            $desiredConfig['enabled'] ? 'true' : 'false',
            $desiredConfig['required'] ? 'true' : 'false'
        ));

        return [
            'action' => 'create',
            'config' => $desiredConfig,
        ];
    }

    private function analyzeFieldUpdate(StatementFieldDefinition $fieldDefinition, string $fieldName, array $desiredConfig, SymfonyStyle $io): ?array
    {
        $currentEnabled = $fieldDefinition->isEnabled();
        $currentRequired = $fieldDefinition->isRequired();
        $desiredEnabled = $desiredConfig['enabled'];
        $desiredRequired = $desiredConfig['required'];

        if ($currentEnabled === $desiredEnabled && $currentRequired === $desiredRequired) {
            return null;
        }

        $oldState = $this->formatFieldState($currentEnabled, $currentRequired);
        $newState = $this->formatFieldState($desiredEnabled, $desiredRequired);

        $io->text(sprintf('  ✓ %s: %s (was: %s)', $fieldName, $newState, $oldState));

        return [
            'action' => 'update',
            'field'  => $fieldDefinition,
            'config' => $desiredConfig,
        ];
    }

    private function formatFieldState(bool $enabled, bool $required): string
    {
        $state = sprintf('enabled=%s', $enabled ? 'true' : 'false');
        if ($enabled) {
            $state .= sprintf(', required=%s', $required ? 'true' : 'false');
        }

        return $state;
    }

    private function getNextOrderNumber(StatementFormDefinitionInterface $formDefinition): int
    {
        $maxOrder = 0;
        foreach ($formDefinition->getFieldDefinitions() as $fieldDefinition) {
            $maxOrder = max($maxOrder, $fieldDefinition->getOrderNumber());
        }

        return $maxOrder + 1;
    }

    private function selectProcedureTypeInteractively(string $templateId, array $config, SymfonyStyle $io): ?ProcedureType
    {
        try {
            $existingTypes = $this->procedureTypeRepository->findAll();
        } catch (Exception $e) {
            $io->error('Failed to retrieve existing procedure types: '.$e->getMessage());

            return null;
        }

        // Always show existing procedure types first
        $io->section('Existing Procedure Types');
        $this->displayProcedureTypesTable($existingTypes, $io);

        if (!empty($existingTypes)) {
            $io->note(sprintf('Found %d procedure type(s).', count($existingTypes)));
        }

        $choices = [];
        $procedureTypes = [];

        // Add existing procedure types
        foreach ($existingTypes as $type) {
            $fieldCount = 0;
            $enabledCount = 0;

            if (null !== $type->getStatementFormDefinition()) {
                $fieldDefinitions = $type->getStatementFormDefinition()->getFieldDefinitions();
                $fieldCount = count($fieldDefinitions);
                $enabledCount = count($fieldDefinitions->filter(fn ($field) => $field->isEnabled()));
            }

            $choices[] = sprintf('%s (%d fields, %d enabled)', $type->getName(), $fieldCount, $enabledCount);
            $procedureTypes[] = $type;
        }

        // Add "create new" option
        $choices[] = sprintf('Create new: "%s"', $config['name']);
        $procedureTypes[] = null; // null means create new

        $question = new ChoiceQuestion(
            sprintf('Which procedure type should receive the \'%s\' template?', $templateId),
            $choices,
            0
        );

        $selectedChoice = $io->askQuestion($question);
        $selectedIndex = array_search($selectedChoice, $choices);

        if (false === $selectedIndex) {
            $io->error('Invalid selection.');

            return null;
        }

        $selectedProcedureType = $procedureTypes[$selectedIndex];

        if (null === $selectedProcedureType) {
            // Create new procedure type
            return $this->createNewProcedureType($config, $io);
        }

        return $selectedProcedureType;
    }

    private function createNewProcedureType(array $config, SymfonyStyle $io): ?ProcedureType
    {
        try {
            $statementFormDefinition = new StatementFormDefinition();
            $procedureBehaviorDefinition = new ProcedureBehaviorDefinition();
            $procedureUiDefinition = new ProcedureUiDefinition();

            $procedureType = $this->procedureTypeService->createProcedureType(
                $config['name'],
                $config['description'],
                $statementFormDefinition,
                $procedureBehaviorDefinition,
                $procedureUiDefinition
            );

            $this->entityManager->persist($procedureType);
            $this->entityManager->flush();

            $io->note(sprintf('Created new procedure type "%s"', $config['name']));

            return $procedureType;
        } catch (Exception $e) {
            $io->error('Failed to create procedure type: '.$e->getMessage());

            return null;
        }
    }
}
