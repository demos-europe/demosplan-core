<?php

namespace demosplan\DemosPlanCoreBundle\Addon;

use DemosEurope\DemosplanAddon\DemosPipes\Controller\AnnotatedStatementPdfPercentageDistributionApiController;
use DemosEurope\DemosplanAddon\Utilities\AddonPath;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use function GuzzleHttp\Psr7\_parse_request_uri;

class AddonRouter extends AnnotationClassLoader implements RouteLoaderInterface
{
    public function loadRoutes(): RouteCollection
    {
        //$path = AddonPath::getRootPath('addons/cache/demosplam-addon-dedmospipes-main/src/Controller/');
        //$path = "/srv/www/demosplan/addons/cache/demosplan-addon-demospipes-main/src/Controller/AnnotatedStatementPdfPercentageDistributionApiController";
        //return $this->load($path);
        // get Controllers from addons
        // pass controller class strings to $this->load()
        // merge results into one RouteCollection
        if(class_exists(AnnotatedStatementPdfPercentageDistributionApiController::class)) {
           //dd('addonRouter class exists');
           $collection = $this->load(AnnotatedStatementPdfPercentageDistributionApiController::class);
           //dd($collection);
        }
        //dd('gibts nicht');
        //$x = $this->load(AnnotatedStatementPdfPercentageDistributionApiController::class);
        //$route = new RouteCollection();
        //$route->add($x);
        //var_dump('addonRouter class does not exists');
        //return new RouteCollection();
        //return $route;
        //$collection = new RouteCollection();
        //dd($collection);
        return $collection;
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
