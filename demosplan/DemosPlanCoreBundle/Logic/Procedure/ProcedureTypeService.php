<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Services\ProcedureTypeServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFieldDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use demosplan\DemosPlanCoreBundle\Exception\ExclusiveProcedureOrProcedureTypeException;
use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\ResourcePersister;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureBehaviorDefinitionRepository;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureTypeRepository;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureUiDefinitionRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementFormDefinitionRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureTypeResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementFieldDefinitionResourceType;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\QueryException;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\PropertyPathInterface;
use Exception;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;

class ProcedureTypeService extends CoreService implements ProcedureTypeServiceInterface
{
    public function __construct(
        private readonly ProcedureBehaviorDefinitionRepository $procedureBehaviorDefinitionRepository,
        private readonly ProcedureRepository $procedureRepository,
        private readonly ProcedureTypeRepository $procedureTypeRepository,
        private readonly ProcedureTypeResourceType $procedureTypeResourceType,
        private readonly ProcedureUiDefinitionRepository $procedureUiDefinitionRepository,
        private readonly ResourcePersister $resourcePersister,
        private readonly SortMethodFactory $sortMethodFactory,
        private readonly StatementFormDefinitionRepository $statementFormDefinitionRepository,
    ) {
    }

    public function deleteStatementFormDefinition(StatementFormDefinition $statementFormDefinition): void
    {
        $this->statementFormDefinitionRepository->deleteObject($statementFormDefinition);
    }

    public function deleteProcedureBehaviorDefinition(ProcedureBehaviorDefinition $procedureBehaviorDefinition,
    ): void {
        $this->procedureBehaviorDefinitionRepository->deleteObject($procedureBehaviorDefinition);
    }

    public function deleteProcedureUiDefinition(ProcedureUiDefinition $procedureUiDefinition): void
    {
        $this->procedureUiDefinitionRepository->deleteObject($procedureUiDefinition);
    }

    public function deleteProcedureType(ProcedureType $procedureType): void
    {
        $this->procedureTypeRepository->deleteObject($procedureType);
    }

    /**
     * To ensure creating a new ProcedureType is possible, the related
     * Definitions have to be persisted and flushed first.
     * This method ensures this order of creation to avoid doctrine-exception caused by flushing ProcedureType
     * before Definitions.
     *
     * @throws ExclusiveProcedureOrProcedureTypeException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function createProcedureType(
        string $name,
        string $description,
        StatementFormDefinition $statementFormDefinition,
        ProcedureBehaviorDefinition $procedureBehaviorDefinition,
        ProcedureUiDefinition $procedureUiDefinition,
    ): ProcedureType {
        $this->statementFormDefinitionRepository->addObject($statementFormDefinition);
        $this->procedureBehaviorDefinitionRepository->addObject($procedureBehaviorDefinition);
        $this->procedureUiDefinitionRepository->addObject($procedureUiDefinition);

        $newProcedureType = new ProcedureType(
            $name,
            $description,
            $statementFormDefinition,
            $procedureBehaviorDefinition,
            $procedureUiDefinition
        );

        return $this->procedureTypeRepository->addObject($newProcedureType);
    }

    public function getProcedureType(string $procedureTypeId): ProcedureType
    {
        return $this->procedureTypeRepository->find($procedureTypeId);
    }

    /**
     * ProcedureType itself will not be copied, therefore no new ProcedureType will be created.
     * The copied content of the ProcedureType is the ProcedureBehaviorDefinition, the ProcedureUiDefinition and
     * the StatementFormDefinition. These will be related to the given $targetProcedure.
     *
     * @param ProcedureType $procedureTypeToCopyContent holds the content to copy
     * @param Procedure     $targetProcedure            procedure where the copied procedureTypeContent will be
     *                                                  related to
     *
     * @throws Exception
     */
    public function copyProcedureTypeContent(
        ProcedureType $procedureTypeToCopyContent,
        Procedure $targetProcedure,
    ): Procedure {
        $this->copyProcedureBehaviorDefinition(
            $procedureTypeToCopyContent->getProcedureBehaviorDefinition(),
            $targetProcedure
        );

        $this->copyProcedureUiDefinition(
            $procedureTypeToCopyContent->getProcedureUiDefinition(),
            $targetProcedure
        );

        $this->copyStatementFormDefinition(
            $procedureTypeToCopyContent->getStatementFormDefinition(),
            $targetProcedure
        );

        $targetProcedure->setProcedureType($procedureTypeToCopyContent);

        return $targetProcedure;
    }

