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
use demosplan\DemosPlanCoreBundle\Traits\DI\RequiresLoggerTrait;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\BadMethodCallException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Base class of all Plugin Descriptor classes.
 *
 * Class implementations should be named after the plugin they are a part of
 * (eg, `class UserCountry extends Plugin`).
 *
 * ### Plugin Metadata
 *
 * This class is responsible for loading metadata
 * found in the plugin.json file.
 *
 * The plugin.json file must exist in the root directory of a plugin. It can
 * contain the following information:
 *
 * - **description**: An internationalized string description of what the plugin
 *                    does.
 * - **homepage**: The URL to the plugin's website.
 * - **authors**: A list of author arrays with keys for 'name', 'email' and 'homepage'
 * - **license**: The license the code uses (eg, GPL, MIT, etc.).
 * - **version**: The plugin version (eg, 1.0.1).
 *
 * This class is heavily inspired by Piwik
 */
class Plugin extends Bundle implements ExtensionInterface
{
    use RequiresLoggerTrait;

    /**
     * Name of this plugin.
     *
     * @var string
     */
    protected $pluginName;

    /**
     * Holds plugin metadata.
     *
     * @var array
     */
    private $pluginInformation;

    /**
     * Constructor.
     *
     * @param string|bool $pluginName A plugin name to force. If not supplied, it is set
     *                                to the last part of the class name.
     *
     * @throws \Exception If plugin metadata is defined in both the getInformation() method
     *                    and the **plugin.json** file.
     */
    public function __construct($pluginName = false)
    {
        if (empty($pluginName)) {
            $pluginName = explode('\\', get_class($this));
            $pluginName = end($pluginName);
        }
        $this->pluginName = $pluginName;

        $cacheId = 'Plugin'.$pluginName.'Metadata';

        if (DemosPlanTools::cacheGet($cacheId)) {
            $this->pluginInformation = DemosPlanTools::cacheGet($cacheId);
        } else {
            $this->reloadPluginInformation();
            DemosPlanTools::cacheAdd($cacheId, $this->pluginInformation, 3600);
        }
    }

    public function reloadPluginInformation()
    {
        $metadataLoader = new MetadataLoader($this->pluginName);
        $this->pluginInformation = $metadataLoader->load();
    }

    /**
     * Returns plugin information, including:.
     *
     * - 'description' => string        // 1-2 sentence description of the plugin
     * - 'author' => string             // plugin author
     * - 'author_homepage' => string    // author homepage URL (or email "mailto:youremail@example.org")
     * - 'homepage' => string           // plugin homepage URL
     * - 'license' => string            // plugin license
     * - 'version' => string            // plugin version number; examples and 3rd party plugins must not use Version::VERSION; 3rd party plugins must increment the version number with each plugin release
     *
     * @return array
     *
     * @deprecated
     */
    public function getInformation()
    {
        return $this->pluginInformation;
    }

    /**
     * This method is executed after a plugin is loaded and translations are registered.
     * Useful for initialization code that uses translated strings.
     */
    public function postLoad()
    {
    }

    /**
     * Installs the plugin. Derived classes should implement this class if the plugin
     * needs to:.
     *
     * - create tables
     * - update existing tables
     * - etc.
     *
     * @throws \Exception if installation of fails for some reason
     */
    public function install()
    {
    }

    /**
     * Uninstalls the plugins. Derived classes should implement this method if the changes
     * made in {@link install()} need to be undone during uninstallation.
     *
     * In most cases, if you have an {@link install()} method, you should provide
     * an {@link uninstall()} method.
     *
     * @throws \Exception if uninstallation of fails for some reason
     */
    public function uninstall()
    {
    }

    /**
     * Executed every time the plugin is enabled.
     */
    public function activate()
    {
    }

    /**
     * Executed every time the plugin is disabled.
     */
    public function deactivate()
    {
    }

    /**
     * Returns the plugin version number.
     *
     * @return string
     */
    final public function getVersion()
    {
        $info = $this->getInformation();

        return $info['version'];
    }

    /**
     * Returns the plugin's base class name without the namespace,
     * e.g., `"XBau"` when the plugin class is `"demosplan\plugins\XBau\XBau"`.
     *
     * @return string
     */
    final public function getPluginName()
    {
        return $this->pluginName;
    }

    /**
     * Tries to find a component such as a Menu or Tasks within this plugin.
     *
     * @param string $componentName    The name of the component you want to look for. In case you request a
     *                                 component named 'Menu' it'll look for a file named 'Menu.php' within the
     *                                 root of the plugin folder that implements a class named
     *                                 demosplan\plugin\$PluginName\Menu . If such a file exists but does not implement
     *                                 this class it'll silently ignored.
     * @param string $expectedSubclass If not empty, a check will be performed whether a found file extends the
     *                                 given subclass. If the requested file exists but does not extend this class
     *                                 a warning will be shown to advice a developer to extend this certain class.
     *
     * @return string|null null if the requested component does not exist or an instance of the found
     *                     component
     */
    public function findComponent($componentName, $expectedSubclass)
    {
        $cacheId = 'Plugin'.$this->pluginName.$componentName.$expectedSubclass;

        $componentFile = sprintf('%s%s/%s.php', DemosPlanPath::getPluginPath(), $this->pluginName, $componentName);

        if (DemosPlanTools::cacheGet($cacheId)) {
            $classname = DemosPlanTools::cacheGet($cacheId);

            if (empty($classname)) {
                return null; // might by "false" in case has no menu, widget, ...
            }

            if (file_exists($componentFile)) {
                include_once $componentFile;
            }
        } else {
            DemosPlanTools::cacheAdd($cacheId, false, 3600); // prevent from trying to load over and over again for instance if there is no Menu for a plugin

            if (!file_exists($componentFile)) {
                return null;
            }

            require_once $componentFile;

            $classname = sprintf('demosplan\\Plugins\\%s\\%s', $this->pluginName, $componentName);

            if (!class_exists($classname)) {
                return null;
            }

            if (!empty($expectedSubclass) && !is_subclass_of($classname, $expectedSubclass)) {
                return null;
            }

            DemosPlanTools::cacheAdd($cacheId, $classname, 3600);
        }

        return $classname;
    }

