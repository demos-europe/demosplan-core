<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Permission;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;

readonly class CustomFieldAttributePermissionGuard
{
    public function __construct(private CurrentUserInterface $currentUser)
    {
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @throws AccessDeniedException
     */
    public function assertCreationAllowed(string $targetEntity, array $attributes): void
    {
        foreach ($this->getRestrictedAttributes() as $restrictedAttribute) {
            if (!$restrictedAttribute->appliesTo($targetEntity)) {
                continue;
            }

            $this->assertChangeAllowed(
                $restrictedAttribute->getPermission(),
                $attributes[$restrictedAttribute->getName()] ?? $restrictedAttribute->getDefaultValue(),
                $restrictedAttribute->getDefaultValue()
            );
        }
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @throws AccessDeniedException
     */
    public function assertUpdateAllowed(
        string $targetEntity,
        array $attributes,
        CustomFieldInterface $currentField,
    ): void {
        foreach ($this->getRestrictedAttributes() as $restrictedAttribute) {
            if (!$restrictedAttribute->appliesTo($targetEntity)) {
                continue;
            }

            $currentValue = $restrictedAttribute->getCurrentValue($currentField);
            $this->assertChangeAllowed(
                $restrictedAttribute->getPermission(),
                $attributes[$restrictedAttribute->getName()] ?? $currentValue,
                $currentValue
            );
        }
    }

    private function assertChangeAllowed(string $permission, mixed $requestedValue, mixed $currentValue): void
    {
        if ($requestedValue === $currentValue || $this->currentUser->hasPermission($permission)) {
            return;
        }

        throw AccessDeniedException::missingPermission($permission);
    }

    /**
     * @return list<AbstractRestrictedCustomFieldAttribute>
     */
    private function getRestrictedAttributes(): array
    {
        return [new IsRequiredRestrictedAttribute()];
    }
}
