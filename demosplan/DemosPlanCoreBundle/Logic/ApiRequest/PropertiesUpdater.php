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

use function array_key_exists;

class PropertiesUpdater
{
    /**
     * @param array<string,mixed> $properties
     */
    public function __construct(private readonly array $properties)
    {
    }

    /**
     * @param callable $callback will be called with the value read from the properties
     */
    public function ifPresent(PropertyPathInterface $path, callable $callback): void
    {
        $pathString = $path->getAsNamesInDotNotation();
        if (array_key_exists($pathString, $this->properties)) {
            $value = $this->properties[$pathString];
            $callback($value);
        }

        // if needed we could return a boolean indicating if the property was set
        // or the result of the callback
    }
}
