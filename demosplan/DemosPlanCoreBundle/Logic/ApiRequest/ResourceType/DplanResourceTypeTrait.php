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

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiResourceTypeService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Contracts\SortMethodFactoryInterface;
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
    protected ?MessageBagInterface $messageBag;
    protected ?ResourceTypeService $resourceTypeService;
    protected ?TranslatorInterface $translator;
    protected ?CustomerService $currentCustomerService;
    protected ?DqlConditionFactory $conditionFactory;
    protected ?SortMethodFactoryInterface $sortMethodFactory;
    protected ?JsonApiResourceTypeService $dplanResourceTypeService;
    protected ?ResourceTypeStore $resourceTypeStore;

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setDplanResourceTypeService(JsonApiResourceTypeService $dplanResourceTypeService): void
    {
        $this->dplanResourceTypeService = $dplanResourceTypeService;
    }

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     */
    #[Required]
    public function setResourceTypeStore(ResourceTypeStore $resourceTypeStore): void
    {
        $this->resourceTypeStore = $resourceTypeStore;
    }

    protected function getTypes(): ResourceTypeStore
    {
        return $this->resourceTypeStore;
    }

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
    public function setSortMethodFactory(SortMethodFactory $sortMethodFactory): void
    {
        $this->sortMethodFactory = $sortMethodFactory;
    }
}
