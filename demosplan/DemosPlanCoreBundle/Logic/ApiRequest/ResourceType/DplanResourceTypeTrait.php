<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiResourceTypeService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PrefilledResourceTypeProvider;
use demosplan\DemosPlanCoreBundle\Logic\EntityWrapperFactory;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\JsonApi\RequestHandling\MessageFormatter;
use EDT\Querying\Contracts\SortMethodFactoryInterface;
use EDT\Wrapping\Contracts\TypeProviderInterface;
use EDT\Wrapping\WrapperFactories\WrapperObjectFactory;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Provides helper class properties and corresponding injection/initialization for {@link DplanResourceType}.
 *
 * Do not add any unrelated properties or methods to this trait.
 */
trait DplanResourceTypeTrait
{
    protected ?CurrentUserInterface $currentUser;
    protected ?CurrentProcedureService $currentProcedureService;
    protected ?GlobalConfigInterface $globalConfig;
    protected ?LoggerInterface $logger;
    protected ?ResourceTypeService $resourceTypeService;
    protected ?TranslatorInterface $translator;
    protected ?CustomerService $currentCustomerService;
    protected ?DqlConditionFactory $conditionFactory;
    protected ?TypeProviderInterface $typeProvider;
    protected ?WrapperObjectFactory $wrapperFactory;
    protected ?SortMethodFactoryInterface $sortMethodFactory;
    protected ?EventDispatcherInterface $eventDispatcher;
    protected ?MessageFormatter $messageFormatter;
    protected ?JsonApiResourceTypeService $dplanResourceTypeService;

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
    public function setResourceTypeService(ResourceTypeService $resourceTypeService): void
    {
        $this->resourceTypeService = $resourceTypeService;
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
    public function setEventDispatcher(TraceableEventDispatcher $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setJsonApiResourceTypeService(JsonApiResourceTypeService $jsonApiResourceTypeService): void
    {
        $this->dplanResourceTypeService = $jsonApiResourceTypeService;
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

    protected function getMessageFormatter(): MessageFormatter
    {
        if (!isset($this->messageFormatter)) {
            $this->messageFormatter = new MessageFormatter();
        }

        return $this->messageFormatter;
    }
}
