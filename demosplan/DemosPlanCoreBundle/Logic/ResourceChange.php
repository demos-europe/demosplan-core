<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Controller\GenericApiController;
use demosplan\DemosPlanCoreBundle\Entity\EntityInterface;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;

/**
 * This implementation is used in the {@link GenericApiController generic API implementation}, which
 * handles as many request handling details as possible but leaves the actual implementation of
 * writing tasks and their details to manually implemented methods. These methods must return
 * {@link ResourceChange} instances, containing the affected entities, so that the generic API
 * implementation can automatically update the database and search index.
 *
 * @template T of \demosplan\DemosPlanCoreBundle\Entity\EntityInterface
 */
class ResourceChange implements EntityChangeInterface
{
    /**
     * The entity backing the resource that is the target of the request.
     *
     * @var T
     */
    private $targetEntity;

    /**
     * The entities that need to be persisted in the database to apply the changes resulting from the request.
     * Includes {@link targetEntity}.
     *
     * @var array<int,EntityInterface>
     */
    private $entitiesToPersist = [];

    /**
     * @var array<int,EntityInterface>
     */
    private $entitiesToDelete = [];

    /**
     * @var ResourceTypeInterface<T>
     */
    private $targetResourceType;

    /**
     * @var bool
     */
    private $unrequestedChangesToTargetResource = false;

    /**
     * @var array<string,mixed>
     */
    private $requestProperties;

    /**
     * @var array<class-string,array<int,string>>
     */
    private $entityIdsToUpdateInIndex = [];

    /**
     * @param object $targetEntity      the entity backing the resource that was targeted by the request
     * @param array  $requestProperties the values in the request that were specified to be set. Additional changes may
     *                                  have been made by the resource type or listeners
     */
    public function __construct(object $targetEntity, ResourceTypeInterface $targetResourceType, array $requestProperties)
    {
        $this->targetEntity = $targetEntity;
        $this->targetResourceType = $targetResourceType;
        $this->requestProperties = $requestProperties;
    }

    public function addEntityToPersist(EntityInterface $entity): void
    {
        $this->entitiesToPersist[] = $entity;
    }

    public function addEntitiesToPersist(array $entities): void
    {
        array_push($this->entitiesToPersist, ...$entities);
    }

    public function getEntitiesToPersist(): array
    {
        return $this->entitiesToPersist;
    }

    public function addEntityToDelete(EntityInterface $entity): void
    {
        $this->entitiesToDelete[] = $entity;
    }

    public function getEntitiesToDelete(): array
    {
        return $this->entitiesToDelete;
    }

    /**
     * @return T
     */
    public function getTargetResource(): object
    {
        return $this->targetEntity;
    }

    /**
     * @return ResourceTypeInterface<T>
     */
    public function getTargetResourceType(): ResourceTypeInterface
    {
        return $this->targetResourceType;
    }

    /**
     * Sets {@link unrequestedChangesToTargetResource} to true.
     *
     * Invoke this function if the {@link targetEntity} was not created exactly like specified as
     * defined in {@link getRequestProperties}.
     */
    public function setUnrequestedChangesToTargetResource(): void
    {
        $this->unrequestedChangesToTargetResource = true;
    }

    /**
     * @return bool True if the request had side effects (fields were set in the object beside the ones
     *              specified by the array returned by {@link getRequestProperties}). False otherwise.
     */
    public function getUnrequestedChangesToTargetResource(): bool
    {
        return $this->unrequestedChangesToTargetResource;
    }

    /**
     * The values to be set that were specified in the request. Does not contain additional changes
     * that were potentially made by the backend. E.g. if a resource has a lastModified
     * attribute which is automatically updated then that will **not** be in this array even if it
     * was changed due to some other changes made in the request.
     *
     * @return array<string,mixed> the properties requested to set in the {@link targetEntity}
     */
    public function getRequestProperties(): array
    {
        return $this->requestProperties;
    }

    public function addEntityToUpdateInIndex(string $class, string $entityId): void
    {
        $this->entityIdsToUpdateInIndex[$class][] = $entityId;
    }

    public function getEntityIdsToUpdateInIndex(): array
    {
        return $this->entityIdsToUpdateInIndex;
    }
}
