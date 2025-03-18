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

class AddonInfo
{
    public const DEFAULT_CONTROLLER_PATH = '/src/Controller';

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(private readonly string $name, private readonly array $config, private readonly PermissionInitializerInterface $permissionInitializer)
    {
    }

    public function isEnabled(bool $dynamicOverride = true): bool
    {
        // when the permissionInitializer of the addon has a method isEnabled, call it
        if ($dynamicOverride && method_exists($this->permissionInitializer, 'isEnabled')) {
            return $this->permissionInitializer->isEnabled();
        }

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
        return $this->config['manifest']['controller_paths'] ?? [self::DEFAULT_CONTROLLER_PATH];
    }

    public function hasUIHooks(): bool
    {
        return array_key_exists('ui', $this->config['manifest']);
    }

    public function getUIHooks(): array
    {
        return $this->config['manifest']['ui'];
    }

    public function getVersion(): string
    {
        return $this->config['version'];
    }
}
