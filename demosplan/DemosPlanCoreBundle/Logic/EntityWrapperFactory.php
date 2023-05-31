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

use EDT\DqlQuerying\PropertyAccessors\ProxyPropertyAccessor;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Querying\Contracts\PropertyAccessorInterface;
use EDT\Querying\Utilities\ConditionEvaluator;
use EDT\Wrapping\Contracts\Types\TransferableTypeInterface;
use EDT\Wrapping\Utilities\CachingPropertyReader;
use EDT\Wrapping\Utilities\PropertyReader;
use EDT\Wrapping\WrapperFactories\WrapperObject;
use EDT\Wrapping\WrapperFactories\WrapperObjectFactory;

/**
 * Service to wrap entities into an object that prevents access to properties not allowed by the
 * corresponding {@link ResourceTypeInterface}.
 */
class EntityWrapperFactory extends WrapperObjectFactory
{
    protected PropertyAccessorInterface $propertyAccessor;

    protected PropertyReader $propertyReader;

    protected ConditionEvaluator $conditionEvaluator;

    public function __construct(
        CachingPropertyReader $propertyReader,
        ConditionEvaluator $conditionEvaluator,
        ProxyPropertyAccessor $propertyAccessor
    ) {
        $this->propertyReader = $propertyReader;
        $this->conditionEvaluator = $conditionEvaluator;
        $this->propertyAccessor = $propertyAccessor;
        parent::__construct(
            $this->propertyReader,
            $this->propertyAccessor,
            $this->conditionEvaluator
        );
    }

    public function createWrapper(object $object, TransferableTypeInterface $type): WrapperObject
    {
        return new TwigableWrapperObject(
            $object,
            $this->propertyReader,
            $type,
            $this->propertyAccessor,
            $this->conditionEvaluator,
            $this
        );
    }
}
