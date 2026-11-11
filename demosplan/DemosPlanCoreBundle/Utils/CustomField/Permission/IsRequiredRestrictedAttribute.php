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

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;

class IsRequiredRestrictedAttribute extends AbstractRestrictedCustomFieldAttribute
{
    private const NAME = 'isRequired';
    private const PERMISSION = 'feature_statements_custom_fields_required';

    public function appliesTo(string $targetEntity): bool
    {
        return CustomFieldSupportedEntity::statement->value === $targetEntity;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getPermission(): string
    {
        return self::PERMISSION;
    }

    public function getDefaultValue(): bool
    {
        return false;
    }

    public function getCurrentValue(CustomFieldInterface $customField): bool
    {
        return $customField->getRequired();
    }
}
