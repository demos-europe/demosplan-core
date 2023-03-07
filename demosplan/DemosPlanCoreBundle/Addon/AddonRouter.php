<?php

namespace demosplan\DemosPlanCoreBundle\Addon;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use function GuzzleHttp\Psr7\_parse_request_uri;

class AddonRouter extends AnnotationDirectoryLoader implements RouteLoaderInterface
{
    public function loadRoutes(): RouteCollection
    {
        $path = DemosPlanPath::getRootPath('addons/cache/demosplam-addon-dedmospipes-main/src/Controller');
        return $this->load($path);
        // get Controllers from addons
        // pass controller class strings to $this->load()
        // merge results into one RouteCollection
        //if(class_exists(AnnotatedStatementPdfPercentageDistributionApiController::class)) {
        //   var_dump('addonRouter class exists');
        //   $this->load(AnnotatedStatementPdfPercentageDistributionApiController::class);
        //}

        //$x = $this->load(AnnotatedStatementPdfPercentageDistributionApiController::class);
        //$route = new RouteCollection();
        //$route->add($x);
        //var_dump('addonRouter class does not exists');
        //return new RouteCollection();
        //return $route;

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
