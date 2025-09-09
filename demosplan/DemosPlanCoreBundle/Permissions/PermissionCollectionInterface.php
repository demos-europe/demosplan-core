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

/**
 * Provides permission definitions.
 *
 * Does not provide information about the enabled/disabled state of a permission.
 */
interface PermissionCollectionInterface
{
    /**
     * @return array<non-empty-string, Permission>
     */
    public function toArray(): array;

    /**
     * @param non-empty-string $permissionKey the unique identifier of the permission, usually referred to as "name"
     */
    public function getPermission(string $permissionKey): Permission;

    /**
     * @param non-empty-string $permissionKey
     */
    public function containsPermission(string $permissionKey): bool;
}
