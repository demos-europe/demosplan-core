<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use DemosEurope\DemosplanAddon\Utilities\AddonPath;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

class AddonRoutingLoader extends AnnotationDirectoryLoader implements RouteLoaderInterface
{
    private const PATH_TO_CONTROLLERS_FROM_ADDONROOT = '/src/Controller';

    public function __construct(
        private AddonRegistry $addonRegistry,
        FileLocatorInterface $locator,
        AnnotationClassLoader $loader
    ) {
        parent::__construct($locator, $loader);
    }

    public function loadRoutes(): RouteCollection
    {
        $routeCollection = new RouteCollection();
        foreach ($this->addonRegistry->getAddonInfos() as $addonInfo) {
            if ('' !== $addonInfo->getInstallPath()) {
                $controllerPath = AddonPath::getRootPath(
                    $addonInfo->getInstallPath().self::PATH_TO_CONTROLLERS_FROM_ADDONROOT
                );
                $routeCollection->addCollection($this->load($controllerPath));
            }
        }

        return $routeCollection;
    }

    protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $annot)
    {
        if ('__invoke' === $method->getName()) {
            $route->setDefault('_controller', $class->getName());
        } else {
            $route->setDefault('_controller', $class->getName().'::'.$method->getName());
        }
    }
}
