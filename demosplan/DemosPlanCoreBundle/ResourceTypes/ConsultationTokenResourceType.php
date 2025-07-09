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

use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ConsultationToken;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<ConsultationToken>
 *
 * @property-read End                           $note
 * @property-read End                           $token
 * @property-read End                           $usedEmailAddress
 * @property-read EmailResourceType             $sentEmail
 * @property-read StatementResourceType         $statement
 * @property-read OriginalStatementResourceType $originalStatement
 */
final class ConsultationTokenResourceType extends DplanResourceType
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

    public function isUpdateAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_admin_consultations');
    }

    /**
     * Condition to check if the current user is in the procedure of the original statement.
     *
     * @return \EDT\DqlQuerying\Contracts\ClauseFunctionInterface|\EDT\Querying\Contracts\PathsBasedInterface
     * @throws \EDT\Querying\Contracts\PathException
     * @throws \demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException
     */
    public function isInCurrentProcedureCondition(): \EDT\DqlQuerying\Contracts\ClauseFunctionInterface|\EDT\Querying\Contracts\PathsBasedInterface
    {
        return $this->conditionFactory->propertyHasValue(
            $this->currentProcedureService->getProcedureWithCertainty('No authorization for any procedure.')->getId(),
            Paths::consultationToken()->originalStatement->procedure->id
        );
    }

    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }

        return [$this->conditionFactory->propertyHasValue(
            $procedure->getId(),
            $this->originalStatement->procedure->id
        )];
    }

    /**
     * @return array|\EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilder[]|\EDT\JsonApi\PropertyConfig\Builder\ToManyRelationshipConfigBuilder[]|\EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilder[]
     * @throws \EDT\Querying\Contracts\PathException
     * @throws \demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException
     */
    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable()->sortable()->filterable(),
            $this->createAttribute($this->note)->readable(true)->sortable()->filterable()->updatable(
                [$this->isInCurrentProcedureCondition()]),
            $this->createAttribute($this->token)->readable(true)->sortable()->filterable(),
            $this->createToOneRelationship($this->statement)->readable()->sortable()->filterable(),
            $this->createAttribute($this->usedEmailAddress)->readable()->sortable()->filterable()
                ->aliasedPath($this->sentEmail->to),
        ];
    }
}
