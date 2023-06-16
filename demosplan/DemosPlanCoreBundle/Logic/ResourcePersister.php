<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertyUpdateAccessException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\QueryException;

class ResourcePersister extends CoreService
{
    public function __construct(private readonly EntityFetcher $entityFetcher, private readonly RepositoryHelper $repositoryHelper, private readonly ResourceTypeService $resourceTypeService)
    {
    }

    /**
     * @param array<string,mixed> $properties
     *
     * @throws ResourceNotFoundException
     * @throws NonUniqueResultException
     * @throws QueryException
     * @throws UserNotFoundException
     * @throws PropertyUpdateAccessException
     */
    public function updateBackingObject(
        UpdatableDqlResourceTypeInterface $resourceType,
        string $id,
        array $properties
    ): ResourceChange {
        $entity = $this->entityFetcher->getEntityAsUpdateTarget($resourceType, $id);

        return $this->updateBackingObjectWithEntity($resourceType, $entity, $properties);
    }

    /**
     * @template T
     *
     * @param UpdatableDqlResourceTypeInterface<T> $resourceType
     * @param T                                    $entity
     * @param array<string, mixed>                 $properties
     *
     * @throws PropertyUpdateAccessException
     */
    public function updateBackingObjectWithEntity(
        UpdatableDqlResourceTypeInterface $resourceType,
        object $entity,
        array $properties
    ): ResourceChange {
        $allowedProperties = $resourceType->getUpdatableProperties($entity);
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
        $entitiesToPersist = array_merge(...array_map(static fn(ResourceChange $resourceChanges) => $resourceChanges->getEntitiesToPersist(), $resourceChanges));
        $entitiesToDelete = array_merge(...array_map(static fn(ResourceChange $resourceChanges) => $resourceChanges->getEntitiesToDelete(), $resourceChanges));

        // We use the repository of the resource type for all resource types as it doesn't matter
        // which one we use and can wrap all changes in a single transaction this way.
        $repository = $this->repositoryHelper->getRepository($firstResourceChange->getTargetResourceType()->getEntityClass());
        $repository->persistAndDelete($entitiesToPersist, $entitiesToDelete);
    }
}
