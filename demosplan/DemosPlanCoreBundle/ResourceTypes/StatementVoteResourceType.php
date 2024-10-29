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

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Repository\StatementVoteRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\StatementVoteResourceConfigBuilder;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;

/**
 * @template-extends DplanResourceType<StatementVote>
 */
final class StatementVoteResourceType extends DplanResourceType
{
    public function __construct(
        protected readonly StatementVoteRepository $statementVoteRepository,
        protected readonly StatementService $statementService,
    ) {
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

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        /**
         * Create, update or delete votes are allowed if:
         * - if the statement is manual OR
         * - if public verified has any of the mentioned values
         * */
        $voteConditions = $this->conditionFactory->anyConditionApplies(
            $this->conditionFactory->propertyHasValue(true, Paths::statementVote()->statement->manual),
            $this->conditionFactory->propertyHasAnyOfValues(
                [StatementInterface::PUBLICATION_PENDING, StatementInterface::PUBLICATION_APPROVED, StatementInterface::PUBLICATION_REJECTED],
                Paths::statementVote()->statement->publicVerified)
        );

        $statementVoteConfig = $this->getConfig(StatementVoteResourceConfigBuilder::class);

        $statementVoteConfig->id->setReadableByPath();

        $statementVoteConfig->name
            ->readable(true, fn (StatementVote $statementVote): string => $statementVote->getName())
            ->updatable([$voteConditions], function (StatementVote $statementVote, ?string $name): array {
                $statementVote->setLastName($name);

                return [];
            })
            ->initializable(true, static function (StatementVote $statementVote, ?string $name): array {
                $statementVote->setLastName($name);

                return [];
            });

        $statementVoteConfig->email
            ->setReadableByPath()
            ->addPathUpdateBehavior([$voteConditions])
            ->addPathCreationBehavior(OptionalField::YES, [$voteConditions])
            ->setAliasedPath(Paths::statementVote()->userMail);

        $statementVoteConfig->city
            ->setReadableByPath()
            ->addPathUpdateBehavior([$voteConditions])
            ->addPathCreationBehavior(OptionalField::YES, [$voteConditions])
            ->setAliasedPath(Paths::statementVote()->userCity);

        $statementVoteConfig->postcode
            ->setReadableByPath()
            ->addPathUpdateBehavior([$voteConditions])
            ->addPathCreationBehavior(OptionalField::YES, [$voteConditions])
            ->setAliasedPath(Paths::statementVote()->userPostcode);

        $statementVoteConfig->createdByCitizen
            ->setReadableByPath()
            ->addPathUpdateBehavior([$voteConditions])
            ->addPathCreationBehavior(OptionalField::NO, [$voteConditions]);

        $statementVoteConfig->organisationName
            ->setReadableByPath()
            ->addPathUpdateBehavior([$voteConditions])
            ->addPathCreationBehavior(OptionalField::YES, [$voteConditions]);

        $statementVoteConfig->departmentName
            ->setReadableByPath()
            ->addPathUpdateBehavior([$voteConditions])
            ->addPathCreationBehavior(OptionalField::YES, [$voteConditions]);

        $statementVoteConfig->user
            ->setRelationshipType($this->resourceTypeStore->getUserResourceType())
            ->setReadableByPath();

        $statementVoteConfig->statement
            ->setRelationshipType($this->resourceTypeStore->getStatementResourceType())
           // ->addConstructorBehavior(ToOneRelationshipConstructorBehavior::createFactory(null, [], null, OptionalField::NO))
            ->initializable(false, static function (StatementVote $statementVote, Statement $statement): array {
                $statementVote->setStatement($statement);

                return [];
            });

        $statementVoteConfig->addPostConstructorBehavior(new FixedSetBehavior(function (StatementVote $statementVote, EntityDataInterface $entityData): array {
            $this->statementVoteRepository->persistEntities([$statementVote]);

            return [];
        }));

        return $statementVoteConfig;
    }

    public function isCreateAllowed(): bool
    {
        return $this->isAvailable();
    }

    public function isUpdateAllowed(): bool
    {
        return $this->isAvailable();
    }

    public function isDeleteAllowed(): bool
    {
        return $this->isAvailable();
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
