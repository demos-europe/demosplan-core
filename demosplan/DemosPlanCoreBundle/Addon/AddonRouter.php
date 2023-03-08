<?php

namespace demosplan\DemosPlanCoreBundle\Addon;

use DemosEurope\DemosplanAddon\Utilities\AddonPath;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

class AddonRouter extends AnnotationDirectoryLoader implements RouteLoaderInterface
{
    private const PATH_TO_CONTROLLERS_FROM_ADDONROOT = '/src/Controller';

    public function __construct(
        private AddonRegistry $addonRegistry,
        private LoggerInterface $logger,
        FileLocatorInterface $locator,
        AnnotationClassLoader $loader
    ) {
        parent::__construct($locator, $loader);
    }

    public function loadRoutes(): RouteCollection
    {
        $routeCollection = new RouteCollection();
        $errors = [];
        foreach ($this->addonRegistry->getAddonInfos() as $addonInfo) {
            if ($addonInfo->isEnabled()) {
                $controllerPath = AddonPath::getRootPath(
                    $addonInfo->getInstallPath().self::PATH_TO_CONTROLLERS_FROM_ADDONROOT
                );
                $routeCollection->addCollection($this->load($controllerPath));
            }
        }

        $routeCollection = $this->loadSlicingTaggingInsideDemosPipesAnnotationsIfExist($routeCollection);

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

    /**
     * delete this if possible - just a (hopefully) temporary solution as long as SlicingTagging lives inside an Addon
     * but data is laid out like a separate addon.
     */
    private function loadSlicingTaggingInsideDemosPipesAnnotationsIfExist(RouteCollection $routeCollection): RouteCollection
    {
        $controllerPath = AddonPath::getRootPath(
            'addons/vendor/demos-europe/demosplan-addon-demospipes/src/SlicingTaggingAddon/Controller'
        );
        try {
            $routeCollection->addCollection($this->load($controllerPath));
        } catch (Exception $e) {
            // Slicing Tagging is only available if demosPipes is enabled at the moment
            // - so no Exception should be thrown here - since demosPipe is not mandatory.
            $this->logger->log(LogLevel::INFO, 'failed loading annotations for controllers with path: '.$controllerPath);
        }

        return $routeCollection;
    }
}
