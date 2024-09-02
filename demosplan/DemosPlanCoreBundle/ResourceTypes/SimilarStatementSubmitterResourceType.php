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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\PathBuilding\End;
use EDT\Wrapping\Contracts\ContentField;
use EDT\Wrapping\CreationDataInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\AttributeConstructorBehavior;
use EDT\Wrapping\PropertyBehavior\Relationship\ToOne\ToOneRelationshipConstructorBehavior;
use Webmozart\Assert\Assert;

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
final class SimilarStatementSubmitterResourceType extends DplanResourceType
{
    public function __construct(
        private readonly ProcedureRepository $procedureRepository,
    ) {
    }

    public static function getName(): string
    {
        return 'SimilarStatementSubmitter';
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable(),
            $this->createAttribute($this->fullName)->readable()->sortable()->updatable()
                ->addConstructorBehavior(
                    AttributeConstructorBehavior::createFactory(null, OptionalField::NO, null)
                ),
            $this->createAttribute($this->city)->readable()->initializable(true)->updatable(),
            $this->createAttribute($this->streetName)->readable()->initializable(true)->updatable(),
            $this->createAttribute($this->streetNumber)->readable()->initializable(true)->updatable(),
            $this->createAttribute($this->postalCode)->readable()->initializable(true)->updatable(),
            $this->createAttribute($this->emailAddress)->readable()->initializable(true)->updatable(),
            $this->createToManyRelationship($this->similarStatements)->initializable(
                true,
                function (ProcedurePerson $submitter, array $similarStatements): array {
                    /** @var Statement $statement */
                    foreach ($similarStatements as $statement) {
                        $statement->getSimilarStatementSubmitters()->add($submitter);
                    }
                    $this->procedureRepository->persistEntities($similarStatements);

                    return [];
                }),
            // FIXME : have to check this change, there is no RequiredToOneRelationshipConstructorBehavior class that's why i used
            // the  ToOneRelationshipConstructorBehavior
            $this->createToOneRelationship($this->procedure)->addConstructorBehavior(ToOneRelationshipConstructorBehavior::createFactory(
                null,
                [],
                function (CreationDataInterface $entityData): array {
                    $currentProcedure = $this->currentProcedureService->getProcedure();
                    $toOneRelationships = $entityData->getToOneRelationships();
                    $procedureRef = $toOneRelationships[$this->procedure->getAsNamesInDotNotation()];
                    Assert::notNull($procedureRef);
                    Assert::notNull($currentProcedure);
                    $procedureId = $currentProcedure->getId();
                    Assert::notNull($procedureId);
                    if ($procedureRef != [ContentField::ID => $procedureId, ContentField::TYPE => ProcedureResourceType::getName()]) {
                        throw new InvalidArgumentException('Expected the procedure the user authorized for to be the same as the procedure the instance is to be created with.');
                    }

                    return [$currentProcedure, []];
                },
                OptionalField::NO
            )),
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

    public function isUpdateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_similar_statement_submitter');
    }

    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }

        $procedureId = $procedure->getId();

        return [$this->conditionFactory->propertyHasValue($procedureId, $this->procedure->id)];
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_similar_statement_submitter');
    }
}
