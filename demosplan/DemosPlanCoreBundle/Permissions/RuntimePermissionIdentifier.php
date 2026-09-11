<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

use DemosEurope\DemosplanAddon\Permission\PermissionIdentifierInterface;

/**
 * Identifies a permission whose name is only known at runtime, e.g. because it was read from an
 * addon manifest. Permissions known at compile time are to be identified by an enum implementing
 * {@link PermissionIdentifierInterface} instead.
 */
final readonly class RuntimePermissionIdentifier implements PermissionIdentifierInterface
{
    /**
     * @param non-empty-string      $permissionName
     * @param non-empty-string|null $addonIdentifier
     */
    private function __construct(
        private string $permissionName,
        private ?string $addonIdentifier
    ) {
    }

    /**
     * @param non-empty-string $permissionName
     */
    public static function forCore(string $permissionName): self
    {
        return new self($permissionName, null);
    }

    /**
     * @param non-empty-string $permissionName
     * @param non-empty-string $addonIdentifier
     */
    public static function forAddon(string $permissionName, string $addonIdentifier): self
    {
        return new self($permissionName, $addonIdentifier);
    }

    public function getAddonIdentifier(): ?string
    {
        return $this->addonIdentifier;
    }

    public function getPermissionName(): string
    {
        return $this->permissionName;
    }
}
