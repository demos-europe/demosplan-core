<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFieldDefinition;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertyUpdateAccessException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureBehaviorDefinitionResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureTypeResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureUiDefinitionResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementFieldDefinitionResourceType;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

class ResourcePersister extends CoreService
{
    public function __construct(
        private readonly RepositoryHelper $repositoryHelper,
        private readonly ResourceTypeService $resourceTypeService
    ) {
    }

    /**
     * @template T of ProcedureType|ProcedureUiDefinition|ProcedureBehaviorDefinition|StatementFieldDefinition
     *
     * @param (ProcedureTypeResourceType|ProcedureUiDefinitionResourceType|ProcedureBehaviorDefinitionResourceType|StatementFieldDefinitionResourceType)&DplanResourceType<T> $resourceType
     * @param T                                                                                                                                                               $entity
     * @param array<string, mixed>                                                                                                                                            $properties
     *
     * @throws PropertyUpdateAccessException
     */
    public function updateBackingObjectWithEntity(
        ProcedureTypeResourceType|ProcedureUiDefinitionResourceType|ProcedureBehaviorDefinitionResourceType|StatementFieldDefinitionResourceType $resourceType,
        ProcedureType|ProcedureUiDefinition|ProcedureBehaviorDefinition|StatementFieldDefinition $entity,
        array $properties
    ): ResourceChange {
        $allowedProperties = $resourceType->getUpdatableProperties();
        if ([] === $allowedProperties) {
            throw new BadRequestException("User is not allowed to update resources of type {$resourceType::getName()}.");
        }
        $this->resourceTypeService->checkWriteAccess($resourceType, $properties, $allowedProperties);

        return $resourceType->updateObject($entity, $properties);
    }

    /**
     * @param array<int,ResourceChange> $resourceChanges
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function persistResourceChanges(array $resourceChanges): void
    {
        if (0 === count($resourceChanges)) {
            return;
        }
        /** @var ResourceChange $firstResourceChange */
        $firstResourceChange = $resourceChanges[0];
        $entitiesToPersist = array_merge(...array_map(static fn (ResourceChange $resourceChanges) => $resourceChanges->getEntitiesToPersist(), $resourceChanges));
        $entitiesToDelete = array_merge(...array_map(static fn (ResourceChange $resourceChanges) => $resourceChanges->getEntitiesToDelete(), $resourceChanges));

        // We use the repository of the resource type for all resource types as it doesn't matter
        // which one we use and can wrap all changes in a single transaction this way.
        $repository = $this->repositoryHelper->getRepository($firstResourceChange->getTargetResourceType()->getEntityClass());
        $repository->persistAndDelete($entitiesToPersist, $entitiesToDelete);
    }
}
