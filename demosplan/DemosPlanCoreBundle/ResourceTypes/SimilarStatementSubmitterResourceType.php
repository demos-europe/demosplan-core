<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use DemosEurope\DemosplanAddon\Contracts\ResourceType\CreatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends DplanResourceType<ProcedurePerson>
 *
 * @property-read End $fullName
 * @property-read End $city
 * @property-read End $postalCode
 * @property-read End $streetName
 * @property-read End $streetNumber
 * @property-read End $emailAddress
 * @property-read StatementResourceType $similarStatements
 * @property-read ProcedureResourceType $procedure
 */
final class SimilarStatementSubmitterResourceType extends DplanResourceType implements CreatableDqlResourceTypeInterface, UpdatableDqlResourceTypeInterface
{
    /**
     * @var StatementService
     */
    private $statementService;

    public function __construct(StatementService $statementService)
    {
        $this->statementService = $statementService;
    }

    public function createObject(array $properties): ResourceChange
    {
        $inputProcedure = $properties[$this->procedure->getAsNamesInDotNotation()];
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure || !$inputProcedure instanceof Procedure || $inputProcedure->getId() !== $procedure->getId()) {
            throw new InvalidArgumentException('Expected the procedure the user authorized for to be the same as the procedure the instance is to be created with.');
        }

        $change = $this->statementService->createPersonAndAddToStatementWithResourceType($properties);
        $this->resourceTypeService->validateObject($change->getTargetResource());

        return $change;
    }

    public static function getName(): string
    {
        return 'SimilarStatementSubmitter';
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true),
            $this->createAttribute($this->fullName)->readable()->sortable()->initializable(),
            $this->createAttribute($this->city)->readable()->initializable(true),
            $this->createAttribute($this->streetName)->readable()->initializable(true),
            $this->createAttribute($this->streetNumber)->readable()->initializable(true),
            $this->createAttribute($this->postalCode)->readable()->initializable(true),
            $this->createAttribute($this->emailAddress)->readable()->initializable(true),
            $this->createAttribute($this->similarStatements)->initializable(true),
            $this->createAttribute($this->procedure)->initializable(),
        ];
    }

    public function getEntityClass(): string
    {
        return ProcedurePerson::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('feature_similar_statement_submitter');
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return $this->conditionFactory->false();
        }

        $procedureId = $procedure->getId();

        return $this->conditionFactory->propertyHasValue($procedureId, $this->procedure->id);
    }

    /**
     * @param ProcedurePerson $object
     */
    public function updateObject(object $object, array $properties): ResourceChange
    {
        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->fullName, [$object, 'setFullName']);
        $this->statementService->updatePersonEditableProperties($updater, $object);

        $this->resourceTypeService->validateObject($object);

        return new ResourceChange($object, $this, $properties);
    }

    public function getUpdatableProperties(object $updateTarget): array
    {
        return $this->toProperties(
            $this->fullName,
            $this->city,
            $this->streetName,
            $this->streetNumber,
            $this->postalCode,
            $this->emailAddress,
        );
    }

    public function isCreatable(): bool
    {
        return true;
    }
}
