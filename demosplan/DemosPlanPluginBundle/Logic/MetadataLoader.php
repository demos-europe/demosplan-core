<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanPluginBundle\Logic;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\DemosPlanPluginBundle\Exception\PluginException;

/**
 * Loads plugin metadata found in the following files:
 * - plugin.json
 * This class is heavily inspired by Piwik.
 */
class MetadataLoader
{
    const PLUGIN_JSON_FILENAME = 'composer.json';

    /**
     * The name of the plugin whose metadata will be loaded.
     *
     * @var string
     */
    private $pluginName;

    /**
     * Constructor.
     *
     * @param string $pluginName name of the plugin to load metadata
     */
    public function __construct($pluginName)
    {
        $this->pluginName = $pluginName;
    }

    /**
     * Loads plugin metadata. @see Plugin::getInformation.
     *
     * @return array
     */
    public function load()
    {
        $defaults = $this->getDefaultPluginInformation();
        $plugin = $this->loadPluginInfoJson();

        // look for a license file
        $licenseFile = $this->getPathToLicenseFile();
        if (!empty($licenseFile)) {
            $plugin['license_file'] = $licenseFile;
        }

        return array_merge(
            $defaults,
            $plugin
        );
    }

    /**
     * @return bool
     */
    public function hasPluginJson()
    {
        $hasJson = $this->loadPluginInfoJson();

        return !empty($hasJson);
    }

    /**
     * @return array
     */
    private function getDefaultPluginInformation()
    {
        $descriptionKey = $this->pluginName.'_PluginDescription';

        return [
            'description' => $descriptionKey,
            'homepage'    => '',
            'authors'     => [['name' => 'me', 'homepage' => '']],
            'license'     => '',
            'version'     => '',
            'theme'       => false,
            'require'     => [],
        ];
    }

    /**
     * It is important that this method works without using anything from DI.
     *
     * @return array|mixed
     */
    public function loadPluginInfoJson()
    {
        $path = $this->getPathToPluginJson();

        return $this->loadJsonMetadata($path);
    }

    public function getPathToPluginJson()
    {
        return $this->getPathToPluginFolder().'/'.self::PLUGIN_JSON_FILENAME;
    }

    private function loadJsonMetadata($path)
    {
        if (!file_exists($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if (!$json) {
            return [];
        }

        $info = Json::decodeToArray($json);
        if (!is_array($info)
            || empty($info)
        ) {
            throw PluginException::invalidComposerJsonException($path);
        }

        return $info;
    }

    /**
     * @return string
     */
    private function getPathToPluginFolder()
    {
        return DemosPlanPath::getPluginPath($this->pluginName);
    }

    /**
     * @return string|null
     */
    public function getPathToLicenseFile()
    {
        $prefixPath = $this->getPathToPluginFolder().'/';
        $licenseFiles = [
            'LICENSE',
            'LICENSE.md',
            'LICENSE.txt',
        ];
        foreach ($licenseFiles as $licenseFile) {
            $pathToLicense = $prefixPath.$licenseFile;
            if (is_file($pathToLicense) && is_readable($pathToLicense)) {
                return $pathToLicense;
            }
        }

        return null;
    }
}
