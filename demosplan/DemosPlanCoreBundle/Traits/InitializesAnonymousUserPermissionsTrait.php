<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Traits;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;

/**
 * Trait for initializing anonymous user permissions in background/async execution contexts.
 *
 * Use this trait in console commands, message handlers, and scheduled tasks
 * where no authenticated user is available but permission checks are required.
 *
 * Classes using this trait must:
 * - Have a $permissions property of type PermissionsInterface
 * - Call $this->initializeAnonymousUserPermissions() before any permission checks
 *
 * Example usage:
 * ```php
 * #[AsMessageHandler]
 * class MyMessageHandler
 * {
 *     use InitializesAnonymousUserPermissionsTrait;
 *
 *     public function __construct(
 *         private readonly PermissionsInterface $permissions,
 *         // ... other dependencies
 *     ) {}
 *
 *     public function __invoke(MyMessage $message): void
 *     {
 *         $this->initializeAnonymousUserPermissions();
 *
 *         if ($this->permissions->hasPermission('my_permission')) {
 *             // ... do work
 *         }
 *     }
 * }
 * ```
 */
trait InitializesAnonymousUserPermissionsTrait
{
    /**
     * Initialize permissions with an anonymous user for background/async execution contexts.
     *
     * For console commands, message handlers, and scheduled tasks, no real user
     * is available. This method initializes permissions with an anonymous user
     * to enable permission checks based on global project permissions.
     */
    protected function initializeAnonymousUserPermissions(): void
    {
        $this->permissions->initPermissions(new AnonymousUser());
    }
}
