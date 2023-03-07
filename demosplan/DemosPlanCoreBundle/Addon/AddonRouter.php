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
    private $addonFolder = 'addons/cache/demosplam-addon-dedmospipes-main/src/Controller/';
    public function loadRoutes(): RouteCollection
    {
        $collection = new RouteCollection();
        //dd(glob('addons/cache/demosplan-addon-demospipes-main/src/Controller/*.php'));
        foreach (glob('addons/cache/demosplan-addon-demospipes-main/src/Controller/*.php') as $file)
        {
            //dd(AnnotatedStatementPdfPercentageDistributionApiController::class);
            //require_once $file;
            $array = explode("/", $file);
            $className = array_pop($array);
            $className = basename($className, '.php');
            //dd($className);
            // get the file name of the current file without the extension
            // which is essentially the class name
            //$class = basename($file, '.php');
            $adjustedClassName = 'DemosEurope\DemosplanAddon\DemosPipes\Controller\\'.$className;

            //dd($adjustedClassName);
            //dd($adjustedClassName);
            //dd(class_exists($adjustedClassName));

            if (class_exists($adjustedClassName))
            {
                $collection->addCollection($this->load($adjustedClassName));
            }
        }
        //$path = AddonPath::getRootPath('addons/cache/demosplam-addon-dedmospipes-main/src/Controller/');
        //$path = "/srv/www/demosplan/addons/cache/demosplan-addon-demospipes-main/src/Controller/AnnotatedStatementPdfPercentageDistributionApiController";
        //return $this->load($path);
        // get Controllers from addons
        // pass controller class strings to $this->load()
        // merge results into one RouteCollection

        //$path = DemosPlanPath::getRootPath($this->addonFolder);
        //$collection = $this->load($path);
        //dd($path);
        /*if(class_exists(AnnotatedStatementPdfPercentageDistributionApiController::class)) {
           //dd('addonRouter class exists');
           $collection = $this->load(AnnotatedStatementPdfPercentageDistributionApiController::class);
           //dd($collection);
        }*/


        //dd('gibts nicht');
        //$route = new RouteCollection();
        //$route->add($x);
        //var_dump('addonRouter class does not exists');
        //return new RouteCollection();
        //return $route;
        //$collection = new RouteCollection();
        dd($collection);
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