    public function updateProcedure(Procedure $procedure): Procedure
    {
        return $this->procedureRepository->updateObject($procedure);
    }

    /**
     * @param ProcedureUiDefinition $procedureUiDefinitionToCopy procedureUiDefinition to copy
     * @param Procedure             $targetProcedure             procedure where the copied ProcedureUiDefinition
     *                                                           will be related to
     *
     * @return ProcedureUiDefinition copied ProcedureUiDefinition
     *
     * @throws ExclusiveProcedureOrProcedureTypeException
     */
    private function copyProcedureUiDefinition(
        ProcedureUiDefinition $procedureUiDefinitionToCopy,
        Procedure $targetProcedure,
    ): ProcedureUiDefinition {
        $copiedProcedureUiDefinition = new ProcedureUiDefinition();
        $copiedProcedureUiDefinition->setProcedure($targetProcedure);
        $targetProcedure->setProcedureUiDefinition($copiedProcedureUiDefinition);

        $copiedProcedureUiDefinition->setMapHintDefault($procedureUiDefinitionToCopy->getMapHintDefault());
        $copiedProcedureUiDefinition->setStatementFormHintPersonalData(
            $procedureUiDefinitionToCopy->getStatementFormHintPersonalData()
        );
        $copiedProcedureUiDefinition->setStatementFormHintRecheck(
            $procedureUiDefinitionToCopy->getStatementFormHintRecheck()
        );
        $copiedProcedureUiDefinition->setStatementFormHintStatement(
            $procedureUiDefinitionToCopy->getStatementFormHintStatement()
        );
        $copiedProcedureUiDefinition->setStatementPublicSubmitConfirmationText(
            $procedureUiDefinitionToCopy->getStatementPublicSubmitConfirmationText()
        );

        return $copiedProcedureUiDefinition;
    }

    /**
     * @param Procedure $targetProcedure procedure to which the copied
     *                                   ProcedureBehaviorDefinition will be related to
     *
     * @return ProcedureBehaviorDefinition copied ProcedureBehaviorDefinition
     *
     * @throws ExclusiveProcedureOrProcedureTypeException
     */
    private function copyProcedureBehaviorDefinition(
        ProcedureBehaviorDefinition $definitionToCopy,
        Procedure $targetProcedure,
    ): ProcedureBehaviorDefinition {
        $copiedDefinition = new ProcedureBehaviorDefinition();
        $copiedDefinition->setProcedure($targetProcedure);
        $targetProcedure->setProcedureBehaviorDefinition($copiedDefinition);

        $copiedDefinition->setHasPriorityArea($definitionToCopy->hasPriorityArea());
        $copiedDefinition->setAllowedToEnableMap($definitionToCopy->isAllowedToEnableMap());
        $copiedDefinition->setParticipationGuestOnly($definitionToCopy->isParticipationGuestOnly());

        return $copiedDefinition;
    }

    /**
     * @param StatementFormDefinition $statementFormDefinitionToCopy StatementFormDefinition to copy
     * @param Procedure               $targetProcedure               procedure where the copied
     *                                                               StatementFormDefinition will be related to
     *
     * @return StatementFormDefinition copied statementFormDefinition
     *
     * @throws ExclusiveProcedureOrProcedureTypeException
     */
    private function copyStatementFormDefinition(
        StatementFormDefinition $statementFormDefinitionToCopy,
        Procedure $targetProcedure,
    ): StatementFormDefinition {
        $copiedStatementFromDefinition = new StatementFormDefinition();
        $copiedStatementFromDefinition->setProcedure($targetProcedure);
        $targetProcedure->setStatementFormDefinition($copiedStatementFromDefinition);

        $this->copyStatementFieldDefinitions($statementFormDefinitionToCopy, $copiedStatementFromDefinition);

        return $copiedStatementFromDefinition;
    }

