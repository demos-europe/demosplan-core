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

use DemosEurope\DemosplanAddon\Contracts\ResourceType\CreatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Query\QueryException;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\RequestHandling\AbstractApiService;
use EDT\JsonApi\RequestHandling\ApiListResultInterface;
use EDT\JsonApi\RequestHandling\FilterParserInterface;
use EDT\JsonApi\RequestHandling\JsonApiSortingParser;
use EDT\JsonApi\RequestHandling\PaginatorFactory;
use EDT\JsonApi\RequestHandling\PropertyValuesGenerator;
use EDT\JsonApi\RequestHandling\UrlParameter;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Contracts\SortMethodInterface;
use EDT\Querying\Utilities\Iterables;
use EDT\Wrapping\Contracts\TypeRetrievalAccessException;
use EDT\Wrapping\Contracts\Types\ReadableTypeInterface;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use demosplan\DemosPlanCoreBundle\Event\AfterResourceCreationEvent;
use demosplan\DemosPlanCoreBundle\Event\AfterResourceDeletionEvent;
use demosplan\DemosPlanCoreBundle\Event\AfterResourceUpdateEvent;
use demosplan\DemosPlanCoreBundle\Event\BeforeResourceCreationEvent;
use demosplan\DemosPlanCoreBundle\Event\BeforeResourceDeletionEvent;
use demosplan\DemosPlanCoreBundle\Event\BeforeResourceUpdateEvent;
use demosplan\DemosPlanCoreBundle\Event\BeforeResourceUpdateFlushEvent;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\PersistResourceException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PrefilledResourceTypeProvider;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DeletableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\ReadableEsResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\SearchParams;
use demosplan\DemosPlanCoreBundle\ValueObject\APIPagination;
use demosplan\DemosPlanCoreBundle\ValueObject\ApiListResult;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use function get_class;

/**
 * @template-extends AbstractApiService<ClauseFunctionInterface<bool>>
 */
