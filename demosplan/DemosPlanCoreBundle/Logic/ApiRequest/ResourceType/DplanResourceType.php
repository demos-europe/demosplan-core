<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType;

use Carbon\Carbon;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\GetPropertiesEventInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\GetInternalPropertiesEvent;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\GetPropertiesEvent;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PrefilledResourceTypeProvider;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\TransformerLoader;
use demosplan\DemosPlanCoreBundle\Logic\EntityWrapperFactory;
use demosplan\DemosPlanCoreBundle\Logic\Logger\ApiLogger;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Repository\FluentRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use demosplan\DemosPlanCoreBundle\ValueObject\APIPagination;
use Doctrine\ORM\EntityManagerInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\MappingException;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\DqlQuerying\Utilities\JoinFinder;
use EDT\JsonApi\RequestHandling\MessageFormatter;
use EDT\JsonApi\ResourceTypes\CachingResourceType;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\PathBuilding\End;
use EDT\PathBuilding\PropertyAutoPathInterface;
use EDT\PathBuilding\PropertyAutoPathTrait;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Contracts\PaginationException;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\PropertyPathInterface;
use EDT\Querying\Contracts\SortMethodFactoryInterface;
use EDT\Querying\Contracts\SortMethodInterface;
use EDT\Querying\ObjectProviders\PrefilledObjectProvider;
use EDT\Querying\Pagination\PagePagination;
use EDT\Querying\Utilities\ConditionEvaluator;
use EDT\Querying\Utilities\Iterables;
use EDT\Querying\Utilities\Sorter;
use EDT\Wrapping\Contracts\AccessException;
use EDT\Wrapping\Contracts\TypeProviderInterface;
use EDT\Wrapping\Contracts\Types\ExposableRelationshipTypeInterface;
use EDT\Wrapping\Contracts\Types\FilterableTypeInterface;
use EDT\Wrapping\Contracts\Types\SortableTypeInterface;
use EDT\Wrapping\Contracts\Types\TransferableTypeInterface;
use EDT\Wrapping\Contracts\Types\TypeInterface;
use EDT\Wrapping\Properties\UpdatableRelationship;
use EDT\Wrapping\Utilities\SchemaPathProcessor;
use EDT\Wrapping\WrapperFactories\WrapperObjectFactory;
use IteratorAggregate;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

use function collect;
use function in_array;
use function is_array;

/**
 * @template T of object
 *
 * @template-extends CachingResourceType<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, T>
 * @template-extends IteratorAggregate<int, non-empty-string>
 *
 * @property-read End $id
 */
abstract class DplanResourceType extends CachingResourceType implements IteratorAggregate, PropertyAutoPathInterface, ExposableRelationshipTypeInterface
{
    use PropertyAutoPathTrait;

