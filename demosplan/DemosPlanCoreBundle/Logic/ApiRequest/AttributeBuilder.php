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

use EDT\Querying\Contracts\PropertyPathInterface;
use League\Fractal\Scope;

class AttributeBuilder
{
    /**
     * @var array<string,mixed>
     */
    private $attributes = [];

    /**
     * @var array<string,callable>
     */
    private $optionals = [];

    public function __construct(private readonly Scope $scope, private readonly string $type)
    {
    }

    public function add(PropertyPathInterface $path, callable $callback): self
    {
        $this->attributes[$path->getAsNamesInDotNotation()] = $callback();

        return $this;
    }

    public function addOptional(PropertyPathInterface $path, callable $callback): self
    {
        $this->optionals[$path->getAsNamesInDotNotation()] = $callback;

        return $this;
    }

    /**
     * Returns all attributes added via {@link AttributeBuilder::add()}.
     *
     * Will additionally add all {@link AttributeBuilder::$optionals} to the result *if*
     * the corresponding field key was specifically requested.
     *
     * @return array<string,mixed> the attributes added to this builder, potentially with optional fields added
     */
    public function build(): array
    {
        $attributes = $this->attributes;
        $fieldsetBag = $this->scope->getManager()->getFieldset($this->type);
        if (null !== $fieldsetBag) {
            $fieldset = is_array($fieldsetBag) ? $fieldsetBag : iterator_to_array($fieldsetBag);
            foreach ($this->optionals as $optionalFieldName => $getter) {
                if (in_array($optionalFieldName, $fieldset, true)) {
                    $attributes[$optionalFieldName] = $getter();
                }
            }
        }

        return $attributes;
    }
}
