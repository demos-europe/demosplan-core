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

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\ProcedurePhaseDefinitionResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\PersistResourceException;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedurePhaseDefinitionEditor;
use demosplan\DemosPlanCoreBundle\Logic\Report\ProcedurePhaseDefinitionUpdatableField;
use demosplan\DemosPlanCoreBundle\Repository\ProcedurePhaseDefinitionRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\ProcedurePhaseDefinitionResourceConfigBuilder;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\Querying\Contracts\PathException;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\CallbackAttributeSetBehavior;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;

/**
 * @template-extends DplanResourceType<ProcedurePhaseDefinition>
 */
final class ProcedurePhaseDefinitionResourceType extends DplanResourceType implements ProcedurePhaseDefinitionResourceTypeInterface
{
    public function __construct(
        private readonly ProcedurePhaseDefinitionRepository $procedurePhaseDefinitionRepository,
        private readonly ProcedurePhaseDefinitionEditor $procedurePhaseDefinitionEditor,
    ) {
    }

    public function getEntityClass(): string
    {
        return ProcedurePhaseDefinition::class;
    }

    public static function getName(): string
    {
        return 'ProcedurePhaseDefinition';
    }

    public function getTypeName(): string
    {
        return 'ProcedurePhaseDefinition';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_customer_procedure_phase_definitions');
    }

    public function isUpdateAllowed(): bool
    {
        return $this->isCreateAllowed();
    }

    /**
     * @throws PathException
     * @throws CustomerNotFoundException
     */
    protected function getAccessConditions(): array
    {
        $customerId = $this->currentCustomerService->getCurrentCustomer()->getId();

        return [
            $this->conditionFactory->propertyHasValue($customerId, ['customer', 'id']),
        ];
    }

