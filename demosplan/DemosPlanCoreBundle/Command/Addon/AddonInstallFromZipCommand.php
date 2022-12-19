<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Addon;

use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use demosplan\DemosPlanCoreBundle\Addon\AddonRegistry;
use demosplan\DemosPlanCoreBundle\Addon\Composer\PackageInformation;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Exception\JsonException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
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

    public function configure(): void
    {
        $this->addArgument(
            'path',
            InputArgument::REQUIRED,
            'Path to zip'
        );
    }

    /**
     * The execute function can be interpreted as the basic step-by-step instruction on how to
     * install an addon. All these steps can also be done manually, if necessary.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $this->setPaths($input->getArgument('path'));
        try {
            $this->initializeAddonsInfrastructure();
        } catch (JsonException|RuntimeException $e) {
            $output->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->copyAndUnzipFileIfNecessary($output);

        try {
            $packageDefinition = $this->loadPackageDefinition();

            $this->addAddonToComposerRequire($packageDefinition);
        } catch (JsonException $e) {
            $output->error($e->getMessage());

            return Command::FAILURE;
        }

        try {
            Batch::create($this->getApplication(), $output)
                ->addShell(['composer', 'clearcache'])
                ->addShell(['composer', 'dump-autoload'])
                ->addShell(['composer', 'bin', 'addons', 'update', '-a'])
                ->run();
        } catch (Exception $e) {
            $output->error($e->getMessage());

            return Command::FAILURE;
        }

        // If composer update went well, add the addon to the registry
        $addonRegistry = new AddonRegistry();
        $addonRegistry->register($packageDefinition);

        try {
            $packageMeta = $addonRegistry->get($packageDefinition->getName());

            if (array_key_exists('ui', $packageMeta['manifest'])) {
                // TODO: fix frontend build
                /*
                Batch::create($this->getApplication(), $output)
                    ->addShell(['yarn', 'install', '--frozen-lockfile'], $packageMeta['install_path'])
                    ->addShell(['yarn', 'run', 'webpack', '--node-env=production'], $packageMeta['install_path'])
                    ->run();*/
            }
        } catch (Exception $e) {
            $output->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
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
        $this->addonsDirectory = DemosPlanPath::getRootPath(AddonRegistry::ADDON_DIRECTORY);
        $this->addonsCacheDirectory = DemosPlanPath::getRootPath(AddonRegistry::ADDON_CACHE_DIRECTORY);
        $this->zipSourcePath = realpath($path);

        $pathInfo = new SplFileInfo($path);

        $this->zipCachePath = DemosPlanPath::getRootPath(AddonRegistry::ADDON_CACHE_DIRECTORY.$pathInfo->getBasename('.zip').'/');
    }

    /**
     * This will try to copy and unzip the Repo if the path is correct and the repo is not already present in the cache.
     */
    private function copyAndUnzipFileIfNecessary(OutputInterface $output): void
    {
        $output->writeln('Checking if the addon needs to be unpacked');

        $doesFileExist = file_exists($this->zipSourcePath);
        $addonExistsInCache = file_exists($this->zipCachePath);

        if ($doesFileExist && !$addonExistsInCache) {
            $zipArchive = new ZipArchive();
            $open = $zipArchive->open($this->zipSourcePath);
            if ($open) {
                $output->writeln('Unpacking addon');
                $zipArchive->extractTo($this->addonsCacheDirectory);
            }
        }
    }

    /**
     * @return \Composer\Package\BasePackage|\Composer\Package\CompleteAliasPackage|\Composer\Package\CompletePackage|\Composer\Package\RootAliasPackage|\Composer\Package\RootPackage|\Symfony\Component\Console\Input\InputDefinition
     *
     * @throws JsonException
     */
    public function loadPackageDefinition()
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
}
