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
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\StatementVoteResourceConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
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

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $statementVoteConfig = $this->getConfig(StatementVoteResourceConfigBuilder::class);

        $statementVoteConfig->id->setReadableByPath();
        $statementVoteConfig->firstName->setReadableByPath();
        $statementVoteConfig->lastName->setReadableByPath();
        $statementVoteConfig->userMail->setReadableByPath();
        $statementVoteConfig->userCity->setReadableByPath();
        $statementVoteConfig->userPostcode->setReadableByPath();
        $statementVoteConfig->user->setRelationshipType($this->resourceTypeStore->getUserResourceType())
        ->setReadableByPath();

        return $statementVoteConfig;

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

}
