<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanPluginBundle\Logic;

use demosplan\DemosPlanCoreBundle\Logic\DemosFilesystem;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\DemosPlanPluginBundle\Exception\ManagePluginException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Tightenco\Collect\Support\Collection;

/**
 * Lists the currently activated plugins.
 *
 * This class is heavily inspired by Piwik
 */
class PluginList
{
    /** @var string */
    protected $pluginsFilePath;

    /** @var string */
    protected $dplanRootPath;

    public function __construct()
    {
        $this->pluginsFilePath = DemosPlanPath::getProjectPath('app/config/plugins.yml');
        $this->dplanRootPath = DemosPlanPath::getRootPath();
    }

    /**
     * Allow Path to be overridden in test.
     *
     * @param string $dplanRootPath
     */
    public function setDplanRootPath($dplanRootPath)
    {
        $this->dplanRootPath = $dplanRootPath;
    }

    /**
     * Allow Path to be overridden in test.
     *
     * @param string $pluginsFilePath
     */
    public function setPluginsFilePath($pluginsFilePath)
    {
        $this->pluginsFilePath = $pluginsFilePath.'/app/config/plugins.yml';
    }

    /**
     * Returns the list of all known plugins.
     */
    public function getAllPlugins(): Collection
    {
        $finder = Finder::create()
            ->in(DemosPlanPath::getRootPath('demosplan/plugins'))
            ->name('composer.json')
            ->files();

        $plugins = [];

        $enabledPlugins = $this->getEnabledPlugins();

        /** @var \Symfony\Component\Finder\SplFileInfo $composerFile */
        foreach ($finder as $composerFile) {
            $composerContent = Json::decodeToMatchingType($composerFile->getContents());
            $plugins[] = [
                'name'        => $composerContent->name,
                'description' => $composerContent->description,
                'version'     => $composerContent->version,
                'enabled'     => $enabledPlugins->has($composerContent->name),
            ];
        }

        return collect($plugins)->sortBy('name');
    }

    /**
     * Returns the list of plugins that should be loaded. Used by the container factory to
     * load plugin specific DI overrides.
     */
    public function getEnabledPlugins(): Collection
    {
        $enabledProjectPlugins = $this->getEnabledProjectPlugins();

        // add corePlugins
        return $enabledProjectPlugins->merge($this->getCorePlugins());
    }

    /**
     * Return a config/bundles.php formatted array of all enabled plugin bundles.
     */
    public static function getBundles(): array
    {
        $pluginList = new self();

        return $pluginList->getEnabledPlugins()->flatMap(static function ($className) {
            return [$className => ['all' => true]];
        })->toArray();
    }

    /**
     * Returns the plugins bundled with core package that are disabled by default.
     */
    public function getCorePluginsDisabledByDefault(): Collection
    {
        return collect();
    }

    /**
     * Returns the plugins bundled with core package.
     */
    public function getCorePlugins(): Collection
    {
        return collect([
            'SegmentsManager' => \demosplan\plugins\workflow\SegmentsManager\SegmentsManager::class,
        ]);
    }

    /**
     * Is given plugin activated in this project?
     *
     * @param string $pluginName
     */
    public function hasEnabledPlugin($pluginName): bool
    {
        return $this->getEnabledPlugins()->has($pluginName);
    }

    /**
     * Validate plugin by Pluginname.
     *
     * @param string $pluginName
     *
     * @return bool is valid plugin
     */
    public function isValidPlugin($pluginName): bool
    {
        $pluginClass = $this->getClassFromPlugin($pluginName);
        if (!class_exists($pluginClass)) {
            return false;
        }

        return true;
    }

    /**
     * Get Class name the plugin should have derived from plugin Name.
     *
     * @param string $pluginName
     */
    protected function getClassFromPlugin($pluginName): string
    {
        $pluginClass = $pluginName;
        if (false !== strpos($pluginName, '/')) {
            $pluginPieces = explode('/', $pluginName);
            $pluginClass = $pluginPieces[count($pluginPieces) - 1];
            $pluginName = str_replace('/', '\\', $pluginName);
        }

        return 'demosplan\plugins\\'.$pluginName.'\\'.$pluginClass;
    }

    /**
     * Enable plugin in project.
     *
     * @param string $pluginName
     */
    public function enablePlugin($pluginName): bool
    {
        $enabledPlugins = $this->getEnabledProjectPlugins();
        $class = $this->getClassFromPlugin($pluginName);
        if ($enabledPlugins->search($class)) {
            throw new ManagePluginException('Plugin already enabled');
        }

        if (!class_exists($class)) {
            throw new ManagePluginException('Could not find plugin');
        }

        $this->addPlugin($pluginName, $class);

        return true;
    }

    /**
     * Disable plugin in project.
     *
     * @param string $pluginName
     */
    public function disablePlugin($pluginName): bool
    {
        $this->checkConfigFile();

        $class = $this->getClassFromPlugin($pluginName);
        $enabledPlugins = $this->getEnabledProjectPlugins();
        if (!$enabledPlugins->search($class)) {
            throw new ManagePluginException('Plugin was not enabled');
        }

        $configFile = Yaml::parseFile($this->pluginsFilePath);
        $initialPluginCount = count($configFile['PluginsEnabled']);

        // remove plugin from List of enabled plugins by its name
        $configFile['PluginsEnabled'] = collect($configFile['PluginsEnabled'])->reject(function ($value, $key) use ($pluginName) {
            return $key === $pluginName;
        })->toArray();

        if ($initialPluginCount > count($configFile['PluginsEnabled'])) {
            $fs = new DemosFilesystem();
            $fs->dumpFile($this->pluginsFilePath, Yaml::dump($configFile));

            return true;
        }

        throw new ManagePluginException('Plugin was not enabled');
    }

    /**
     * Get enabled project plugins.
     */
    protected function getEnabledProjectPlugins(): Collection
    {
        $cacheKey = 'plugins_enabled';
        if (DemosPlanTools::cacheExists($cacheKey)) {
            return DemosPlanTools::cacheGet($cacheKey);
        }
        $return = collect([]);
        $this->checkConfigFile();
        $configFile = Yaml::parseFile($this->pluginsFilePath);
        if (is_array($configFile) && array_key_exists('PluginsEnabled', $configFile) && is_array($configFile['PluginsEnabled'])) {
            $return = collect($configFile['PluginsEnabled']);
        }

        DemosPlanTools::cacheAdd($cacheKey, $return, 10);

        return $return;
    }

    /**
     * Adds a plugin to the plugin list.
     *
     * @param string $pluginName
     * @param string $class
     */
    protected function addPlugin($pluginName, $class)
    {
        $this->checkConfigFile();
        $enabledPlugins = Yaml::parseFile($this->pluginsFilePath);

        $enabledPlugins['PluginsEnabled'][$pluginName] = $class;
        $fs = new DemosFilesystem();
        $fs->dumpFile($this->pluginsFilePath, Yaml::dump($enabledPlugins));
    }

    /**
     * Check whether plugin list exists, create it if not.
     */
    protected function checkConfigFile()
    {
        $fs = new DemosFilesystem();
        if (!$fs->exists($this->pluginsFilePath)) {
            $fs->dumpFile($this->pluginsFilePath, "PluginsEnabled:\n");
        }
    }
}
