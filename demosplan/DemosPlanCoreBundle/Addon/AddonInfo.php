<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use DemosEurope\DemosplanAddon\Permission\PermissionInitializerInterface;

final class AddonInfo
{
    /**
     * @param array<string,mixed> $config
     */
    public function __construct(private string $name, private array $config, private PermissionInitializerInterface $permissionInitializer)
    {
    }

    public function isEnabled(): bool
    {
        return $this->config['enabled'];
    }

    public function getInstallPath(): string
    {
        return $this->config['install_path'];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPermissionInitializer(): PermissionInitializerInterface
    {
        return $this->permissionInitializer;
    }

    public function getControllerPaths(): array
    {
        return $this->config['manifest']['controller_paths'];
    }

    public function hasUIHooks(): bool
    {
        return array_key_exists('ui', $this->config['manifest']);
    }

    public function getUIHooks(): array
    {
        return $this->config['manifest']['ui'];
    }
}