    protected ?CurrentUserInterface $currentUser;
    protected ?CurrentProcedureService $currentProcedureService;
    protected ?GlobalConfigInterface $globalConfig;
    protected ?LoggerInterface $logger;
    protected ?MessageBagInterface $messageBag;
    protected ?ResourceTypeService $resourceTypeService;
    protected ?TransformerLoader $transformerLoader;
    protected ?TranslatorInterface $translator;
    protected ?CustomerService $currentCustomerService;
    protected ?DqlConditionFactory $conditionFactory;
    protected ?TypeProviderInterface $typeProvider;
    protected ?WrapperObjectFactory $wrapperFactory;
    protected ?SortMethodFactoryInterface $sortMethodFactory;
    protected ?ApiLogger $apiLogger;
    protected ?EventDispatcherInterface $eventDispatcher;
    protected ?MessageFormatter $messageFormatter;
    protected ?EntityManagerInterface $entityManager;
    protected ?SchemaPathProcessor $schemaPathProcessor;
    protected ?ConditionEvaluator $conditionEvaluator;
    protected ?Sorter $sorter;
    protected ?JoinFinder $joinFinder;
    protected ?FluentRepository $repository;

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setCurrentProcedureService(CurrentProcedureService $currentProcedureService): void
    {
        $this->currentProcedureService = $currentProcedureService;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setCustomerService(CustomerService $customerService): void
    {
        $this->currentCustomerService = $customerService;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setCurrentUserService(CurrentUserInterface $currentUserService): void
    {
        $this->currentUser = $currentUserService;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setGlobalConfig(GlobalConfigInterface $globalConfig): void
    {
        $this->globalConfig = $globalConfig;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setMessageBag(MessageBagInterface $messageBag): void
    {
        $this->messageBag = $messageBag;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setResourceTypeService(ResourceTypeService $resourceTypeService): void
    {
        $this->resourceTypeService = $resourceTypeService;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setTransformerLoader(TransformerLoader $transformerLoader): void
    {
        $this->transformerLoader = $transformerLoader;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setConditionFactory(DqlConditionFactory $conditionFactory): void
    {
        $this->conditionFactory = $conditionFactory;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setTypeProvider(PrefilledResourceTypeProvider $typeProvider): void
    {
        $this->typeProvider = $typeProvider;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setWrapperFactory(EntityWrapperFactory $wrapperFactory): void
    {
        $this->wrapperFactory = $wrapperFactory;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setSortMethodFactory(SortMethodFactory $sortMethodFactory): void
    {
        $this->sortMethodFactory = $sortMethodFactory;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setApiLogger(ApiLogger $apiLogger): void
    {
        $this->apiLogger = $apiLogger;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setEventDispatcher(TraceableEventDispatcher $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
        $this->joinFinder = new JoinFinder($this->entityManager->getMetadataFactory());

        $repository = $entityManager->getRepository($this->getEntityClass());
        if (!$repository instanceof FluentRepository) {
            $fluentRepositoryClass = FluentRepository::class;
            throw new InvalidArgumentException("No repository found extending `$fluentRepositoryClass` for entity `{$this->getEntityClass()}`.");
        }
        $this->repository = $repository;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setSchemaPathProcessor(SchemaPathProcessor $schemaPathProcessor): void
    {
        $this->schemaPathProcessor = $schemaPathProcessor;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setConditionEvaluator(ConditionEvaluator $conditionEvaluator): void
    {
        $this->conditionEvaluator = $conditionEvaluator;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setSorter(Sorter $sorter): void
    {
        $this->sorter = $sorter;
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @throws MessageBagException
     */
    public function addCreationErrorMessage(array $parameters): void
    {
        $this->messageBag->add('error', 'generic.error');
    }

    public function getDefaultSortMethods(): array
    {
        return [];
    }

    public function getIdentifierPropertyPath(): array
    {
        return $this->id->getAsNames();
    }

    public function getInternalProperties(): array
    {
        $properties = array_map(static function (string $className): ?string {
            $classImplements = class_implements($className);
            if (is_array($classImplements) && in_array(ResourceTypeInterface::class, $classImplements, true)) {
                /* @var ResourceTypeInterface $className */
                return $className::getName();
            }

            return null;
        }, $this->getAutoPathProperties());

        $event = new GetInternalPropertiesEvent($properties, $this);
        $this->eventDispatcher->dispatch($event);

        return array_map(
            fn (?string $typeIdentifier): ?TypeInterface => null === $typeIdentifier
                ? null
                : $this->typeProvider->requestType($typeIdentifier)->getInstanceOrThrow(),
            $event->getProperties(),
        );
    }

    public function isExposedAsPrimaryResource(): bool
    {
        return $this->isAvailable() && $this->isDirectlyAccessible();
    }

    /**
     * @deprecated do not implement or call this method, it will be removed as soon as possible
     */
    public function isExposedAsRelationship(): bool
    {
        return $this->isAvailable() && $this->isReferencable();
    }

    abstract public function isAvailable(): bool;

    abstract public function isDirectlyAccessible(): bool;

    /**
     * @deprecated Move the permission-checks from the overrides of this method to the
     *             {@link self::getProperties()} method of the referencing resource type instead.
     *             Afterward, return `true` in the override of this method.
     */
    abstract public function isReferencable(): bool;

    /**
     * Convert the given array to an array with different mapping.
     *
     * The returned array will map using
     *
     * * as key: the dot notation of the property path
     * * as value: the corresponding {@link ResourceTypeInterface::getName} return value in case of a
     * relationship or `null` in case of an attribute
     *
     * The behavior for multiple given property paths with the same dot notation is undefined.
     *
     * @return array<non-empty-string, UpdatableRelationship|null>
     */
    protected function toProperties(PropertyPathInterface ...$propertyPaths): array
    {
        return collect($propertyPaths)
            ->mapWithKeys(static function (PropertyPathInterface $propertyPath): array {
                $key = $propertyPath->getAsNamesInDotNotation();
                $value = $propertyPath instanceof ResourceTypeInterface
                    ? new UpdatableRelationship([])
                    : null;

                return [$key => $value];
            })->all();
    }

    /**
     * Will return all entities matching the given condition with the specified sorting.
     *
     * For all properties accessed while filtering/sorting it is checked if:
     *
     * * the given type and the types in the property paths are
     *  {@link TypeInterface::isAvailable() available at all} and
     *  {@link TransferableTypeInterface readable}
     * * the property is available for
     *  {@link FilterableTypeInterface::getFilterableProperties() filtering}/
     *  {@link SortableTypeInterface::getSortableProperties() sorting}
     *
     * @param array<int,FunctionInterface<bool>> $conditions  Always conjuncted as AND. Order does not matter
     * @param array<int,SortMethodInterface>     $sortMethods Order matters. Lower positions imply
     *                                                        higher priority. Ie. a second sort method
     *                                                        will be applied to each subset individually
     *                                                        that resulted from the first sort method.
     *                                                        The array keys will be ignored.
     *
     * @return array<int,T>
     *
     * @throws AccessException thrown if the resource type denies the currently logged in user
     *                         the access to the resource type needed to fulfill the request
     */
    public function listEntities(array $conditions, array $sortMethods = []): array
    {
        $this->assertDirectlyAvailable();

        $conditions = $this->mapConditions($conditions);
        $sortMethods = $this->mapSortMethods($sortMethods);

        return $this->repository->getEntities($conditions, $sortMethods);
    }

    /**
     * Unlike {@link FluentRepository::getEntitiesForPage} this method accepts conditions and sort methods using the
     * schema of a resource type instead of the schema of the backing entity.
     *
     * It will automatically check access rights and apply aliases before creating a
     * {@link QueryBuilder} and using it to create the returned {@link DemosPlanPaginator}.
     *
     * @param array<int, ClauseFunctionInterface<bool>> $conditions
     * @param array<int, OrderBySortMethodInterface>    $sortMethods
     *
     * @throws MappingException
     * @throws PaginationException
     * @throws PathException
     */
    public function getEntityPaginator(
        APIPagination $pagination,
        array $conditions,
        array $sortMethods = []
    ): DemosPlanPaginator {
        $this->assertDirectlyAvailable();

        $conditions = $this->mapConditions($conditions);
        $sortMethods = $this->mapSortMethods($sortMethods);
        $pagePagination = new PagePagination($pagination->getSize(), $pagination->getNumber());

        return $this->repository->getEntitiesForPage($conditions, $sortMethods, $pagePagination);
    }

    /**
     * Will return all entities matching the given condition with the specified sorting. The dataObjects array is the data source from which
     * matching entities will be returned (This is the only difference to the listEntities function above!).
     *
     * For all properties accessed while filtering/sorting it is checked if:
     *
     * * the given type and the types in the property paths are
     *   {@link TypeInterface::isAvailable() available at all} and
     *   {@link TransferableTypeInterface readable}
     * * the property is available for
     *   {@link FilterableTypeInterface::getFilterableProperties() filtering}/
     *   {@link SortableTypeInterface::getSortableProperties() sorting}
     *
     * @param array<int,T>                       $dataObjects
     * @param array<int,FunctionInterface<bool>> $conditions  Always conjuncted as AND. Order does not matter
     * @param array<int,SortMethodInterface>     $sortMethods Order matters. Lower positions imply
     *                                                        higher priority. Ie. a second sort method
     *                                                        will be applied to each subset individually
     *                                                        that resulted from the first sort method.
     *                                                        The array keys will be ignored.
     *
     * @return array<int, T>
     *
     * @throws AccessException thrown if the resource type denies the currently logged in user
     *                         the access to the resource type needed to fulfill the request
     */
    public function listPrefilteredEntities(
        array $dataObjects,
        array $conditions = [],
        array $sortMethods = []
    ): array {
        $this->assertDirectlyAvailable();

        $conditions = $this->mapConditions($conditions);
        $sortMethods = $this->mapSortMethods($sortMethods);

        $entityProvider = new PrefilledObjectProvider($this->conditionEvaluator, $this->sorter, $dataObjects);
        $entities = $entityProvider->getEntities($conditions, $sortMethods, null);
        $entities = Iterables::asArray($entities);

        return array_values($entities);
    }

    /**
     * @return T
     *
     * @throws AccessException          thrown if the resource type denies the currently logged in user
     *                                  the access to the resource type needed to fulfill the request
     * @throws InvalidArgumentException thrown if no entity with the given ID and resource type was found
     */
    public function getEntityAsReadTarget(string $id): object
    {
        if (!$this->isDirectlyAccessible()) {
            throw AccessException::typeNotDirectlyAccessible($this);
        }

        return $this->getEntityByTypeIdentifier($id);
    }

    /**
     * @param array<int, ClauseFunctionInterface<bool>> $conditions
     */
    public function getEntityCount(array $conditions): int
    {
        $this->assertDirectlyAvailable();

        $conditions = $this->mapConditions($conditions);

        return $this->repository->getEntityCount($conditions);
    }

    /**
     * @return T
     *
     * @throws AccessException          thrown if the resource type denies the currently logged in user
     *                                  the access to the resource type needed to fulfill the request
     * @throws InvalidArgumentException thrown if no entity with the given ID and resource type was found
     */
    public function getEntityByTypeIdentifier(string $id): object
    {
        if (!$this->isAvailable()) {
            throw AccessException::typeNotAvailable($this);
        }

        try {
            return $this->repository->getEntityByIdentifier($id, [], $this->getIdentifierPropertyPath());
        } catch (AccessException $e) {
            $typeName = $this::getName();
            throw new InvalidArgumentException("Could not retrieve entity for type '$typeName' with ID '$id'.", 0, $e);
        }
    }

    /**
     * @param array<int,FunctionInterface<bool>> $conditions  Always conjuncted as AND. Order does not matter
     * @param array<int,SortMethodInterface>     $sortMethods Order matters. Lower positions imply
     *                                                        higher priority. I.e. a second sort method
     *                                                        will be applied to each subset individually
     *                                                        that resulted from the first sort method.
     *                                                        The array keys will be ignored.
     *
     * @return array<int, string> the identifiers of the entities, sorted by the given $sortMethods
     *
     * @throws AccessException thrown if the resource type denies the currently logged in user
     *                         the access to the resource type needed to fulfill the request
     */
    public function listEntityIdentifiers(
        array $conditions,
        array $sortMethods
    ): array {
        $this->assertDirectlyAvailable();

        $conditions = $this->mapConditions($conditions);
        $sortMethods = $this->mapSortMethods($sortMethods);

        return $this->repository->getEntityIdentifiers($conditions, $sortMethods, $this->getEntityIdentifierProperty());
    }

    /**
     * @throws AccessException
     */
    protected function assertDirectlyAvailable(): void
    {
        if (!$this->isDirectlyAccessible()) {
            throw AccessException::typeNotDirectlyAccessible($this);
        }

        if (!$this->isAvailable()) {
            throw AccessException::typeNotAvailable($this);
        }
    }

    protected function processProperties(array $properties): array
    {
        $event = new GetPropertiesEvent($this, $properties);
        $this->eventDispatcher->dispatch($event, GetPropertiesEventInterface::class);

        return $event->getProperties();
    }

    protected function getWrapperFactory(): WrapperObjectFactory
    {
        return $this->wrapperFactory;
    }

    protected function getTypeProvider(): TypeProviderInterface
    {
        return $this->typeProvider;
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    protected function formatDate(?DateTime $date): ?string
    {
        if (null === $date) {
            return null;
        }

        return Carbon::instance($date)->toIso8601String();
    }

    protected function getMessageFormatter(): MessageFormatter
    {
        if (!isset($this->messageFormatter)) {
            $this->messageFormatter = new MessageFormatter();
        }

        return $this->messageFormatter;
    }

    /**
     * This method determines the property within an entity that should be used as ID, as
     * not all entities use `id` for this but some use `ident`.
     *
     * It can only get the identifier if it is not nested within a relationship, because
     * support for nested IDs was not added yet.
     *
     * You probably want to utilize {@link SchemaPathProcessor::processPropertyPath} when
     * you add support for paths (nested IDs).
     *
     * @throws NotYetImplementedException if the ID is either nested within a relationship of the resource/entity
     */
    protected function getEntityIdentifierProperty(): string
    {
        // get the resource identifier attribute
        $resourceIdPath = $this->getIdentifierPropertyPath();
        if (1 !== count($resourceIdPath)) {
            throw new NotYetImplementedException('Usage of a property within a resource relationship as ID is not yet supported');
        }

        // map the resource identifier attribute to an entity property
        $resourceIdProperty = array_pop($resourceIdPath);
        $entityIdPath = $this->getAliases()[$resourceIdProperty] ?? [$resourceIdProperty];
        if (1 !== (is_countable($entityIdPath) ? count($entityIdPath) : 0)) {
            throw new NotYetImplementedException('Usage of a property within a entity relationship as ID is not yet supported');
        }

        return array_pop($entityIdPath);
    }

    /**
     * @param list<ClauseFunctionInterface<bool>> $conditions
     *
     * @return list<ClauseFunctionInterface<bool>>
     *
     * @throws PathException
     */
    private function mapConditions(array $conditions): array
    {
        if ([] !== $conditions && $this instanceof FilterableTypeInterface) {
            $this->schemaPathProcessor->mapFilterConditions($this, $conditions);
        }
        $conditions[] = $this->schemaPathProcessor->processAccessCondition($this);

        return $conditions;
    }

    /**
     * @param list<OrderBySortMethodInterface> $sortMethods
     *
     * @return list<OrderBySortMethodInterface>
     *
     * @throws PathException
     */
    private function mapSortMethods(array $sortMethods): array
    {
        if ([] !== $sortMethods && $this instanceof SortableTypeInterface) {
            $this->schemaPathProcessor->mapSorting($this, $sortMethods);
        }
        $defaultSortMethods = $this->schemaPathProcessor->processDefaultSortMethods($this);

        return [...$sortMethods, ...$defaultSortMethods];
    }
}
