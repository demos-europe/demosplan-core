<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Addon;

use Composer\Console\Input\InputOption;
use Composer\Package\BasePackage;
use Composer\Package\CompleteAliasPackage;
use Composer\Package\CompletePackage;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use Composer\Package\RootAliasPackage;
use Composer\Package\RootPackage;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Addon\AddonManifestCollection;
use demosplan\DemosPlanCoreBundle\Addon\Composer\PackageInformation;
use demosplan\DemosPlanCoreBundle\Addon\Registrator;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Exception\AddonException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

/**
 * This command handles the whole installation process for a zipped addon bundle.
 * It will create all necessary directories and files if they don't exist and set everything up to have the
 * addon in the right vendor directory and added to composer and the addons.yaml.
 *
 * It does **NOT** handle addon activation!
 */
class AddonInstallFromZipCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:addon:install';
    protected static $defaultDescription = 'Installs an addon based on a given zip-file';

    private string $zipSourcePath;
    private string $zipCachePath;
    private string $addonsDirectory;
    private string $addonsCacheDirectory;

    public function __construct(private readonly Registrator $installer, ParameterBagInterface $parameterBag, string $name = null)
    {
        parent::__construct($parameterBag, $name);
    }

    public function configure(): void
    {
        $this->addArgument(
            'path',
            InputArgument::REQUIRED,
            'Path to zip'
        );

        $this->addOption('reinstall', '', InputOption::VALUE_NONE, 'Re-install an addon (useful for debugging)');
        $this->addOption('enable', '', InputOption::VALUE_NONE, 'Immediately enable addon during installation');
    }

    /**
     * The execute function can be interpreted as the basic step-by-step instruction on how to
     * install an addon. All these steps can also be done manually, if necessary.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $reinstall = $input->getOption('reinstall');
        $enable = $input->getOption('enable');

        $this->setPaths($input->getArgument('path'));
        try {
            $this->initializeAddonsInfrastructure();
        } catch (JsonException|RuntimeException $e) {
            $output->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->copyAndUnzipFileIfNecessary($output, $reinstall);

        try {
            $packageDefinition = $this->loadPackageDefinition();

            $this->checkReinstall($packageDefinition, $reinstall);

            $this->addAddonToComposerRequire($packageDefinition);
        } catch (JsonException $e) {
            $output->error($e->getMessage());

            return Command::FAILURE;
        } catch (AddonException $e) {
            $output->success($e->getMessage());

            return Command::SUCCESS;
        }

        try {
            // The '-a' flag for the composer update is strictly necessary as it generates the authorative
            // classmap with all classes which we then use for our own extended autoloading.
            $composerReturn = Batch::create($this->getApplication(), $output)
                ->addShell(['composer', 'clearcache'])
                ->addShell(['composer', 'dump-autoload'])
                ->addShell(['composer', 'bin', 'addons', 'update', '-a', '-o', '--prefer-lowest'])
                ->run();
        } catch (Exception $e) {
            $output->error($e->getMessage());

            return Command::FAILURE;
        }

        if (0 !== $composerReturn) {
            $output->error('Composer commands failed! This is most likely due to a conflict in dependency versions. Please check manually!');

            return Command::FAILURE;
        }

        try {
            // If composer update went well, add the addon to the registry
            $name = $this->installer->register($packageDefinition, $enable);

            $kernel = $this->getApplication()->getKernel();
            $environment = $kernel->getEnvironment();
            $activeProject = $this->getApplication()->getKernel()->getActiveProject();

            $batchReturn = Batch::create($this->getApplication(), $output)
                ->addShell(["bin/{$activeProject}", 'cache:clear', '-e', $environment])
                ->addShell(["bin/{$activeProject}", 'dplan:addon:build-frontend', $name, '-e', $environment])
                ->run();

            if (0 === $batchReturn) {
                return Command::SUCCESS;
            }
        } catch (Exception $e) {
            $output->error($e->getMessage());
        }

        return Command::FAILURE;
    }

    /**
     * This method checks if everything necessary for Addons already exists and creates the missing pieces
     * with a default configuration.
     *
     * @throws RuntimeException|JsonException
     */
    private function initializeAddonsInfrastructure(): void
    {
        $this->createDirectoryIfNecessary($this->addonsDirectory);
        $this->createDirectoryIfNecessary($this->addonsCacheDirectory);

        // If addons.yaml does not exist, create it
        if (!file_exists($this->addonsDirectory.'addons.yaml')) {
            file_put_contents($this->addonsDirectory.'addons.yaml', Yaml::dump(['addons' => []]));
        }

        // If composer.json does not exist, create it
        if (!file_exists($this->addonsDirectory.'composer.json')) {
            $content = [
                'minimum-stability' => 'dev',
                'require'           => [],
                'config'            => [
                    'sort-packages' => true,
                    'allow-plugins' => [
                        'demos-europe/demosplan-addon-installer' => true,
                    ],
                ],
                'repositories'      => [
                    [
                        'type'    => 'path',
                        'url'     => 'cache/*',
                        'options' => [
                            'symlink' => true,
                        ],
                    ],
                ],
            ];
            file_put_contents($this->addonsDirectory.'composer.json', Json::encode($content, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }
    }

    /**
     * Creates a new directory with the given path if it does not yet exist.
     *
     * @throws RuntimeException
     */
    private function createDirectoryIfNecessary(string $directory): void
    {
        if (!file_exists($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
            }
        }
    }

    /**
     * Sets all necessary paths for the command.
     */
    private function setPaths(string $path): void
    {
        $this->addonsDirectory = DemosPlanPath::getRootPath(Registrator::ADDON_DIRECTORY);
        $this->addonsCacheDirectory = DemosPlanPath::getRootPath(Registrator::ADDON_CACHE_DIRECTORY);
        $this->zipSourcePath = realpath($path);

        $pathInfo = new SplFileInfo($path);

        $this->zipCachePath = DemosPlanPath::getRootPath(Registrator::ADDON_CACHE_DIRECTORY.$pathInfo->getBasename('.zip').'/');
    }

    /**
     * This will try to copy and unzip the Repo if the path is correct and the repo is not already present in the cache.
     */
    private function copyAndUnzipFileIfNecessary(OutputInterface $output, bool $reinstall): void
    {
        $doesFileExist = file_exists($this->zipSourcePath);
        $addonExistsInCache = file_exists($this->zipCachePath);
        $shouldUnzip = !$addonExistsInCache || $reinstall;

        if ($doesFileExist && $shouldUnzip) {
            $zipArchive = new ZipArchive();
            $open = $zipArchive->open($this->zipSourcePath);
            if ($open) {
                $output->writeln('Unpacking addon');
                $zipArchive->extractTo($this->addonsCacheDirectory);
            }
        }
    }

    /**
     * @return BasePackage|CompleteAliasPackage|CompletePackage|RootAliasPackage|RootPackage|InputDefinition
     *
     * @throws JsonException
     */
    public function loadPackageDefinition(): PackageInterface
    {
        $loader = new ArrayLoader();
        $composerJsonArray = Json::decodeToArray(file_get_contents($this->zipCachePath.'composer.json'));

        /*
         * Regular composer.json files are not a reliable source for version information
         * since the version field is not required on the schema. Thus, if it's missing
         * we set it to a bogus version as it is never used internally.
         */
        if (!array_key_exists('version', $composerJsonArray)) {
            $composerJsonArray['version'] = PackageInformation::UNDEFINED_VERSION;
        }

        return $loader->load($composerJsonArray);
    }

    /**
     * Adds the addon name and version to the required part of the composer.json in case
     * the addon is not already present there.
     *
     * @throws JsonException
     */
    private function addAddonToComposerRequire(PackageInterface $addonComposerDefinition): void
    {
        $addonName = $addonComposerDefinition->getName();
        $addonVersion = $addonComposerDefinition->getVersion();

        if (PackageInformation::UNDEFINED_VERSION === $addonVersion) {
            $addonVersion = '*';
        }

        $composerContent = Json::decodeToArray(file_get_contents($this->addonsDirectory.'composer.json'));

        if (!array_key_exists($addonName, $composerContent['require'])) {
            $composerContent['require'][$addonName] = $addonVersion;
            file_put_contents(
                $this->addonsDirectory.'composer.json',
                Json::encode($composerContent, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            );
        }
    }

    public function checkReinstall(PackageInterface $packageDefinition, bool $reinstall): void
    {
        $addons = AddonManifestCollection::load();
        if (array_key_exists($packageDefinition->getName(), $addons) && !$reinstall) {
            throw AddonException::alreadyInstalled();
        }
    }
}
