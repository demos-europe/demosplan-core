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
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<StatementVote>
 *
 * @property-read StatementResourceType $statement
 * @property-read UserResourceType $user
 * @property-read End $firstName
 * @property-read End $lastName
 * @property-read End $userMail
 * @property-read End $userCity
 * @property-read End $userPostcode
 * @property-read End $active
 * @property-read End $deleted
 */
final class StatementVoteResourceType extends DplanResourceType
{
    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable()->sortable()->filterable(),
            $this->createAttribute($this->firstName)->readable(),
            $this->createAttribute($this->lastName)->readable(),
            $this->createAttribute($this->userMail)->readable(),
            $this->createAttribute($this->userCity)->readable(),
            $this->createAttribute($this->userPostcode)->readable(),
            $this->createToOneRelationship($this->user)->readable(),
        ];
    }

    protected function getAccessConditions(): array
    {
        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure) {
            return [$this->conditionFactory->false()];
        }

        $procedureId = $currentProcedure->getId();

        return [
            $this->conditionFactory->propertyHasValue($procedureId, Paths::statementVote()->statement->procedure->id),
            $this->conditionFactory->propertyHasValue(false, Paths::statementVote()->deleted),
        ];
    }

    public static function getName(): string
    {
        return 'StatementVote';
    }

    public function getEntityClass(): string
    {
        return StatementVote::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('field_statement_votes') && null !== $this->currentProcedureService->getProcedure();
    }
}
