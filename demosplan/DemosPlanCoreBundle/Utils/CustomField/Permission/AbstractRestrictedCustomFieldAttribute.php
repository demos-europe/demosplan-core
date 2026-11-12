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

abstract class AbstractRestrictedCustomFieldAttribute
{
    abstract public function appliesTo(string $targetEntity): bool;

    abstract public function getName(): string;

    abstract public function getPermission(): string;

    abstract public function getDefaultValue(): mixed;

    abstract public function getCurrentValue(CustomFieldInterface $customField): mixed;
}
