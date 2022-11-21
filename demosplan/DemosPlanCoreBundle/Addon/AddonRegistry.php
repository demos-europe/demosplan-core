<?php


declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Symfony\Component\Yaml\Yaml;
use Tightenco\Collect\Support\Collection;

/**
 * This is the central information repository about all addons installed on this system and their configuration.
 */
class AddonRegistry
{
    private Collection $addons;

    public function __construct()
    {
        $this->loadAddonInformation();
    }

    /**
     * Reads addon information from the configuration file for all installed addons.
     */
    private function loadAddonInformation(): void
    {
        $this->addons = \collect([]);
        if (file_exists(DemosPlanPath::getRootPath('addons/addons.yaml'))) {
            $configFile = Yaml::parseFile(DemosPlanPath::getRootPath('addons/addons.yaml'));
            if (is_array($configFile) && array_key_exists('addons', $configFile) && is_array($configFile['addons'])) {
                $this->addons = \collect($configFile['addons']);
            }
        }
    }

    /**
     * Returns all available addons.
     */
    public function getAllAddons(): Collection
    {
        return $this->addons;
    }

    /**
     * returns only enabled addons.
     */
    public function getEnabledAddons(): Collection
    {
        return $this->addons->filter(function ($addon) {
            return $addon['enabled'];
        });
    }

    /**
     * returns only disabled addons.
     */
    public function getDisabledAddons(): Collection
    {
        return $this->addons->filter(function ($addon) {
            return !$addon['enabled'];
        });
    }
}
