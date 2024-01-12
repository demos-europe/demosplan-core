<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class DplanPermissions
{
    private readonly array $permissions;

    public function __construct(string|array $permissions)
    {
        if (is_string($permissions)) {
            $permissions = [$permissions];
        }

        $this->permissions = $permissions;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }
}