class JsonApiActionService extends AbstractApiService
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var ResourceTypeService
     */
    private $resourceTypeService;

    /**
     * @var EntityFetcher
     */
    private $entityFetcher;

    /**
     * @var SearchIndexTaskService
     */
    private $searchIndexTaskService;

    /**
     * @var TransactionService
     */
    private $transactionService;

    /**
     * @var ResourcePersister
     */
    private $resourcePersister;

    /**
     * @var JsonApiPaginationParser
     */
    private $paginationParser;

    /**
     * @var JsonApiEsService
     */
    private $jsonApiEsService;

    public function __construct(
        FilterParserInterface $filterParser,
        EntityFetcher $entityFetcher,
        TraceableEventDispatcher $eventDispatcher,
        JsonApiEsService $jsonApiEsService,
        JsonApiPaginationParser $paginationParser,
        JsonApiSortingParser $sortingParser,
        PaginatorFactory $paginatorFactory,
        PrefilledResourceTypeProvider $typeProvider,
        PropertyValuesGenerator $propertyValuesGenerator,
        ResourcePersister $resourcePersister,
        ResourceTypeService $resourceTypeService,
        SearchIndexTaskService $searchIndexTaskService,
        TransactionService $transactionService
    ) {
        parent::__construct(
            $filterParser,
            $sortingParser,
            $paginatorFactory,
            $propertyValuesGenerator,
            $typeProvider
        );
        $this->eventDispatcher = $eventDispatcher;
        $this->resourceTypeService = $resourceTypeService;
        $this->entityFetcher = $entityFetcher;
        $this->searchIndexTaskService = $searchIndexTaskService;
        $this->transactionService = $transactionService;
        $this->resourcePersister = $resourcePersister;
        $this->paginationParser = $paginationParser;
        $this->jsonApiEsService = $jsonApiEsService;
    }

    /**
     * @param array<int, ClauseFunctionInterface<bool>> $conditions
     * @param array<int, OrderBySortMethodInterface>    $sortMethods
     *
     * @throws QueryException
     * @throws UserNotFoundException
     */
    public function listObjects(
        ReadableTypeInterface $type,
        array $conditions,
        array $sortMethods = [],
        APIPagination $pagination = null
    ): ApiListResult {
        if (null === $pagination) {
            $filteredEntities = $this->entityFetcher->listEntities($type, $conditions, $sortMethods);

            return new ApiListResult($filteredEntities, [], null);
        }

        $paginator = $this->entityFetcher->getEntityPaginator($type, $pagination, $conditions, $sortMethods);

        $entities = $paginator->getCurrentPageResults();
        $entities = Iterables::asArray($entities);

        return new ApiListResult($entities, [], null, null, $paginator);
    }

    /**
     * @param ReadableEsResourceTypeInterface&DplanResourceType $type
     * @param array<int, FunctionInterface<bool>>               $conditions
     */
    public function searchObjects(
        ReadableEsResourceTypeInterface $type,
        SearchParams $searchParams,
        array $conditions,
        array $sortMethods = [],
        array $filterAsArray = [],
        bool $requireEntities = true,
        APIPagination $pagination = null
    ): ApiListResult {
        // we do not need to apply any sorting here, because it needs to be applied later
        $entityIdentifiers = $this->entityFetcher->listEntityIdentifiers($type, $conditions, []);

        return $this->jsonApiEsService->getEsFilteredObjects($type, $entityIdentifiers, $searchParams, $filterAsArray, $requireEntities, $sortMethods, $pagination);
    }

    protected function getObject(ResourceTypeInterface $type, string $id): object
    {
        return $this->entityFetcher->getEntityAsReadTarget($type, $id);
    }

    protected function updateObject(ResourceTypeInterface $resourceType, string $resourceId, array $properties): ?object
    {
        if (!$resourceType instanceof UpdatableDqlResourceTypeInterface) {
            throw new TypeRetrievalAccessException("Resource type is not updatable: {$resourceType::getName()}");
        }

        $entity = $this->entityFetcher->getEntityAsUpdateTarget($resourceType, $resourceId);

        $preEvent = new BeforeResourceUpdateEvent($entity, $resourceType, $properties);
        $this->eventDispatcher->dispatch($preEvent);

        $resourceChange = $this->resourcePersister->updateBackingObjectWithEntity($resourceType, $entity, $properties);

        $beforeResourceUpdateFlushEvent = new BeforeResourceUpdateFlushEvent($resourceChange);
        $this->eventDispatcher->dispatch($beforeResourceUpdateFlushEvent);

        $object = $this->persistResourceChange($resourceChange);

        $postEvent = new AfterResourceUpdateEvent($resourceChange);
        $this->eventDispatcher->dispatch($postEvent);

        return $object;
    }

    public function createObject(ResourceTypeInterface $resourceType, array $properties): ?object
    {
        if (!$resourceType instanceof CreatableDqlResourceTypeInterface) {
            throw new TypeRetrievalAccessException("Resource type is not creatable: {$resourceType::getName()}");
        }

        if (!$resourceType->isCreatable()) {
            throw new BadRequestException("User is not allowed to create {$resourceType::getName()} resources.");
        }

        $initializableProperties = $resourceType->getInitializableProperties();
        $this->resourceTypeService->checkWriteAccess($resourceType, $properties, $initializableProperties);
        $requiredProperties = array_flip($resourceType->getPropertiesRequiredForCreation());
        $this->resourceTypeService->checkRequiredProperties($resourceType, $properties, $requiredProperties);

        $beforeCreationEvent = new BeforeResourceCreationEvent($resourceType, $properties);
        $this->eventDispatcher->dispatch($beforeCreationEvent);

        $resourceChange = $resourceType->createObject($properties);
        // Currently we always create the ID in the backend, hence we must always return the object
        // to the FE by invoking the following method.
        $resourceChange->setUnrequestedChangesToTargetResource();
        try {
            $object = $this->persistResourceChange($resourceChange);
        } catch (Exception $e) {
            $resourceType->addCreationErrorMessage($properties);
            throw new PersistResourceException($e->getMessage(), 0, $e);
        }

        $afterCreationEvent = new AfterResourceCreationEvent($resourceChange);
        $this->eventDispatcher->dispatch($afterCreationEvent);

        return $object;
    }

    /**
     * @throws QueryException
     * @throws UserNotFoundException
     */
    public function getObjectsByQueryParams(
        ParameterBag $query,
        ResourceTypeInterface $type
    ): ApiListResult {
        $filters = $this->getFilters($query);
        $sortMethods = $this->getSorting($query);

        return $this->getObjects($type, $filters, $sortMethods, $query);
    }

    protected function getObjects(
        ResourceTypeInterface $type,
        array $filters,
        array $sortMethods,
        ParameterBag $query
    ): ApiListResultInterface {
        $searchParams = SearchParams::createOptional($query->get('search', []));
        $pagination = $this->getPagination($query);

        if (null === $searchParams) {
            return $this->listObjects(
                $type,
                $filters,
                $sortMethods,
                $pagination
            );
        }

        if (!$type instanceof ReadableEsResourceTypeInterface) {
            $typeClass = get_class($type);
            throw new InvalidArgumentException("Type does not implement ReadableEsResourceTypeInterface: $typeClass");
        }

        return $this->searchObjects(
            $type,
            $searchParams,
            $filters,
            $sortMethods,
            [],
            true,
            $pagination
        );
    }

    /**
     * Persist the entities in the given object in the database.
     *
     * @return object|null The original target of the update or creation request. null if the target
     *                     object was persisted exactly as originally requested (i.e. no side effects done).
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ConnectionException
     */
    public function persistResourceChange(ResourceChange $resourceChange): ?object
    {
        $this->transactionService->persistResourceChange($resourceChange);

        $entityIdsByClass = $resourceChange->getEntityIdsToUpdateInIndex();
        collect($entityIdsByClass)->each(function (array $entityIds, string $class): void {
            $this->searchIndexTaskService->addIndexTask($class, $entityIds);
        });

        return $resourceChange->getUnrequestedChangesToTargetResource()
            ? $resourceChange->getTargetResource()
            : null;
    }

    protected function deleteObject(ResourceTypeInterface $resourceType, string $resourceId): void
    {
        if (!$resourceType instanceof DeletableDqlResourceTypeInterface) {
            throw new TypeRetrievalAccessException("Resource type is not deletable: {$resourceType::getName()}");
        }

        $entity = $this->entityFetcher->getEntityAsDeletionTarget($resourceType, $resourceId);

        $beforeDeletionEvent = new BeforeResourceDeletionEvent($entity, $resourceType);
        $this->eventDispatcher->dispatch($beforeDeletionEvent);

        $resourceChange = $resourceType->delete($entity);
        $this->persistResourceChange($resourceChange);

        $afterDeletionEvent = new AfterResourceDeletionEvent($resourceType);
        $this->eventDispatcher->dispatch($afterDeletionEvent);
    }

    protected function normalizeTypeName(string $typeName): string
    {
        return $typeName;
    }

    protected function getPagination(ParameterBag $query): ?APIPagination
    {
        $pagination = null;
        if ($query->has(UrlParameter::PAGE)) {
            $pagination = $this->paginationParser->parseApiPaginationProfile(
                $query->get(UrlParameter::PAGE, []),
                '', // sorting is done using JsonApiSortingParser
                $query->get(UrlParameter::SIZE, JsonApiPaginationParser::DEFAULT_PAGE_SIZE)
            );
        }

        return $pagination;
    }
}
