<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;

class AccessDeniedException extends \Symfony\Component\Security\Core\Exception\AccessDeniedException
{
    /**
     * @param array<int, string> $permissions
     */
    public static function missingPermissions(User $user = null, array $permissions = []): self
    {
        $additionalUserData = '';

        if ($user instanceof User) {
            $roles = DemosPlanTools::varExport($user->getRoles(), true);
            $additionalUserData = "User: {$user->getLastname()} UserId: {$user->getId()} Roles: $roles";
        }

        if ([] !== $permissions) {
            if ('' !== $additionalUserData) {
                $additionalUserData .= ' ';
            }
            $permissionsString = implode(', ', $permissions);
            $additionalUserData .= "Permissions: $permissionsString";
        }

        return new self("Der Zugriff ist nicht gestattet $additionalUserData");
    }

    public static function missingPermission(string $permission, User $user = null): self
    {
        $additionalUserData = '';

        if ($user instanceof User) {
            $additionalUserData = 'User: '.
                $user->getLastname().' UserId: '.
                $user->getId().' Roles: '.DemosPlanTools::varExport(
                    $user->getRoles(),
                    true
                );
        }

        return new self("Der Zugriff auf {$permission} ist nicht gestattet. {$additionalUserData}");
    }

    /**
     * @param string $resourceName the name of the resource that was to be updated
     * @param array  $attributes   the attributes the write access was denied to
     *
     * @return static
     */
    public static function deniedWriteAccessToAttributes(string $resourceName, array $attributes): self
    {
        $deniedAttributesString = implode('|', $attributes);

        return new AccessDeniedException("Write access to {$resourceName} attributes not allowed: {$deniedAttributesString}");
    }
}
