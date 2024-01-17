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
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\HttpClientInterface;
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

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Registrator $installer,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }

    public function configure(): void
    {
        $this->addArgument(
            'path',
            InputArgument::OPTIONAL,
            'Path to zip'
        );

        $this->addOption('reinstall', '', InputOption::VALUE_NONE, 'Re-install an addon (useful for debugging)');
        $this->addOption('no-enable', '', InputOption::VALUE_NONE, 'Do not immediately enable addon during installation');
        $this->addOption('local', '', InputOption::VALUE_NONE, 'Only use locally available addons, do not connect GitHub');
        $this->addOption('github', '', InputOption::VALUE_NONE, 'Directly load addons from GitHub');
        $this->addOption('repos', '', InputOption::VALUE_NONE, 'Call demosplan addon repository');
        $this->addOption('force-download', '', InputOption::VALUE_NONE, 'Force download repository from GitHub');
        $this->addOption('folder', '', InputOption::VALUE_REQUIRED, 'Folder to read addon zips from', 'addonZips');
    }

    /**
     * The execute function can be interpreted as the basic step-by-step instruction on how to
     * install an addon. All these steps can also be done manually, if necessary.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $reinstall = $input->getOption('reinstall');
        $enable = !$input->getOption('no-enable');
        $path = $input->getArgument('path');
        $folder = $input->getOption('folder');

        if (null === $path) {
            try {
                if ($input->getOption('local')) {
                    $path = $this->getPathFromZip($input, $output, $folder);
                } elseif ($input->getOption('github')) {
                    $path = $this->loadFromGithub($input, $output, $folder);
                } elseif ($input->getOption('repos')) {
                    $path = $this->loadFromApiRepository($input, $output, $folder);
                } else {
                    $path = $this->getPathFromZip($input, $output, $folder);
                }
            } catch (Exception $e) {
                $output->error($e->getMessage());

                return Command::FAILURE;
            }
        }

        $this->setPaths($path);
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
        } catch (JsonException|AddonException $e) {
            $output->error($e->getMessage());

            return Command::FAILURE;
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
                $output->success("Addon {$name} successfully installed. Please remember to ".
                    'build the frontend assets of the core and deployment to webserver folder when needed.');

                return Command::SUCCESS;
            }
        } catch (Exception $e) {
            $output->error($e->getMessage());
            // this hint may be removed in symfony6 when we can update the efrane/console-additions
            // to a version bigger than 0.7, as the batch will not swallow the exception anymore
            $output->info('If you have no clue why this happened, you may try to install '.
                'the addon manually by performing
                `composer bin addons update --prefer-lowest -a -o`');
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

        if (!array_key_exists('require', $composerContent)
            || !array_key_exists($addonName, $composerContent['require'])) {
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

    private function getPathFromZip(InputInterface $input, OutputInterface $output, string $folder): string
    {
        $zips = glob(DemosPlanPath::getRootPath($folder).'/*.zip');

        if (!is_array($zips) || 0 === count($zips)) {
            throw new RuntimeException("No Addon zips found in Folder {$folder}");
        }

        $question = new ChoiceQuestion('Which addon do you want to install? ', $zips);

        return $this->getHelper('question')->ask($input, $output, $question);
    }

    private function loadFromApiRepository(InputInterface $input, SymfonyStyle $output, mixed $folder): string
    {
        $addonRepositoryUrl = $this->parameterBag->get('addon_repository_url');
        try {
            $addonRepositoryToken = $this->parameterBag->get('addon_repository_token');
        } catch (Exception) {
            throw new RuntimeException('You need to set an environment variable ADDON_REPOSITORY_TOKEN to access the addon repository. You may use the option --local to only use locally available addons.');
        }
        $addonRepositoryOptions = [
            'headers' => [
                'Authorization'        => 'Bearer '.$addonRepositoryToken,
            ],
        ];

        // fetch a list of available repositories
        $repositoryListUrl = sprintf('%s/api/list', $addonRepositoryUrl);
        $existingReposResponse = $this->httpClient->request('GET', $repositoryListUrl, $addonRepositoryOptions);
        if (401 === $existingReposResponse->getStatusCode()) {
            throw new RuntimeException('Could not access repository. Did you purchase an addon repository personal access token?');
        }
        $existingTagsContent = $existingReposResponse->getContent(false);
        try {
            $availableAddons = Json::decodeToArray($existingTagsContent);
        } catch (JsonException $exception) {
            throw new RuntimeException('Could not decode response from repository. '.$exception->getMessage().' Response was '.$existingTagsContent);
        }
        $repoQuestion = new ChoiceQuestion('Which addon do you want to install? ', $availableAddons);
        $repo = $this->getHelper('question')->ask($input, $output, $repoQuestion);

        // fetch a list of available tags
        $tagsUrl = sprintf('%s/api/list/tags/%s', $addonRepositoryUrl, $repo);
        $existingTagsResponse = $this->httpClient->request('GET', $tagsUrl, $addonRepositoryOptions);
        $existingTagsContent = $existingTagsResponse->getContent(false);
        $tag = $this->askTag($existingTagsContent, $input, $output);

        // tags are prefixed with v, but the zip file is not
        $path = DemosPlanPath::getRootPath($folder).'/'.$repo.'-'.str_replace('v', '', $tag).'.zip';
        if (file_exists($path) && !$input->getOption('force-download')) {
            $output->info('File '.$path.' already exists, skipping download. You may use the --force-download option to force a download.');
        } else {
            $zipUrl = sprintf('%s/api/get/%s/%s', $addonRepositoryUrl, $repo, $tag);
            $zipResponse = $this->httpClient->request('GET', $zipUrl, $addonRepositoryOptions);

            $zipContent = $zipResponse->getContent(false);
            file_put_contents($path, $zipContent);
        }

        return $path;
    }

    private function loadFromGithub(InputInterface $input, SymfonyStyle $output, mixed $folder): string
    {
        try {
            $ghToken = $this->parameterBag->get('github_token');
        } catch (Exception) {
            throw new RuntimeException('You need to set an environment variable GITHUB_TOKEN to access GitHub. You may use the option --local to only use locally available addons.');
        }
        $ghOptions = [
            'headers' => [
                'Accept'               => 'application/vnd.github.v3+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'Authorization'        => 'Bearer '.$ghToken,
            ],
        ];

        $ghReposUrl = 'https://api.github.com/orgs/demos-europe/repos?per_page=100';
        $availableRepositories = $this->fetchRepositories($ghOptions, $ghReposUrl);
        $availableAddons = collect($availableRepositories)->filter(function ($repo) {
            return str_contains($repo['name'], 'demosplan-addon-');
        })
            ->map(function ($repo) {
                return $repo['name'];
            })
            ->sortBy(function ($repo) {
                return $repo;
            })
            ->values()
            ->toArray();
        $question = new ChoiceQuestion('Which addon do you want to install? ', $availableAddons);
        $repo = $this->getHelper('question')->ask($input, $output, $question);

        // fetch a list of available tags
        $ghUrl = 'https://api.github.com/repos/demos-europe/'.$repo.'/tags';
        $existingTagsResponse = $this->httpClient->request('GET', $ghUrl, $ghOptions);
        if (404 === $existingTagsResponse->getStatusCode()) {
            throw new RuntimeException('Could not access repository. Did you create a GitHub personal access token?');
        }
        $existingTagsContent = $existingTagsResponse->getContent(false);
        $tag = $this->askTag($existingTagsContent, $input, $output);

        // tags are prefixed with v, but the zip file in GitHub is not
        $path = DemosPlanPath::getRootPath($folder).'/'.$repo.'-'.str_replace('v', '', $tag).'.zip';
        if (file_exists($path) && !$input->getOption('force-download')) {
            $output->info('File '.$path.' already exists, skipping download. You may use the --force-download option to force a download.');
        } else {
            // url is hardcoded by purpose as the zip otherwise extracts to a folder containing the hash, not the tag
            // which makes it harder to recognize the installed addon version
            $zipUrl = 'https://github.com/demos-europe/'.$repo.'/archive/refs/tags/'.$tag.'.zip';
            $zipResponse = $this->httpClient->request('GET', $zipUrl, $ghOptions);

            $zipContent = $zipResponse->getContent(false);
            file_put_contents($path, $zipContent);
        }

        return $path;
    }

    private function fetchRepositories(array $ghOptions, string $githubUrl): array
    {
        $repositories = [];
        $existingReposResponse = $this->httpClient->request('GET', $githubUrl, $ghOptions);
        if (404 === $existingReposResponse->getStatusCode()) {
            throw new RuntimeException('Could not access repository. Did you create a GitHub personal access token?');
        }
        $existingReposContent = $existingReposResponse->getContent(false);
        $repositories = array_merge($repositories, JSON::decodeToArray($existingReposContent));
        if (array_key_exists('link', $existingReposResponse->getHeaders())) {
            $links = explode(',', $existingReposResponse->getHeaders()['link'][0]);
            foreach ($links as $link) {
                if (str_contains($link, 'rel="next"')) {
                    $nextLink = str_replace(['<', '>', 'rel="next"'], '', $link);
                    $nextRepositories = $this->fetchRepositories($ghOptions, $nextLink);
                }
            }
        }

        return array_merge($repositories, $nextRepositories ?? []);
    }

    private function askTag(string $existingTagsContent, InputInterface $input, SymfonyStyle $output): mixed
    {
        $tags = collect(Json::decodeToArray($existingTagsContent))->filter(
            function ($tag) {
                return !str_contains($tag['name'], 'rc');
            }
        )->map(function ($tag) {
            return $tag['name'];
        })->toArray();

        if (0 === count($tags)) {
            throw new RuntimeException('No tags found for this repository. Please install the addon via --local');
        }

        $question = new ChoiceQuestion(
            'Which tag do you want to install? ',
            $tags
        );
        $tag = $this->getHelper('question')->ask($input, $output, $question);

        return $tag;
    }
}
