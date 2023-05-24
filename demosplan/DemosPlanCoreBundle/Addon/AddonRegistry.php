<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use ArrayAccess;
use DemosEurope\DemosplanAddon\Permission\PermissionInitializerInterface;
use demosplan\DemosPlanCoreBundle\Exception\AddonException;

/**
 * This is the central information repository about all addons installed on this system and their configuration.
 *
 * @template-implements ArrayAccess<string, AddonInfo>
 */
class AddonRegistry implements ArrayAccess
{
    /** @var array<string, AddonInfo> */
    private array $addonInfos;

    public function __construct()
    {
        $this->addonInfos = [];
    }

    public function boot(array $addonInfos = [])
    {
        if ([] !== $this->addonInfos) {
            AddonException::immutableRegistry();
        }

        foreach ($addonInfos as $addonInfo) {
            $this->addonInfos[$addonInfo->getName()] = $addonInfo;
        }
    }

    public function getAddonInfos(): array
    {
        return $this->addonInfos;
    }


    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->addonInfos);
    }

    /**
     * @param string $offset
     */
    public function offsetGet(mixed $offset): AddonInfo
    {
        return $this->addonInfos[$offset];
    }

    /**
     * @param string    $offset
     * @param AddonInfo $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw AddonException::immutableRegistry();
    }

    /**
     * @param string $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        throw AddonException::immutableRegistry();
    }

    /**
     * @return PermissionInitializerInterface[]
     */
    public function getPermissionInitializers(): array
    {
        return array_map(fn (AddonInfo $addonInfo) => $addonInfo->getPermissionInitializer(), $this->addonInfos);
    }
}
