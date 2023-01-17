<?php

namespace demosplan\DemosPlanCoreBundle\Addon;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Symfony\Component\Yaml\Yaml;

// TODO: find a better name for this!!!
final class AddonManifestCollection
{
    private const ADDONS_YAML = 'addons/addons.yaml';

    public static function load(): array
    {
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
