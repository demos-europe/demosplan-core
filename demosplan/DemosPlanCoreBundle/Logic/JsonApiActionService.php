<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Events\AfterResourceCreationEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\AfterResourceUpdateEventInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\CreatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Event\AfterResourceCreationEvent;
use demosplan\DemosPlanCoreBundle\Event\AfterResourceDeletionEvent;
use demosplan\DemosPlanCoreBundle\Event\AfterResourceUpdateEvent;
use demosplan\DemosPlanCoreBundle\Event\BeforeResourceCreationEvent;
use demosplan\DemosPlanCoreBundle\Event\BeforeResourceDeletionEvent;
use demosplan\DemosPlanCoreBundle\Event\BeforeResourceUpdateEvent;
use demosplan\DemosPlanCoreBundle\Event\BeforeResourceUpdateFlushEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\PersistResourceException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PrefilledResourceTypeProvider;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DeletableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\ReadableEsResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\SearchParams;
use demosplan\DemosPlanCoreBundle\ValueObject\ApiListResult;
use demosplan\DemosPlanCoreBundle\ValueObject\APIPagination;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
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
use EDT\Querying\Utilities\Iterables;
use EDT\Wrapping\Contracts\TypeRetrievalAccessException;
use EDT\Wrapping\Contracts\Types\TransferableTypeInterface;
use Exception;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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

    public function __construct(
        FilterParserInterface $filterParser,
        private readonly EntityFetcher $entityFetcher,
        TraceableEventDispatcher $eventDispatcher,
        private readonly JsonApiEsService $jsonApiEsService,
        private readonly JsonApiPaginationParser $paginationParser,
        JsonApiSortingParser $sortingParser,
        PaginatorFactory $paginatorFactory,
        PrefilledResourceTypeProvider $typeProvider,
        PropertyValuesGenerator $propertyValuesGenerator,
        private readonly ResourcePersister $resourcePersister,
        private readonly ResourceTypeService $resourceTypeService,
        private readonly TransactionService $transactionService
    ) {
        parent::__construct(
            $filterParser,
            $sortingParser,
            $paginatorFactory,
            $propertyValuesGenerator,
            $typeProvider
        );
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param array<int, ClauseFunctionInterface<bool>> $conditions
     * @param array<int, OrderBySortMethodInterface>    $sortMethods
     *
     * @throws QueryException
     * @throws UserNotFoundException
     */
    public function listObjects(
        TransferableTypeInterface $type,
        array $conditions,
        array $sortMethods = [],
        APIPagination $pagination = null
    ): ApiListResult {
        if (null === $pagination) {
            $filteredEntities = $type->listEntities($conditions, $sortMethods);

            return new ApiListResult($filteredEntities, [], null);
        }

        $paginator = $type->getEntityPaginator($pagination, $conditions, $sortMethods);

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
        $entityIdentifiers = $type->listEntityIdentifiers($conditions, []);

        return $this->jsonApiEsService->getEsFilteredObjects($type, $entityIdentifiers, $searchParams, $filterAsArray, $requireEntities, $sortMethods, $pagination);
    }

    protected function getObject(ResourceTypeInterface $type, string $id): object
    {
        return $type->getEntityAsReadTarget($id);
    }

    protected function updateObject(ResourceTypeInterface $resourceType, string $resourceId, array $properties): ?object
    {
        if (!$resourceType instanceof UpdatableDqlResourceTypeInterface) {
            throw new TypeRetrievalAccessException("Resource type is not updatable: {$resourceType::getName()}");
        }

        $entity = $resourceType->getEntityByTypeIdentifier($resourceId);

        $preEvent = new BeforeResourceUpdateEvent($entity, $resourceType, $properties);
        $this->eventDispatcher->dispatch($preEvent);

        $resourceChange = $this->resourcePersister->updateBackingObjectWithEntity($resourceType, $entity, $properties);

        $beforeResourceUpdateFlushEvent = new BeforeResourceUpdateFlushEvent($resourceChange);
        $this->eventDispatcher->dispatch($beforeResourceUpdateFlushEvent);

        $object = $this->persistResourceChange($resourceChange);

        $postEvent = new AfterResourceUpdateEvent($resourceChange);
        $this->eventDispatcher->dispatch($postEvent, AfterResourceUpdateEventInterface::class);

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
        $this->eventDispatcher->dispatch($afterCreationEvent, AfterResourceCreationEventInterface::class);

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
            $typeClass = $type::class;
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

        return $resourceChange->getUnrequestedChangesToTargetResource()
            ? $resourceChange->getTargetResource()
            : null;
    }

    protected function deleteObject(ResourceTypeInterface $resourceType, string $resourceId): void
    {
        if (!$resourceType instanceof DeletableDqlResourceTypeInterface) {
            throw new TypeRetrievalAccessException("Resource type is not deletable: {$resourceType::getName()}");
        }

        $entity = $resourceType->getEntityByTypeIdentifier($resourceId);

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
