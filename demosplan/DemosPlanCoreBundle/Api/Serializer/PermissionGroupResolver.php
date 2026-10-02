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
 * Turns the permission labels declared on API resource properties into the serialization groups
 * the current user is entitled to.
 *
 * A label has the form `perm:<read|write>:<permission>`, e.g. `perm:read:field_statement_memo`:
 *
 * - Several labels on one property mean "any of them": `['perm:read:a', 'perm:read:b']` needs a OR b.
 * - A `+` inside one label means "all of them": `perm:read:a+b` needs a AND b.
 * - A property without any `perm:` label is open to everyone who may use the resource.
 *
 * The labels are ordinary serializer groups, so the serializer itself decides which properties
 * leave the server; see {@see PermissionGroupsContextBuilder}.
 */
final class PermissionGroupResolver
{
    private const LABEL_PREFIX = 'perm:';

    public function __construct(
        private readonly ClassMetadataFactoryInterface $classMetadataFactory,
        private readonly CurrentUserInterface $currentUser,
    ) {
    }

    /**
     * @param class-string $class
     *
     * @return list<string> the permission labels of the class the current user is entitled to
     */
    public function getGrantedGroups(string $class, bool $forReading): array
    {
        $direction = $forReading ? 'read' : 'write';

        $granted = [];
        foreach ($this->classMetadataFactory->getMetadataFor($class)->getAttributesMetadata() as $attribute) {
            foreach ($this->getPermissionLabels($attribute->getGroups(), $direction) as $label) {
                if ($this->isGranted($label)) {
                    $granted[$label] = $label;
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
    public function canRead(string $class, string $property): bool
    {
        $attribute = $this->classMetadataFactory->getMetadataFor($class)->getAttributesMetadata()[$property] ?? null;
        $labels = $this->getPermissionLabels($attribute?->getGroups() ?? [], 'read');

        return [] === $labels || [] !== array_filter($labels, $this->isGranted(...));
    }

    /**
     * @param list<string> $groups
     *
     * @return list<string>
     */
    private function getPermissionLabels(array $groups, string $direction): array
    {
        $prefix = self::LABEL_PREFIX.$direction.':';

        return array_values(array_filter(
            $groups,
            static fn (string $group): bool => str_starts_with($group, $prefix)
        ));
    }

    /**
     * "perm:read:a+b" is granted if the user holds a AND b.
     */
    private function isGranted(string $label): bool
    {
        $permissions = explode('+', explode(':', $label, 3)[2]);

        foreach ($permissions as $permission) {
            if (!$this->currentUser->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }
}
