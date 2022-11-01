<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType;

use Carbon\Carbon;
use function collect;
use DateTime;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\GetInternalPropertiesEvent;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\GetPropertiesEvent;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PrefilledResourceTypeProvider;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\TransformerLoader;
use demosplan\DemosPlanCoreBundle\Logic\EntityWrapperFactory;
use demosplan\DemosPlanCoreBundle\Logic\ILogic\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Logic\Logger\ApiLogger;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use demosplan\DemosPlanUserBundle\Logic\CustomerService;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\JsonApi\RequestHandling\MessageFormatter;
use EDT\JsonApi\ResourceTypes\CachingResourceType;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\PathBuilding\End;
use EDT\PathBuilding\PropertyAutoPathTrait;
use EDT\Querying\Contracts\PropertyPathInterface;
use EDT\Querying\Contracts\SortMethodFactoryInterface;
use EDT\Wrapping\Utilities\TypeAccessor;
use EDT\Wrapping\WrapperFactories\WrapperObjectFactory;
use function in_array;
use function is_array;
use IteratorAggregate;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @template T of object
 *
 * @template-extends CachingResourceType<T>
 *
 * @property-read End $id
 */
abstract class DplanResourceType extends CachingResourceType implements IteratorAggregate, PropertyPathInterface
{
    use PropertyAutoPathTrait;

    /**
     * @var CurrentUserInterface
     */
    protected $currentUser;
    /**
     * @var CurrentProcedureService
     */
    protected $currentProcedureService;
    /**
     * @var GlobalConfigInterface
     */
    protected $globalConfig;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var MessageBagInterface
     */
    protected $messageBag;
    /**
     * @var ResourceTypeService
     */
    protected $resourceTypeService;
    /**
     * @var TransformerLoader
     */
    protected $transformerLoader;
    /**
     * @var TranslatorInterface
     */
    protected $translator;
    /**
     * @var CustomerService
     */
    protected $currentCustomerService;
    /**
     * @var ConditionFactoryInterface
     */
    protected $conditionFactory;
    /**
     * @var TypeAccessor
     */
    protected $typeAccessor;

    /**
     * @var WrapperObjectFactory
     */
    protected $wrapperFactory;

    /**
     * @var SortMethodFactoryInterface
     */
    protected $sortMethodFactory;

    /**
     * @var ApiLogger
     */
    protected $apiLogger;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var MessageFormatter
     */
    private $messageFormatter;

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setCurrentProcedureService(CurrentProcedureService $currentProcedureService): void
    {
        $this->currentProcedureService = $currentProcedureService;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setCustomerService(CustomerService $customerService): void
    {
        $this->currentCustomerService = $customerService;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setCurrentUserService(CurrentUserInterface $currentUserService): void
    {
        $this->currentUser = $currentUserService;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setGlobalConfig(GlobalConfigInterface $globalConfig): void
    {
        $this->globalConfig = $globalConfig;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setMessageBag(MessageBagInterface $messageBag): void
    {
        $this->messageBag = $messageBag;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setResourceTypeService(ResourceTypeService $resourceTypeService): void
    {
        $this->resourceTypeService = $resourceTypeService;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setTransformerLoader(TransformerLoader $transformerLoader): void
    {
        $this->transformerLoader = $transformerLoader;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setConditionFactory(DqlConditionFactory $conditionFactory): void
    {
        $this->conditionFactory = $conditionFactory;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setTypeProvider(PrefilledResourceTypeProvider $typeProvider): void
    {
        $this->typeAccessor = new TypeAccessor($typeProvider);
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setWrapperFactory(EntityWrapperFactory $wrapperFactory): void
    {
        $this->wrapperFactory = $wrapperFactory;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setSortMethodFactory(SortMethodFactory $sortMethodFactory): void
    {
        $this->sortMethodFactory = $sortMethodFactory;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setApiLogger(ApiLogger $apiLogger): void
    {
        $this->apiLogger = $apiLogger;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @required
     */
    public function setEventDispatcher(TraceableEventDispatcher $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
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

        return $event->getProperties();
    }

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
     * @param PropertyPathInterface ...$propertyPaths
     *
     * @return array<string,string|null>
     */
    protected function toProperties(PropertyPathInterface ...$propertyPaths): array
    {
        return collect($propertyPaths)
            ->mapWithKeys(static function (PropertyPathInterface $propertyPath): array {
                $key = $propertyPath->getAsNamesInDotNotation();
                $value = $propertyPath instanceof ResourceTypeInterface
                    ? $propertyPath::getName()
                    : null;

                return [$key => $value];
            })->all();
    }

    protected function processProperties(array $properties): array
    {
        $event = new GetPropertiesEvent($this, $properties);
        $this->eventDispatcher->dispatch($event);

        return $event->getProperties();
    }

    protected function getWrapperFactory(): WrapperObjectFactory
    {
        return $this->wrapperFactory;
    }

    protected function getTypeAccessor(): TypeAccessor
    {
        return $this->typeAccessor;
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
        if (null === $this->messageFormatter) {
            $this->messageFormatter = new MessageFormatter();
        }

        return $this->messageFormatter;
    }
}