    protected function getProperties(): ProcedurePhaseDefinitionResourceConfigBuilder
    {
        $configBuilder = $this->getConfig(ProcedurePhaseDefinitionResourceConfigBuilder::class);

        $configBuilder->id
            ->setReadableByPath()
            ->setSortable()
            ->setFilterable();

        $configBuilder->name
            ->setReadableByPath(DefaultField::YES)
            ->setSortable()
            ->setFilterable()
            ->addPathCreationBehavior()
            ->addUpdateBehavior(
                CallbackAttributeSetBehavior::createFactory(
                    [],
                    function (ProcedurePhaseDefinition $procedurePhaseDefinition, string $newName): array {
                        $oldName = $procedurePhaseDefinition->getName();
                        $customer = $procedurePhaseDefinition->getCustomer();
                        if ($newName !== $oldName && $customer instanceof CustomerInterface) {
                            $this->guardNameUnique($newName, $procedurePhaseDefinition->getAudience(), $customer);
                        }
                        $procedurePhaseDefinition->setName($newName);
                        $this->procedurePhaseDefinitionEditor->addReportEntryUpdate(
                            $procedurePhaseDefinition,
                            ProcedurePhaseDefinitionUpdatableField::NAME,
                            $oldName,
                            $newName
                        );

                        return [];
                    },
                    OptionalField::YES
                )
            );

        $configBuilder->audience
            ->setReadableByPath(DefaultField::YES)
            ->setFilterable()
            ->addPathCreationBehavior();

        // permissionSet and participationState are editable for regular phases, but fixed for the
        // configuration phase (orderInAudience 0), where only the name may be changed. Entity conditions
        // on an update behavior would be merged across all properties and block even a name-only update,
        // so the restriction is enforced per-attribute via a callback that only runs when the attribute
        // is part of the request.
        $configBuilder->permissionSet
            ->setReadableByPath(DefaultField::YES)
            ->setFilterable()
            ->addPathCreationBehavior()
            ->addUpdateBehavior(
                CallbackAttributeSetBehavior::createFactory(
                    [],
                    function (ProcedurePhaseDefinition $procedurePhaseDefinition, string $newPermissionSet): array {
                        $this->procedurePhaseDefinitionEditor->guardConfigurationPhaseNotEditable($procedurePhaseDefinition);
                        $oldPermissionSet = $procedurePhaseDefinition->getPermissionSet();
                        $procedurePhaseDefinition->setPermissionSet($newPermissionSet);
                        $this->procedurePhaseDefinitionEditor->addReportEntryUpdate(
                            $procedurePhaseDefinition,
                            ProcedurePhaseDefinitionUpdatableField::PERMISSION_SET,
                            $oldPermissionSet,
                            $newPermissionSet
                        );

                        return [];
                    },
                    OptionalField::YES
                )
            );

        $configBuilder->participationState
            ->setReadableByPath(DefaultField::YES)
            ->setFilterable()
            ->addPathCreationBehavior()
            ->addUpdateBehavior(
                CallbackAttributeSetBehavior::createFactory(
                    [],
                    function (ProcedurePhaseDefinition $procedurePhaseDefinition, ?string $newParticipationState): array {
                        $this->procedurePhaseDefinitionEditor->guardConfigurationPhaseNotEditable($procedurePhaseDefinition);
                        if (ProcedureInterface::PARTICIPATIONSTATE_PARTICIPATE_WITH_TOKEN === $newParticipationState) {
                            $tokenPermission = 'area_customer_procedure_phase_participation_token';
                            if (!$this->currentUser->hasPermission($tokenPermission)) {
                                throw AccessDeniedException::missingPermissions(null, [$tokenPermission]);
                            }
                        }
                        $oldParticipationState = $procedurePhaseDefinition->getParticipationState();
                        $procedurePhaseDefinition->setParticipationState($newParticipationState);
                        $this->procedurePhaseDefinitionEditor->addReportEntryUpdate(
                            $procedurePhaseDefinition,
                            ProcedurePhaseDefinitionUpdatableField::PARTICIPANT_STATE,
                            $oldParticipationState,
                            $newParticipationState
                        );

                        return [];
                    },
                    OptionalField::YES
                )
            );

        $configBuilder->closingPhase
            ->setReadableByPath(DefaultField::YES)
            ->setFilterable()
            ->addPathCreationBehavior(OptionalField::YES);

        $configBuilder->isDeleted
            ->setReadableByPath(DefaultField::YES)
            ->setFilterable()
            ->addUpdateBehavior(CallbackAttributeSetBehavior::createFactory(
                [],
                function (ProcedurePhaseDefinition $procedurePhaseDefinition, bool $newIsDeleted): array {
                    $this->procedurePhaseDefinitionEditor->guardConfigurationPhaseNotEditable($procedurePhaseDefinition);
                    if (!$newIsDeleted) {
                        $customer = $procedurePhaseDefinition->getCustomer();
                        if ($customer instanceof CustomerInterface) {
                            $this->guardNameUnique(
                                $procedurePhaseDefinition->getName(),
                                $procedurePhaseDefinition->getAudience(),
                                $customer
                            );
                        }
                    }
                    $this->procedurePhaseDefinitionEditor->setDeleted($procedurePhaseDefinition, $newIsDeleted);

                    return [];
                },
                OptionalField::YES
            ));

        $configBuilder->orderInAudience
            ->setReadableByPath(DefaultField::YES)
            ->setSortable();

        $configBuilder->customer
            ->setRelationshipType($this->resourceTypeStore->getCustomerResourceType())
            ->setReadableByPath()
            ->setFilterable();

        $configBuilder->addPostConstructorBehavior(
            new FixedSetBehavior(function (ProcedurePhaseDefinition $entity, EntityDataInterface $entityData): array {
                $customer = $this->currentCustomerService->getCurrentCustomer();
                $entity->setCustomer($customer);

                // Read audience from request data directly, as property behaviors (initializable)
                // run after general post-constructor behaviors, so $entity->getAudience() is not set yet.
                $audience = $entityData->getAttributes()['audience'];
                $name = $entityData->getAttributes()['name'];

                $this->guardNameUnique($name, $audience, $customer);

                $maxOrder = $this->procedurePhaseDefinitionRepository
                    ->getMaxOrderForCustomerAndAudience($customer->getId(), $audience);
                $entity->setOrderInAudience($maxOrder + 1);

                $this->getEntityManager()->persist($entity);
                $this->getEntityManager()->flush();

                return [];
            })
        );

        return $configBuilder;
    }

    /**
     * @throws PersistResourceException
     */
    private function guardNameUnique(string $name, string $audience, CustomerInterface $customer): void
    {
        if (null !== $this->procedurePhaseDefinitionRepository->findByNameAndAudienceAndCustomer($name, $audience, $customer)) {
            $this->messageBag->add('error', 'error.procedure_phase_definition.name.duplicate', ['name' => $name]);
            throw new PersistResourceException('A phase definition with this name already exists for this audience.');
        }
    }
}
