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

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\DoctrineOrmPartialDTOProvider;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\DqlFluentQuery;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PartialDTO;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\MappingException;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\DqlQuerying\ObjectProviders\DoctrineOrmEntityProvider;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\DqlQuerying\Utilities\JoinFinder;
use EDT\DqlQuerying\Utilities\QueryBuilderPreparer;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Contracts\PaginationException;
use EDT\Querying\Contracts\SortMethodInterface;
use EDT\Querying\FluentQueries\ConditionDefinition;
use EDT\Querying\FluentQueries\FluentQuery;
use EDT\Querying\FluentQueries\SliceDefinition;
use EDT\Querying\FluentQueries\SortDefinition;
use EDT\Querying\Pagination\PagePagination;
use EDT\Querying\Utilities\Iterables;
use InvalidArgumentException;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;

use function is_array;

/**
 * @template T of object
 *
 * @template-extends CoreRepository<T>
 */
abstract class FluentRepository extends CoreRepository
{
    protected DoctrineOrmEntityProvider $objectProvider;
    protected JoinFinder $joinFinder;
    protected QueryBuilderPreparer $builderPreparer;
    protected EntityManagerInterface $entityManager;

    public function __construct(
        protected readonly DqlConditionFactory $conditionFactory,
        ManagerRegistry $registry,
        protected readonly SortMethodFactory $sortMethodFactory,
        string $entityClass
    ) {
        parent::__construct($registry, $entityClass);

        $this->entityManager = $this->getEntityManager();
        $metadataFactory = $this->entityManager->getMetadataFactory();
        $this->joinFinder = new JoinFinder($metadataFactory);
        $this->builderPreparer = new QueryBuilderPreparer($entityClass, $metadataFactory, $this->joinFinder);
        $this->objectProvider = new DoctrineOrmEntityProvider($this->entityManager, $this->builderPreparer);
    }

    public function createFluentQuery(): FluentQuery
    {
        return new DqlFluentQuery(
            $this->objectProvider,
            new ConditionDefinition($this->conditionFactory, true),
            new SortDefinition($this->sortMethodFactory),
            new SliceDefinition()
        );
    }

    /**
     * Will return all entities matching the given condition with the specified sorting.
     *
     * If you have a use-case that is covered by
     * {@link EntityRepository::findBy() findBy}/{@link EntityRepository::matching matching}
     * you should use these methods instead of this one, as they are the more standard approach.
     *
     * If you want to use paths instead of manually defining joins you should use this method.
     * You may also need to use it for more exotic expressions covered by conditions like
     * {@link ConditionFactoryInterface::propertyHasSize()} and {@link ConditionFactoryInterface::propertiesEqual()}.
     *
     * Unlike {@link DplanResourceType::listEntities} this method won't apply any restrictions
     * beside the provided conditions.
     *
     * @param array<int,FunctionInterface<bool>> $conditions  will be applied in an `AND` conjunction
     * @param array<int,SortMethodInterface>     $sortMethods will be applied in the given order
     *
     * @return array<int,T>
     */
    public function getEntities(array $conditions, array $sortMethods, int $offset = 0, int $limit = null): array
    {
        $entities = $this->objectProvider->getObjects($conditions, $sortMethods, $offset, $limit);

        return is_array($entities) ? $entities : iterator_to_array($entities);
    }

    /**
     * Will provide access to all entities matching the given condition via a paginator.
     * The entities will be sorted by the specified sorting.
     *
     * Unlike {@link DplanResourceType::listEntities} this method won't apply any restrictions
     * beside the provided conditions.
     *
     * @param array<int,ClauseFunctionInterface<bool>> $conditions  will be applied in an `AND` conjunction
     * @param array<int,OrderBySortMethodInterface>    $sortMethods will be applied in the given order
     *
     * @return DemosPlanPaginator&Pagerfanta<T>
     */
    public function getEntitiesForPage(
        array $conditions,
        array $sortMethods,
        PagePagination $pagination
    ): DemosPlanPaginator {
        $queryBuilder = $this->objectProvider->generateQueryBuilder($conditions, $sortMethods);

        $queryAdapter = new QueryAdapter($queryBuilder);
        $paginator = new DemosPlanPaginator($queryAdapter);
        $paginator->setMaxPerPage($pagination->getSize());
        $paginator->setCurrentPage($pagination->getNumber());

        return $paginator;
    }

    /**
     * @param list<FunctionInterface<bool>> $conditions
     *
     * @return int<0, max>
     */
    public function getEntityCount(array $conditions): int
    {
        $pagePagination = new PagePagination(1, 1);

        return $this->getEntitiesForPage($conditions, [], $pagePagination)->getAdapter()->getNbResults();
    }

    /**
     * @param non-empty-string                 $id
     * @param list<FunctionInterface<bool>>    $conditions
     * @param non-empty-list<non-empty-string> $identifierPropertyPath
     *
     * @return T
     */
    public function getEntityByIdentifier(string $id, array $conditions, array $identifierPropertyPath): object
    {
        $identifierCondition = $this->conditionFactory->propertyHasValue($id, $identifierPropertyPath);
        $entities = $this->getEntities($conditions, [$identifierCondition], 0, 2);

        return match (count($entities)) {
            0       => throw new InvalidArgumentException("No matching `{$this->getEntityName()}` entity found."),
            1       => array_pop($entities),
            default => throw new InvalidArgumentException("Multiple matching `{$this->getEntityName()}` entities found.")
        };
    }

    /**
     * @param array<int,FunctionInterface<bool>> $conditions         will be applied in an `AND` conjunction
     * @param array<int,SortMethodInterface>     $sortMethods        will be applied in the given order
     * @param non-empty-string                   $identifierProperty
     *
     * @return list<non-empty-string>
     *
     * @throws MappingException
     * @throws PaginationException
     */
    public function getEntityIdentifiers(array $conditions, array $sortMethods, string $identifierProperty): array
    {
        $entityProvider = new DoctrineOrmPartialDTOProvider(
            $this->entityManager,
            $this->builderPreparer,
            $identifierProperty
        );

        $entities = $entityProvider->getEntities($conditions, $sortMethods, null);
        $entities = Iterables::asArray($entities);
        $partialDtos = array_values($entities);

        return array_map(static fn (PartialDTO $dto): string => $dto->getProperty($identifierProperty), $partialDtos);
    }
}
