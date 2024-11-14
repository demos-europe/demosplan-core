<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use Carbon\Carbon;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Events\GetPropertiesEventInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\JsonApiResourceTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\JsonApiResourceTypeServiceInterface;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use Doctrine\ORM\EntityManagerInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Querying\ObjectProviders\PrefilledEntityProvider;
use EDT\Querying\Utilities\ConditionEvaluator;
use EDT\Querying\Utilities\Iterables;
use EDT\Querying\Utilities\Sorter;
use EDT\Wrapping\Contracts\TypeProviderInterface;
use EDT\Wrapping\Utilities\SchemaPathProcessor;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * This class is intended as helper class for the {@link DplanResourceType} and {@link AddonResourceType} only to
 * reduce code duplication.
 *
 * Do not use this class or its method in any non-resource-type class.
 */
class JsonApiResourceTypeService implements JsonApiResourceTypeServiceInterface
{
    public function __construct(
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly TypeProviderInterface $typeProvider,
        protected readonly SchemaPathProcessor $schemaPathProcessor,
        protected readonly ConditionEvaluator $conditionEvaluator,
        protected readonly Sorter $sorter,
        protected readonly DqlConditionFactory $conditionFactory,
        protected readonly MessageBagInterface $messageBag,
        protected readonly EntityManagerInterface $entityManager
    ) {
    }

    public function listPrefilteredEntities(JsonApiResourceTypeInterface $type, array $dataObjects, array $conditions, array $sortMethods): array
    {
        $entityProvider = new PrefilledEntityProvider($this->conditionEvaluator, $this->sorter, $dataObjects);
        $entities = $entityProvider->getEntities($conditions, $sortMethods, null);
        $entities = Iterables::asArray($entities);

        return array_values($entities);
    }

    public function formatDate(?DateTime $date): ?string
    {
        if (null === $date) {
            return null;
        }

        return Carbon::instance($date)->toIso8601String();
    }

    public function processProperties(JsonApiResourceTypeInterface $type, ResourceConfigBuilderInterface $resourceConfigBuilder): ResourceConfigBuilderInterface
    {
        $event = new GetPropertiesEvent($type, $resourceConfigBuilder);
        $this->eventDispatcher->dispatch($event, GetPropertiesEventInterface::class);

        return $event->getConfigBuilder();
    }

    /**
     * @throws MessageBagException
     */
    public function addCreationErrorMessage(array $parameters): void
    {
        $this->messageBag->add('error', 'error.api.generic');
    }
}
