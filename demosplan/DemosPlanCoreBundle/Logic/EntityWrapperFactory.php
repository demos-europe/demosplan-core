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

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\CacheableTypeAccessor;
use EDT\DqlQuerying\PropertyAccessors\ProxyPropertyAccessor;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Querying\Contracts\PropertyAccessorInterface;
use EDT\Querying\Utilities\ConditionEvaluator;
use EDT\Wrapping\Contracts\Types\ReadableTypeInterface;
use EDT\Wrapping\Utilities\CachingPropertyReader;
use EDT\Wrapping\Utilities\PropertyReader;
use EDT\Wrapping\Utilities\TypeAccessor;
use EDT\Wrapping\WrapperFactories\WrapperObject;
use EDT\Wrapping\WrapperFactories\WrapperObjectFactory;

/**
 * Service to wrap entities into an object that prevents access to properties not allowed by the
 * corresponding {@link ResourceTypeInterface}.
 */
class EntityWrapperFactory extends WrapperObjectFactory
{
    protected TypeAccessor $typeAccessor;

    protected PropertyAccessorInterface $propertyAccessor;

    protected PropertyReader $propertyReader;

    protected ConditionEvaluator $conditionEvaluator;

    public function __construct(
        CacheableTypeAccessor $typeAccessor,
        CachingPropertyReader $propertyReader,
        ConditionEvaluator $conditionEvaluator,
        ProxyPropertyAccessor $propertyAccessor
    ) {
        $this->typeAccessor = $typeAccessor;
        $this->propertyReader = $propertyReader;
        $this->conditionEvaluator = $conditionEvaluator;
        $this->propertyAccessor = $propertyAccessor;
        parent::__construct(
            $this->typeAccessor,
            $this->propertyReader,
            $this->propertyAccessor,
            $this->conditionEvaluator
        );
    }

    public function createWrapper(object $object, ReadableTypeInterface $type): WrapperObject
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
