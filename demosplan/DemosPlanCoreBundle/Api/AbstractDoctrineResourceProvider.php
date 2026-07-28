<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\JsonApi\InputHandling\RepositoryInterface;
use EDT\Querying\Contracts\PathException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

/**
 * Shared `Get`/`GetCollection` provider logic for read-only ApiPlatform resources backed by a
 * {@see RepositoryInterface} entity and scoped by an {@see AccessCheckerInterface}.
 *
 * Concrete providers (e.g. {@see \demosplan\DemosPlanCoreBundle\Api\Place\Provider}) only need to
 * declare their resource class, sortable properties and entity-to-resource mapping; the
 * availability check, access-condition scoping and single-vs-collection branching live here once.
 *
 * @template TEntity of object
 * @template TResource of object
 */
abstract class AbstractDoctrineResourceProvider implements ProviderInterface
{
    /**
     * @param RepositoryInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, TEntity> $repository
     */
    public function __construct(
        private readonly AccessCheckerInterface $accessChecker,
        private readonly RepositoryInterface $repository,
        private readonly SortMethodFactory $sortMethodFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), $this->getResourceClass());

        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection($this->getSortMethods($context));
        }

        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        return null;
    }

    /**
     * @return class-string<TResource>
     */
    abstract protected function getResourceClass(): string;

    /**
     * @return list<non-empty-string> query param values of `sort` this resource accepts, applied ascending
     */
    abstract protected function getSortableProperties(): array;

    /**
     * @param TEntity $entity
     *
     * @return TResource
     */
    abstract protected function mapToResource(object $entity): object;

    /**
     * @return list<OrderBySortMethodInterface>
     * @throws PathException
     */
    protected function getSortMethods(array $context): array
    {
        if (!$context || !array_key_exists('request', $context)) {
            return [];
        }

        $sortQueryParamValue = $context['request']->query->get('sort');

        return in_array($sortQueryParamValue, $this->getSortableProperties(), true)
            ? [$this->sortMethodFactory->propertyAscending([$sortQueryParamValue])]
            : [];
    }

    /**
     * @return TResource|null
     */
    protected function provideSingle(string $id): ?object
    {
        try {
            $entity = $this->repository->getEntityByIdentifier(
                $id,
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->mapToResource($entity);
    }

    /**
     * @param list<OrderBySortMethodInterface> $sortMethods
     *
     * @return list<TResource>
     */
    protected function provideCollection(array $sortMethods): array
    {
        $entities = $this->repository->getEntities(
            $this->accessChecker->getAccessConditions(),
            $sortMethods,
        );

        return array_map(
            fn (object $entity): object => $this->mapToResource($entity),
            $entities
        );
    }
}
