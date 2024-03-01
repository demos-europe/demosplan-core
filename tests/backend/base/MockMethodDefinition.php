<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Base;

use Closure;

class MockMethodDefinition
{
    /**
     * @var string
     */
    private $method;

    private $returnValue;

    /**
     * @var string|null
     */
    private $propertyName;

    /**
     * @var bool
     */
    private $returnValueCallback;

    /**
     * @param mixed|null  $returnValue  the value directly to return when the method is called
     * @param string|null $propertyName set to `null`, to not generate a property on the mock
     *
     * @internal use {@link MockMethodDefinition::withReturnValue} instead for consistency with
     *             {@link MockMethodDefinition::withCalledReturn}
     */
    public function __construct(string $method, $returnValue, string $propertyName = null)
    {
        $this->method = $method;
        $this->returnValue = $returnValue;
        $this->propertyName = $propertyName;
        $this->returnValueCallback = $returnValue instanceof Closure;
    }

    /**
     * @param mixed|null  $returnValue  the value directly to return when the method is called
     * @param string|null $propertyName set to `null`, to not generate a property on the mock
     */
    public static function withReturnValue(
        string $methodName,
        $returnValue,
        string $propertyName = null
    ) {
        return new self($methodName, $returnValue, $propertyName);
    }

    /**
     * @param mixed|null  $returnCallable the callable invoked to determine the value to return
     *                                    when the method is called
     * @param string|null $propertyName   set to `null`, to not generate a property on the mock
     */
    public static function withCalledReturn(
        string $method,
        callable $returnCallable,
        string $propertyName = null
    ): self {
        $self = new self($method, $returnCallable, $propertyName);
        $self->returnValueCallback = true;

        return $self;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getReturnValue()
    {
        return $this->returnValue;
    }

    public function getPropertyName(): ?string
    {
        return $this->propertyName;
    }

    public function isReturnValueCallback(): bool
    {
        return $this->returnValueCallback;
    }
}
