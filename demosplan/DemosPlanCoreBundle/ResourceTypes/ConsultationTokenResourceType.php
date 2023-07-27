<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ConsultationToken;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-implements UpdatableDqlResourceTypeInterface<ConsultationToken>
 *
 * @template-extends DplanResourceType<ConsultationToken>
 *
 * @property-read End                           $note
 * @property-read End                           $token
 * @property-read End                           $usedEmailAddress
 * @property-read EmailResourceType             $sentEmail
 * @property-read StatementResourceType         $statement
 * @property-read OriginalStatementResourceType $originalStatement
 */
final class ConsultationTokenResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface
{
    public static function getName(): string
    {
        return 'ConsultationToken';
    }

    public function getEntityClass(): string
    {
        return ConsultationToken::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_consultations');
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return $this->conditionFactory->false();
        }

        return $this->conditionFactory->propertyHasValue(
            $procedure->getId(),
            $this->originalStatement->procedure->id
        );
    }

    /**
     * @param ConsultationToken $object
     */
    public function updateObject(object $object, array $properties): ResourceChange
    {
        // Check if the current user is allowed to update the entity.
        $procedure = $this->currentProcedureService->getProcedureWithCertainty('No authorization for any procedure.');
        if ($object->getOriginalStatement()->getProcedure() !== $procedure) {
            throw new InvalidArgumentException('No authorization for the tokens procedure.');
        }

        // Update and validate the object.
        $this->resourceTypeService->updateObjectNaive($object, $properties);
        $this->resourceTypeService->validateObject($object);

        // Mark the entity as to be persisted.
        return new ResourceChange($object, $this, $properties);
    }

    public function getUpdatableProperties(object $updateTarget): array
    {
        return $this->toProperties(
            $this->note
        );
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->note)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->token)->readable(true)->sortable()->filterable(),
            $this->createToOneRelationship($this->statement)->readable()->sortable()->filterable(),
            $this->createAttribute($this->usedEmailAddress)->readable()->sortable()->filterable()
                ->aliasedPath($this->sentEmail->to),
        ];
    }
}
