<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\InputHandling\RepositoryInterface;
use EDT\Querying\Pagination\PagePagination;
use EDT\Querying\Utilities\Reindexer;
use Pagerfanta\Pagerfanta;

/**
 * @template-implements RepositoryInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, >
 */
class CustomFieldJsonRepository implements RepositoryInterface
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly ConditionFactoryInterface $conditionFactory,
        private readonly Reindexer $reindexer,
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
    ) {
    }

    public function getEntityByIdentifier(string $id, array $conditions, array $identifierPropertyPath): object
    {
        $customFieldConfiguration = $this->customFieldConfigurationRepository->find($id);

        if (!$customFieldConfiguration) {
            throw new InvalidArgumentException("CustomFieldConfiguration with ID '{$id}' not found");
        }

        $customField = $customFieldConfiguration->getConfiguration();
        $customField->setId($customFieldConfiguration->getId());

        return $customField;
    }

    public function getEntitiesByIdentifiers(array $identifiers, array $conditions, array $sortMethods, array $identifierPropertyPath): array
    {
        throw new InvalidArgumentException();
    }

    public function getEntities(array $conditions, array $sortMethods): array
    {
        $customFieldConfigurations = $this->customFieldConfigurationRepository->getEntities($conditions, $sortMethods);

        return array_map(function ($customFieldConfiguration) {
            $customField = $customFieldConfiguration->getConfiguration();
            $customField->setId($customFieldConfiguration->getId());

            return $customField;
        }, $customFieldConfigurations);
    }

    public function getEntitiesForPage(array $conditions, array $sortMethods, PagePagination $pagination): Pagerfanta
    {
        throw new InvalidArgumentException();
    }

    public function deleteEntityByIdentifier(string $entityIdentifier, array $conditions, array $identifierPropertyPath): void
    {
        // TODO: Implement deleteEntityByIdentifier() method.
        throw new InvalidArgumentException();
    }

    public function reindexEntities(array $entities, array $conditions, array $sortMethods): array
    {
        // TODO: Implement reindexEntities() method.

        return [];
    }

    public function isMatchingEntity(object $entity, array $conditions): bool
    {
        // TODO: Implement isMatchingEntity() method.
        throw new InvalidArgumentException();
    }

    public function assertMatchingEntity(object $entity, array $conditions): void
    {
        // TODO: Implement assertMatchingEntity() method.
        throw new InvalidArgumentException();
    }
}