    /**
     * @param string $directoryWithinPlugin
     * @param string $expectedSubclass
     *
     * @return array|false|mixed
     */
    public function findMultipleComponents($directoryWithinPlugin, $expectedSubclass)
    {
        $cacheId = 'Plugin'.$this->pluginName.$directoryWithinPlugin.$expectedSubclass;

        if (DemosPlanTools::cacheGet($cacheId)) {
            $components = DemosPlanTools::cacheGet($cacheId);

            if ($this->includeComponents($components)) {
                return $components;
            } else {
                // problem including one cached file, refresh cache
            }
        }

        $components = $this->doFindMultipleComponents($directoryWithinPlugin, $expectedSubclass);

        DemosPlanTools::cacheAdd($cacheId, $components, 3600);

        return $components;
    }

    /**
     * Extracts the plugin name from a backtrace array. Returns `false` if we can't find one.
     *
     * @param array $backtrace The result of {@link debug_backtrace()} or
     *                         [Exception::getTrace()](http://www.php.net/manual/en/exception.gettrace.php).
     *
     * @return string|false
     */
    public static function getPluginNameFromBacktrace($backtrace)
    {
        foreach ($backtrace as $tracepoint) {
            // try and discern the plugin name
            if (isset($tracepoint['class'])) {
                $className = self::getPluginNameFromNamespace($tracepoint['class']);
                if ($className) {
                    return $className;
                }
            }
        }

        return false;
    }

    /**
     * Extracts the plugin name from a namespace name or a fully qualified class name. Returns `false`
     * if we can't find one.
     *
     * @param string $namespaceOrClassName the namespace or class string
     *
     * @return string|false
     */
    public static function getPluginNameFromNamespace($namespaceOrClassName)
    {
        if (preg_match('/demosplan\\\\Plugins\\\\([a-zA-Z_0-9]+)\\\\/', $namespaceOrClassName, $matches)) {
            return $matches[1];
        } else {
            return false;
        }
    }

    /**
     * Override this method in your plugin class if you want your plugin to be loaded during tracking.
     *
     * Note: If you define your own dimension or handle a tracker event, your plugin will automatically
     * be detected as a tracker plugin.
     *
     * @return bool
     *
     * @internal
     */
    public function isTrackerPlugin()
    {
        return false;
    }

    /**
     * @param string $directoryWithinPlugin
     * @param string $expectedSubclass
     *
     * @return array<string,string>
     */
    private function doFindMultipleComponents($directoryWithinPlugin, $expectedSubclass)
    {
        $components = [];

        $baseDir = DemosPlanPath::getPluginPath($this->pluginName.'/'.$directoryWithinPlugin);
        $files = DemosFilesystem::globr($baseDir, '*.php');

        foreach ($files as $file) {
            require_once $file;

            $fileName = str_replace([$baseDir.'/', '.php'], '', $file);
            $className = sprintf('demosplan\\plugins\\%s\\%s\\%s', $this->pluginName, str_replace('/', '\\', $directoryWithinPlugin), str_replace('/', '\\', $fileName));

            if (!class_exists($className)) {
                continue;
            }

            if (!empty($expectedSubclass) && !is_subclass_of($className, $expectedSubclass)) {
                continue;
            }

            $klass = new \ReflectionClass($className);

            if ($klass->isAbstract()) {
                continue;
            }

            $components[$file] = $className;
        }

        return $components;
    }

    /**
     * @param array<string,string> $components
     *
     * @return bool true if all files were included, false if any file cannot be read
     */
    private function includeComponents($components)
    {
        foreach ($components as $file => $klass) {
            if (!is_readable($file)) {
                return false;
            }
        }
        foreach ($components as $file => $klass) {
            include_once $file;
        }

        return true;
    }

    /**
     * Loads a specific configuration.
     *
     * @param array            $configs   An array of configuration values
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     */
    public function load(array $configs, ContainerBuilder $container)
    {
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        // disable xsd Validation
        return false;
    }

    /**
     * Returns the recommended alias to use in XML.
     *
     * This alias is also the mandatory prefix to use when using YAML.
     *
     * @return string The alias
     */
    public function getAlias()
    {
        $className = get_class($this);
        if ('Extension' !== substr($className, -9)) {
            throw new BadMethodCallException('This extension does not follow the naming convention; you must overwrite the getAlias() method.');
        }
        $classBaseName = substr(strrchr($className, '\\'), 1, -9);

        return Container::underscore($classBaseName);
    }
}
