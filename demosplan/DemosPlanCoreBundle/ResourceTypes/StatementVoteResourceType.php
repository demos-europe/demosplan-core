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
 * @template-extends DplanResourceType<StatementVote>
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
        $statementVoteConfig->firstname->setReadableByPath()->setAliasedPath(Paths::statementVote()->firstName);
        $statementVoteConfig->lastname->setReadableByPath()->setAliasedPath(Paths::statementVote()->lastName);
        $statementVoteConfig->email->setReadableByPath()->setAliasedPath(Paths::statementVote()->userMail);
        $statementVoteConfig->city->setReadableByPath()->setAliasedPath(Paths::statementVote()->userCity);
        $statementVoteConfig->postcode->setReadableByPath()->setAliasedPath(Paths::statementVote()->userPostcode);
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
