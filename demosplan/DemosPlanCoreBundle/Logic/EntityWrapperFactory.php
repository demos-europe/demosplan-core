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

use demosplan\DemosPlanCoreBundle\Entity\EntityInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\CacheableTypeAccessor;
use Doctrine\Persistence\ManagerRegistry;
use EDT\DqlQuerying\PropertyAccessors\ProxyPropertyAccessor;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Querying\Contracts\PropertyAccessorInterface;
use EDT\Querying\Utilities\ConditionEvaluator;
use EDT\Wrapping\Contracts\TypeProviderInterface;
use EDT\Wrapping\Contracts\Types\ReadableTypeInterface;
use EDT\Wrapping\Contracts\WrapperFactoryInterface;
use EDT\Wrapping\Utilities\CachingPropertyReader;
use EDT\Wrapping\Utilities\PropertyReader;
use EDT\Wrapping\Utilities\SchemaPathProcessor;
use EDT\Wrapping\Utilities\TypeAccessor;

/**
 * Service to wrap entities into an object that prevents access to properties not allowed by the
 * corresponding {@link ResourceTypeInterface}.
 *
 * @template-implements WrapperFactoryInterface<EntityInterface, TwigableWrapperObject>
 */
class EntityWrapperFactory implements WrapperFactoryInterface
{
    /**
     * @var TypeAccessor
     */
    private $typeAccessor;
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @var PropertyReader
     */
    private $propertyReader;

    /**
     * @var ConditionEvaluator
     */
    private $conditionEvaluator;

    public function __construct(
        ManagerRegistry $managerRegistry,
        SchemaPathProcessor $schemaPathProcessor,
        TypeProviderInterface $typeProvider
    ) {
        $this->propertyAccessor = new ProxyPropertyAccessor($managerRegistry->getManager());
        $this->propertyReader = new CachingPropertyReader($this->propertyAccessor, $schemaPathProcessor);
        $this->typeAccessor = new CacheableTypeAccessor($typeProvider);
        $this->conditionEvaluator = new ConditionEvaluator($this->propertyAccessor);
    }

    public function createWrapper(object $object, ReadableTypeInterface $type): TwigableWrapperObject
    {
        return new TwigableWrapperObject(
            $object,
            $this->propertyReader,
            $type,
            $this->typeAccessor,
            $this->propertyAccessor,
            $this->conditionEvaluator,
            $this
        );
    }
}
