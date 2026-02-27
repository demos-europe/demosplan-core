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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\ProcedurePhaseDefinitionResourceConfigBuilder;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;

/**
 * @template-extends DplanResourceType<ProcedurePhaseDefinition>
 */
final class ProcedurePhaseDefinitionResourceType extends DplanResourceType
{
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
        return $this->currentUser->hasPermission('area_customer_procedure_phase_definitions');
    }

    public function isGetAllowed(): bool
    {
        return true;
    }

    public function isListAllowed(): bool
    {
        return true;
    }

    public function isCreateAllowed(): bool
    {
        return true;
    }

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
            ->initializable();

        $configBuilder->audience
            ->setReadableByPath()
            ->setFilterable()
            ->initializable();

        $configBuilder->permissionSet
            ->setReadableByPath()
            ->setFilterable()
            ->initializable();

        $configBuilder->participationState
            ->setReadableByPath()
            ->setFilterable()
            ->initializable();

        $configBuilder->orderInAudience
            ->setReadableByPath()
            ->setSortable()
            ->initializable();

        $configBuilder->customer
            ->setRelationshipType($this->resourceTypeStore->getCustomerResourceType())
            ->setReadableByPath()
            ->setFilterable();

        $configBuilder->addPostConstructorBehavior(
            new FixedSetBehavior(function (ProcedurePhaseDefinition $entity): array {
                $entity->setCustomer($this->currentCustomerService->getCurrentCustomer());
                $this->getEntityManager()->persist($entity);
                $this->getEntityManager()->flush();

                return [];
            })
        );

        return $configBuilder;
    }
}
