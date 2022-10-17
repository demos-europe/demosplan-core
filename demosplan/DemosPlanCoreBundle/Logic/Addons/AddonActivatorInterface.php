<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Addons;

use demosplan\DemosPlanCoreBundle\Permissions\PermissionDecision;

interface AddonActivatorInterface
{
    /**
     * Get the new permissions introduced by this addon into the application.
     *
     * @return array<non-empty-string, PermissionDecision> mapping from the permission name to its default enabled state
     */
    public function getAddonPermissionsWithDefaults(): array;

    /**
     * @return non-empty-string
     */
    public function getPackageName(): string;
}
