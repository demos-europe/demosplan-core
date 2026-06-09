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

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\ProcedurePhaseDefinitionResourceTypeInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Report\ProcedurePhaseDefinitionReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ProcedurePhaseDefinitionUpdatableField;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
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
        private readonly ProcedurePhaseDefinitionReportEntryFactory $procedurePhaseDefinitionReportEntryFactory,
        private readonly ReportService $reportService,
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
                        $procedurePhaseDefinition->setName($newName);
                        $this->addReportEntryUpdate(
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
                        $this->guardConfigurationPhaseNotEditable($procedurePhaseDefinition);
                        $oldPermissionSet = $procedurePhaseDefinition->getPermissionSet();
                        $procedurePhaseDefinition->setPermissionSet($this->resolvePermissionSet($newPermissionSet));
                        $this->addReportEntryUpdate(
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
                        $this->guardConfigurationPhaseNotEditable($procedurePhaseDefinition);
                        $oldParticipationState = $procedurePhaseDefinition->getParticipationState();
                        $procedurePhaseDefinition->setParticipationState(
                            $this->resolveParticipationState($newParticipationState)
                        );
                        $this->addReportEntryUpdate(
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
     * Rejects any attempt to set a field that is fixed for the configuration phase.
     */
    private function guardConfigurationPhaseNotEditable(ProcedurePhaseDefinition $phaseDefinition): void
    {
        if ($phaseDefinition->isConfigurationPhase()) {
            throw new BadRequestException('Only the name of the configuration phase can be changed; permissionSet and participationState are fixed.');
        }
    }

    /**
     * Validates the requested participation state. Allowed values are null,
     * {@see ProcedureInterface::PARTICIPATIONSTATE_FINISHED} and
     * {@see ProcedureInterface::PARTICIPATIONSTATE_PARTICIPATE_WITH_TOKEN}; the latter additionally
     * requires the 'area_customer_procedure_phase_participation_token' permission.
     */
    private function resolveParticipationState(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (ProcedureInterface::PARTICIPATIONSTATE_FINISHED === $value) {
            return ProcedureInterface::PARTICIPATIONSTATE_FINISHED;
        }

        if (ProcedureInterface::PARTICIPATIONSTATE_PARTICIPATE_WITH_TOKEN === $value) {
            $tokenPermission = 'area_customer_procedure_phase_participation_token';
            if (!$this->currentUser->hasPermission($tokenPermission)) {
                throw AccessDeniedException::missingPermissions(null, [$tokenPermission]);
            }

            return ProcedureInterface::PARTICIPATIONSTATE_PARTICIPATE_WITH_TOKEN;
        }

        throw new BadRequestException(sprintf('Invalid participationState; allowed values are null, "%s" or "%s".', ProcedureInterface::PARTICIPATIONSTATE_FINISHED, ProcedureInterface::PARTICIPATIONSTATE_PARTICIPATE_WITH_TOKEN));
    }

    /**
     * Validates the requested permission set. Allowed values are
     * {@see ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_HIDDEN},
     * {@see ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_READ} and
     * {@see ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_WRITE}.
     */
    private function resolvePermissionSet(mixed $value): string
    {
        $allowed = [
            ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_HIDDEN,
            ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_READ,
            ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_WRITE,
        ];

        if (in_array($value, $allowed, true)) {
            return $value;
        }

        throw new BadRequestException(sprintf('Invalid permissionSet; allowed values are "%s", "%s" or "%s".', ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_HIDDEN, ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_READ, ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_WRITE));
    }

    /**
     * @throws JsonException
     */
    private function addReportEntryUpdate(
        ProcedurePhaseDefinition $procedurePhaseDefinition,
        ProcedurePhaseDefinitionUpdatableField $field,
        mixed $oldValue,
        mixed $newValue
    ): void {
        if ($oldValue !== $newValue) {
            $reportEntry = $this->procedurePhaseDefinitionReportEntryFactory->createProcedurePhaseDefinitionUpdateEntry(
                $procedurePhaseDefinition,
                $field,
                $oldValue,
                $newValue,
            );
            $this->reportService->persistAndFlushReportEntry($reportEntry);
        }
    }
}
