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
            if ($addonInfo->isEnabled(false)) {
                $this->addControllers($addonInfo, $routeCollection);
            }
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
        // Without any installPath, we can't register any routes, so we can skip this step
        if ('' === $addonInfo->getInstallPath()) {
            return;
        }

        $controllerPaths = $addonInfo->getControllerPaths();
        foreach ($controllerPaths as $relativeControllerPath) {
            $controllerDir = $addonInfo->getInstallPath().$relativeControllerPath;
            $absoluteControllerPath = AddonPath::getRootPath($controllerDir);
            if (is_dir($absoluteControllerPath)) {
                $routeCollection->addCollection($this->load($absoluteControllerPath));
            }
        }
    }
}
