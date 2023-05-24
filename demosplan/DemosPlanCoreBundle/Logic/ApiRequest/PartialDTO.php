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

use InvalidArgumentException;

/**
 * Keep this class simple, we expect many thousands of these to be created in a single request.
 */
class PartialDTO
{
    /**
     * @var array<string,mixed>
     */
    private $properties;

    public function __construct(array $properties)
    {
        $this->properties = $properties;
    }

    public function hasProperty(string $property): bool
    {
        return array_key_exists($property, $this->properties);
    }

    /**
     * Will throw an error if the property is not available. Use {@link PartialDTO::hasProperty()}
     * if you're unsure.
     *
     * @return mixed the value of the property
     *
     * @throws InvalidArgumentException if the given property was not loaded
     */
    public function getProperty(string $property)
    {
        if (!array_key_exists($property, $this->properties)) {
            throw new InvalidArgumentException("Value for property '$property' unknown, as it was not loaded.");
        }

        return $this->properties[$property];
    }
}
