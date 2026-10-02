<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Serializer;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * Turns the permission names used as serialization groups on API resource properties into the
 * groups the current user is entitled to.
 *
 * A group that is the name of a defined permission is a permission group, e.g.
 * `#[Groups(['field_statement_memo'])]`. Every other group (like `statement:read`) is an
 * ordinary group and left alone:
 *
 * - Several groups on one property mean "any of them": `['a', 'b']` needs a OR b.
 * - A `+` inside one group means "all of them": `'a+b'` needs a AND b.
 * - A property without any permission group is open to everyone who may use the resource.
 *
 * Whether a property is about reading or writing is decided by the class it is declared on: the
 * class that is sent out holds the permissions to see its properties, the class that is received
 * holds the permissions to send them.
 *
 * The groups are ordinary serializer groups, so the serializer itself decides which properties
 * leave the server; see {@see PermissionGroupsContextBuilder}.
 */
final class PermissionGroupResolver
{
    public function __construct(
        private readonly ClassMetadataFactoryInterface $classMetadataFactory,
        private readonly CurrentUserInterface $currentUser,
    ) {
    }

    /**
     * @param class-string $class
     *
     * @return list<string> the permission groups of the class the current user is entitled to
     */
    public function getGrantedGroups(string $class): array
    {
        $granted = [];
        foreach ($this->classMetadataFactory->getMetadataFor($class)->getAttributesMetadata() as $attribute) {
            foreach ($this->getPermissionGroups($attribute->getGroups()) as $group) {
                if ($this->holdsAll($group)) {
                    $granted[$group] = $group;
                }
            }
        }

        return array_values($granted);
    }

    /**
     * Use this where a value is expensive to compute and should not be calculated for users who
     * would not see it anyway. Everything else is decided by the serializer.
     *
     * @param class-string $class
     */
    public function isPropertyAllowed(string $class, string $property): bool
    {
        $attribute = $this->classMetadataFactory->getMetadataFor($class)->getAttributesMetadata()[$property] ?? null;
        $permissionGroups = $this->getPermissionGroups($attribute?->getGroups() ?? []);

        return [] === $permissionGroups || [] !== array_filter($permissionGroups, $this->holdsAll(...));
    }

    /**
     * @param list<string> $groups
     *
     * @return list<string> the groups that consist only of defined permission names
     */
    private function getPermissionGroups(array $groups): array
    {
        $definedPermissions = $this->currentUser->getPermissions()->getPermissions();

        return array_values(array_filter(
            $groups,
            static function (string $group) use ($definedPermissions): bool {
                foreach (explode('+', $group) as $permission) {
                    if (!isset($definedPermissions[$permission])) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    /**
     * "a+b" is granted if the user holds a AND b.
     */
    private function holdsAll(string $group): bool
    {
        foreach (explode('+', $group) as $permission) {
            if (!$this->currentUser->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }
}
