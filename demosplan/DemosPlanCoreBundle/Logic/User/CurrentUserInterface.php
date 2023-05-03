<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;

interface CurrentUserInterface
{
    /**
     * @throws UserNotFoundException
     */
    public function getUser(): User;

    public function setUser(User $user, Customer $customer = null): void;

    public function getPermissions(): PermissionsInterface;

    /**
     * @throws UserNotFoundException
     */
    public function hasPermission(string $permission): bool;

    /**
     * Determines if any of the given permissions is currently enabled for the current user.
     */
    public function hasAnyPermissions(string ...$permissions): bool;

    /**
     * Determines if all of the given permission are currently enabled for the current user.
     */
    public function hasAllPermissions(string ...$permissions): bool;
}
