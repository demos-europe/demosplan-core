<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use DemosEurope\DemosplanAddon\Utilities\AddonPath;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class AddonRoutingLoader extends AnnotationDirectoryLoader implements RouteLoaderInterface
{
    private const PATH_TO_CONTROLLERS_FROM_ADDONROOT = '/src/Controller';

    public function __construct(
        private readonly AddonRegistry $addonRegistry,
        FileLocatorInterface $locator,
        AnnotationClassLoader $loader
    ) {
        parent::__construct($locator, $loader);
    }

    public function loadRoutes(): RouteCollection
    {
        $routeCollection = new RouteCollection();
        foreach ($this->addonRegistry->getAddonInfos() as $addonInfo) {
            $this->addControllers($addonInfo, $routeCollection);
        }

        return $routeCollection;
    }

    protected function configureRoute(Route $route, ReflectionClass $class, ReflectionMethod $method, object $annot)
    {
        if ('__invoke' === $method->getName()) {
            $route->setDefault('_controller', $class->getName());
        } else {
            $route->setDefault('_controller', $class->getName().'::'.$method->getName());
        }
    }

    /**
     * Add controllers from addon to route collection if they exist.
     */
    private function addControllers(mixed $addonInfo, RouteCollection $routeCollection): void
    {
        $controllerDir = $addonInfo->getInstallPath().self::PATH_TO_CONTROLLERS_FROM_ADDONROOT;
        $controllerPath = AddonPath::getRootPath($controllerDir);
        if ('' !== $addonInfo->getInstallPath() && is_dir($controllerPath)) {
            $routeCollection->addCollection($this->load($controllerPath));
        }
    }
}
