<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

/**
 * Handles all exceptions related to composer package information.
 */
class ComposerPackageException extends \InvalidArgumentException
{
    /**
     * Composer packages must have a type. If they don't, this exception is needed.
     */
    public static function typeMissing(string $packageName): self
    {
        return new self(sprintf('The package %s has no type', $packageName));
    }
}
