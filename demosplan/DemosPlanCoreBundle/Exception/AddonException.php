<?php

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

    public static function unresolvableClass(string $class): self
    {
        return new self("Probable addon class `{$class}` could not be resolved by addon class loading.");
    }
}
