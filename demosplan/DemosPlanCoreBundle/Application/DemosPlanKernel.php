<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Application;

use demosplan\DemosPlanCoreBundle\Addon\AddonBundleGenerator;
use demosplan\DemosPlanCoreBundle\Addon\AddonDoctrineMigrationsPass;
use demosplan\DemosPlanCoreBundle\Addon\AddonResolveTargetEntity;
use demosplan\DemosPlanCoreBundle\Addon\LoadAddonInfoCompilerPass;
use demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler\DumpGraphContainerPass;
use demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler\DumpYmlContainerPass;
use demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler\MenusLoaderPass;
use demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler\OptionsLoaderPass;
use demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler\RepositoryLoaderPass;
use demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler\RpcMethodSolverPass;
use demosplan\DemosPlanCoreBundle\DependencyInjection\Compiler\VirusCheckPass;
use demosplan\DemosPlanCoreBundle\DependencyInjection\ServiceTagAutoconfigurator;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function array_merge;
use function file_exists;

/**
 * This class loads all classes used by DPlan core and may be
 * overridden in specific projects.
 *
 * Class DemosPlanKernel
 */
class DemosPlanKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * Allowed extensions for configuration files.
     *
     * @const string
     */
    private const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    /**
     * String that defines test environment.
     *
     * @const string
     */
    final public const ENVIRONMENT_TEST = 'test';

    /**
     * String that defines dev environment.
     *
     * @const string
     */
    final public const ENVIRONMENT_DEV = 'dev';

    /**
     * String that defines production environment.
     *
     * @const string
     */
    final public const ENVIRONMENT_PROD = 'prod';

    public function __construct(
        private readonly string $activeProject,
        string $environment,
        bool $debug,
    ) {
        parent::__construct($environment, $debug);

        DemosPlanPath::setProjectPathFromConfig("projects/{$activeProject}");
    }

    /**
     * Returns an array of bundles to register.
     *
     * @return BundleInterface[] an array of bundle instances
     */
    public function registerBundles(): iterable
    {
        $bundles = require $this->getBundlesConfigPath();

        foreach ($bundles as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }

        $addonBundleGenerator = new AddonBundleGenerator();
        yield from $addonBundleGenerator->registerBundles($this->environment);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $coreConfigPath = DemosPlanPath::getConfigPath();

        $routes->import($coreConfigPath.'/{routes}/'.$this->environment.'/*'.self::CONFIG_EXTS, 'glob');
        $routes->import($coreConfigPath.'/{routes}/*'.self::CONFIG_EXTS, 'glob');

        $routesConfig = DemosPlanPath::getProjectPath('app/config/routing.yml');

        if ('dev' === $this->environment) {
            $routesConfig = DemosPlanPath::getProjectPath('app/config/routing_dev.yml');
        }

        $routes->import($routesConfig);
    }

    protected function configureContainer(ContainerBuilder $containerBuilder, LoaderInterface $loader): void
    {
        $coreConfigPath = DemosPlanPath::getConfigPath();
        $projectConfigPath = DemosPlanPath::getProjectPath('app/config');

        // load bundles
        $containerBuilder->addResource(new FileResource($this->getBundlesConfigPath()));

        // determine configuration to be loaded
        $globs = array_merge(
            $this->determineParameterGlobs($coreConfigPath, $projectConfigPath),
            $this->determineServiceGlobs($coreConfigPath, $projectConfigPath)
        );

        // load configuration
        array_walk(
            $globs,
            static function ($glob) use ($loader) {
                $loader->load($glob.self::CONFIG_EXTS, 'glob');
            }
        );

        // set dynamic parameters
        $this->setDynamicParameters($containerBuilder);
    }

    /**
     * Set CacheDir to be able to work with Docker constraints.
     */
    public function getCacheDir(): string
    {
        if ($this->isLocalContainer()) {
            return DemosPlanPath::getRootPath(
                sprintf('var/cache/%s/%s', $this->environment, $this->activeProject)
            );
        }

        // use distinct caches for parallel tests if needed
        if ('test' === $this->getEnvironment()) {
            return DemosPlanPath::getRootPath(
                sprintf('var/cache/%s/%s/%s', $this->environment, $this->activeProject, $_SERVER['APP_TEST_SHARD'] ?? '')
            );
        }

        return parent::getCacheDir();
    }

    /**
     * Set LogDir to be able to work with Docker constraints.
     */
    public function getLogDir(): string
    {
        if ($this->isLocalContainer()) {
            return DemosPlanPath::getRootPath(
                sprintf('var/log/%s/%s', $this->environment, $this->activeProject)
            );
        }

        // use distinct logfiles for parallel tests if needed
        if ('test' === $this->getEnvironment()) {
            return DemosPlanPath::getRootPath(
                sprintf('var/logs/%s/%s/%s', $this->environment, $this->activeProject, $_SERVER['APP_TEST_SHARD'] ?? '')
            );
        }

        return parent::getLogDir();
    }

    /**
     * Override to speedup tests by 50%!
     *
     * @see akriswallsmith.net/post/27979797907/get-fast-an-easy-symfony2-phpunit-optimization
     *
     * @throws Exception
     */
    protected function initializeContainer()
    {
        if ('test' !== $this->getEnvironment()) {
            parent::initializeContainer();

            return;
        }

        $this->initializeContainerForTestEnvironment();
    }

    protected function initializeContainerForTestEnvironment(): void
    {
        static $first = true;

        $debug = $this->debug;

        if (!$first) {
            // disable debug mode on all but the first initialization
            $this->debug = false;
        }

        // will not work with --process-isolation
        $first = false;

        try {
            parent::initializeContainer();
        } catch (Exception $e) {
            $this->debug = $debug;
            throw $e;
        }

        $this->debug = $debug;

        // set request service for command line interface
        if ('cli' === PHP_SAPI) {
            $this->getContainer()->set('request', new Request());
        }
    }

    /**
     * Is this Kernel booted in local container environment?
     */
    public function isLocalContainer(): bool
    {
        return array_key_exists('DEVELOPMENT_CONTAINER', $_SERVER) && '1' === $_SERVER['DEVELOPMENT_CONTAINER'];
    }

    private function getBundlesConfigPath(): string
    {
        return DemosPlanPath::getConfigPath('bundles.php');
    }

    /**
     * Get path to config file with additional local container params.
     */
    private function getLocalContainerConfigGlob(): string
    {
        return DemosPlanPath::getConfigPath('config_dev_container');
    }

    protected function build(ContainerBuilder $container): void
    {
        ServiceTagAutoconfigurator::configure($container);

        if ($this->isLocalContainer()) {
            $container->addCompilerPass(
                new DumpYmlContainerPass(),
                PassConfig::TYPE_REMOVE,
                -2048
            );

            $container->addCompilerPass(
                new DumpGraphContainerPass(),
                PassConfig::TYPE_REMOVE,
                -2048
            );
        }

        $container->addCompilerPass(new RpcMethodSolverPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
        $container->addCompilerPass(new MenusLoaderPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
        $container->addCompilerPass(new RepositoryLoaderPass());
        $container->addCompilerPass(new OptionsLoaderPass(), PassConfig::TYPE_AFTER_REMOVING, 0);
        $container->addCompilerPass(new VirusCheckPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
        $container->addCompilerPass(new AddonResolveTargetEntity(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        if ('test' !== $this->getEnvironment()) {
            $container->addCompilerPass(new LoadAddonInfoCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
            $container->addCompilerPass(new AddonDoctrineMigrationsPass());
        }
    }

    public function getActiveProject(): string
    {
        return $this->activeProject;
    }

    /**
     * Build the glob list for all parameters.
     *
     * @return string[]
     */
    private function determineParameterGlobs(
        string $coreConfigPath,
        string $projectConfigPath,
    ): array {
        $parameterGlobs = [
            // global defaults
            "{$coreConfigPath}/parameters_default",
            // global environment dependent defaults
            "{$coreConfigPath}/parameters_{$this->environment}",
            // project defaults
            "{$projectConfigPath}/parameters_default_project",
        ];

        if ($this->isLocalContainer()) {
            // development container defaults
            $parameterGlobs[] = $this->getLocalContainerConfigGlob();
        }

        // defined test environment params should always win
        if (self::ENVIRONMENT_TEST === $this->getEnvironment()) {
            $parameterGlobs[] = "{$coreConfigPath}/parameters_test";
        }

        // individual runtime project parameters
        $parameterGlobs[] = "{$projectConfigPath}/parameters";

        return $parameterGlobs;
    }

    /**
     * Build the glob list for all services.
     *
     * @return string[]
     */
    private function determineServiceGlobs(
        string $coreConfigPath,
        string $projectConfigPath,
    ): array {
        $bundleGlobs = [
            // default bundle configurations
            "{$coreConfigPath}/packages/*",
            // environment dependent bundle configuration
            "{$coreConfigPath}/{packages}/{$this->environment}/*",
            // core dplan service configuration
            "{$coreConfigPath}/config_core",
            // project specific environment dependent service configuration
            "{$projectConfigPath}/config_{$this->environment}",
        ];

        // uses local file, no need for flysystem
        if (file_exists(DemosPlanPath::getRootPath('deploy'))) {
            // deployment services, these are a little extra
            // as they are not shipped and MUST thus not always be included
            $bundleGlobs[] = "{$coreConfigPath}/services_deployment";
        }

        return $bundleGlobs;
    }

    private function setDynamicParameters(ContainerBuilder $containerBuilder): void
    {
        $projectPathWithoutTrailingSlash = substr(DemosPlanPath::getProjectPath(), 0, -1);

        $containerBuilder->setParameter(
            'demosplan.project_dir',
            $projectPathWithoutTrailingSlash
        );

        $containerBuilder->setParameter(
            'demosplan.project_name',
            $this->activeProject
        );

        // This is required to make project overrides work
        $containerBuilder->setParameter(
            'kernel.root_dir',
            DemosPlanPath::getProjectPath('app')
        );
    }
}
