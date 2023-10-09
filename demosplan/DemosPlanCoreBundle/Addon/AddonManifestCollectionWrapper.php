<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

/**
 * Helper class to be able to test Classes that use the static method
 * AddonManifestCollection::load().
 */
class AddonManifestCollectionWrapper
{
    public function load(): array
    {
        return AddonManifestCollection::load();
    }
}
