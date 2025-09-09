<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Symfony\Component\Yaml\Yaml;

final class AddonManifestCollection
{
    public const ADDONS_YAML = 'addons/addons.yaml';

    public static function load(): array
    {
        // uses local file, no need for flysystem
        if (!file_exists(DemosPlanPath::getRootPath(self::ADDONS_YAML))) {
            return [];
        }

        $addonsYaml = Yaml::parseFile(DemosPlanPath::getRootPath(self::ADDONS_YAML));

        if (!array_key_exists('addons', $addonsYaml)) {
            return [];
        }

        return $addonsYaml['addons'];
    }
}