    /**
     * @return StatementFormDefinition statementFormDefinition which holds the StatementFieldDefinitions of
     *                                 incoming $statementFormDefinitionToCopy
     */
    private function copyStatementFieldDefinitions(
        StatementFormDefinition $statementFormDefinitionToCopy,
        StatementFormDefinition $targetStatementFromDefinition,
    ): StatementFormDefinition {
        /** @var StatementFieldDefinition $field */
        foreach ($statementFormDefinitionToCopy->getFieldDefinitions() as $field) {
            $newFieldDefinition = $targetStatementFromDefinition->getFieldDefinitionByName($field->getName());
            if ($newFieldDefinition instanceof StatementFieldDefinition) {
                $newFieldDefinition->setEnabled($field->isEnabled());
                $newFieldDefinition->setRequired($field->isRequired());
            }
        }

        return $targetStatementFromDefinition;
    }

    public function findAll()
    {
        return $this->procedureTypeRepository->findAll();
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function updateProcedureUiDefinition(
        ProcedureUiDefinition $procedureUiDefinition,
        array $properties,
    ): void {
        $procedureUiDefinition->setMapHintDefault($properties['mapHintDefault']);
        $procedureUiDefinition->setStatementFormHintPersonalData($properties['statementFormHintPersonalData']);
        $procedureUiDefinition->setStatementFormHintRecheck($properties['statementFormHintRecheck']);
        $procedureUiDefinition->setStatementFormHintStatement($properties['statementFormHintStatement']);
        $procedureUiDefinition->setStatementPublicSubmitConfirmationText(
            $properties['statementPublicSubmitConfirmationText']
        );
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function updateProcedureBehaviorDefinition(
        ProcedureBehaviorDefinition $procedureBehaviorDefinition,
        array $properties,
    ): void {
        $procedureBehaviorDefinition->setAllowedToEnableMap($properties['allowedToEnableMap']);
        $procedureBehaviorDefinition->setHasPriorityArea($properties['hasPriorityArea']);
        $procedureBehaviorDefinition->setParticipationGuestOnly($properties['participationGuestOnly']);
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function updateStatementFieldDefinition(
        StatementFieldDefinition $statementFieldDefinition,
        array $properties,
    ): void {
        $statementFieldDefinition->setEnabled($properties['enabled']);
        $statementFieldDefinition->setRequired($properties['required']);
    }

    /**
     * Uses all names of each path to retrieve nested values from the given data. The last name of
     * each path will be used as key.
     *
     * @param array<string,mixed> $data
     *
     * @return array<string,mixed>
     */
    public function toKeyedValues(array $data, PropertyPathInterface ...$valuePaths): array
    {
        return collect($valuePaths)->mapWithKeys(static function (PropertyPathInterface $path) use ($data): array {
            $keys = $path->getAsNames();
            $value = $data;
            foreach ($keys as $key) {
                $value = $value[$key];
            }

            return [array_pop($keys) => $value];
        })->all();
    }

    /**
     * @param array[] $fieldDefinitions
     *
     * @throws NonUniqueResultException
     * @throws QueryException
     * @throws ResourceNotFoundException
     * @throws UserNotFoundException
     */
    public function calculateStatementFieldDefinitionChanges(
        array $fieldDefinitions,
        StatementFieldDefinitionResourceType $statementFieldDefinitionResourceType,
    ): array {
        $statementFieldDefinitionChanges = [];
        foreach ($fieldDefinitions as $fieldDefinition) {
            $statementFieldDefinitionProperties = $this->toKeyedValues(
                $fieldDefinition,
                $statementFieldDefinitionResourceType->enabled,
                $statementFieldDefinitionResourceType->required
            );

            $statementFieldDefinition = $statementFieldDefinitionResourceType->getEntity($fieldDefinition['id']);
            $statementFieldDefinitionChanges[] = $this->resourcePersister->updateBackingObjectWithEntity(
                $statementFieldDefinitionResourceType,
                $statementFieldDefinition,
                $statementFieldDefinitionProperties
            );
        }

        return $statementFieldDefinitionChanges;
    }

    /**
     * Adds the fieldDefinitions and participationGuestOnly (it is never send in request since it can't be
     * manually changed) of the original procedure type to the request. Also adds the original ID for easier error
     * handling in case of a redirect to the form.
     */
    public function addMissingRequestData(
        Request $request,
    ): Request {
        $params = $request->request->all();
        $originalProcedureTypeEntity = $this->procedureTypeResourceType->getEntity($params['id']);
        // Always adds participationGuestOnly since it is never send in the form
        $originalParticipationGuestOnly =
            $originalProcedureTypeEntity->getProcedureBehaviorDefinition()->isParticipationGuestOnly();

        $procedureBehaviorDefinition = ['participationGuestOnly' => $originalParticipationGuestOnly];
        try {
            // override default value if given by request
            $procedureBehaviorDefinition = $request->request->all('procedureBehaviorDefinition');
            $procedureBehaviorDefinition['participationGuestOnly'] = $originalParticipationGuestOnly;
        } catch (BadRequestException) {
            // default value already set
        }

        $request->request->set('procedureBehaviorDefinition', $procedureBehaviorDefinition);

        // Fills field definitions based on the original if they are missing.
        // Also use original values if they shouldn't be changed anyway
        if (!isset($request->request->all()['statementFormDefinition']) || !$originalParticipationGuestOnly) {
            $originalFieldDefinitions = $originalProcedureTypeEntity->getStatementFormDefinition(
            )->getFieldDefinitions();
            $fieldDefinitions = $originalFieldDefinitions->map(function (StatementFieldDefinition $item) {
                $return = [];
                $return['name'] = $item->getName();
                if ($item->isEnabled()) {
                    $return['enabled'] = '1';
                }
                if ($item->isRequired()) {
                    $return['required'] = '1';
                }

                return $return;
            })->toArray();
            $statementFormDefinition['fieldDefinitions'] = $fieldDefinitions;
            $request->request->set('statementFormDefinition', $statementFormDefinition);
        }

        return $request;
    }

    /**
     * @param array<string,mixed> $formData
     *
     * @return array<string,mixed>
     */
    public function getProcedureTypeResourceProperties(array $formData): array
    {
        $procedureTypeResourceType = $this->procedureTypeResourceType;
        $procedureTypeProperties = $this->toKeyedValues(
            $formData,
            $procedureTypeResourceType->name,
            $procedureTypeResourceType->description
        );

        $procedureUiDefinitionProperties = $this->toKeyedValues(
            $formData,
            $procedureTypeResourceType->procedureUiDefinition->mapHintDefault,
            $procedureTypeResourceType->procedureUiDefinition->statementFormHintRecheck,
            $procedureTypeResourceType->procedureUiDefinition->statementFormHintPersonalData,
            $procedureTypeResourceType->procedureUiDefinition->statementFormHintStatement,
            $procedureTypeResourceType->procedureUiDefinition->statementPublicSubmitConfirmationText
        );

        // At the time of writing, this is the only information that can be missing, because it only shows up in robob.
        // Everywhere else, this value is always false.
        if (!isset($formData['procedureBehaviorDefinition']['hasPriorityArea'])) {
            $formData['procedureBehaviorDefinition']['hasPriorityArea'] = false;
        }

        $procedureBehaviorDefinitionProperties = $this->toKeyedValues(
            $formData,
            $procedureTypeResourceType->procedureBehaviorDefinition->allowedToEnableMap,
            $procedureTypeResourceType->procedureBehaviorDefinition->hasPriorityArea,
            $procedureTypeResourceType->procedureBehaviorDefinition->participationGuestOnly
        );

        $fieldDefinitions = $this->toKeyedValues(
            $formData,
            $procedureTypeResourceType->statementFormDefinition->fieldDefinitions
        );
        $fieldDefinitions = array_pop($fieldDefinitions);

        return [
            'procedureTypeProperties'               => $procedureTypeProperties,
            'procedureUiDefinitionProperties'       => $procedureUiDefinitionProperties,
            'procedureBehaviorDefinitionProperties' => $procedureBehaviorDefinitionProperties,
            'fieldDefinitions'                      => $fieldDefinitions,
        ];
    }

    /**
     * @return array<int, ProcedureType>
     *
     * @throws PathException
     */
    public function getAllProcedureTypes(): array
    {
        $nameSorting = $this->sortMethodFactory->propertyAscending($this->procedureTypeResourceType->name);

        return $this->procedureTypeResourceType->getEntities([], [$nameSorting]);
    }

    public function getProcedureTypeByName(string $name): ?ProcedureType
    {
        return $this->procedureTypeRepository->findOneBy(['name' => $name]);
    }
}
