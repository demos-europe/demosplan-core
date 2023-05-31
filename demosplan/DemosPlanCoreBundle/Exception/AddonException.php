<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use RuntimeException;

class AddonException extends RuntimeException
{
    public static function missing(string $addonName): self
    {
        return new self("The requested addon `{$addonName}` is not installed.");
    }

    public static function invalidType(string $name, string $type): self
    {
        return new self("Cannot install {$name} as demosplan Addon, given type `{$type}` is invalid");
    }

    public static function invalidManifest(string $addonName): self
    {
        return new self("Manifest for `{$addonName}` does not exist or is invalid");
    }

    public static function manifestEntryNotFound(string $entryName): self
    {
        return new self(sprintf('No entry found in manifest with name: "%s"', $entryName));
    }

    public static function immutableRegistry(): self
    {
        return new self('The addon registry is immutable after booting.');
    }

    public static function alreadyInstalled(): self
    {
        return new self("Du'h, it's already there.");
    }
}
