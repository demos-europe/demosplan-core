<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class VendorlistUpdateCommand extends CoreCommand
{
    /**
     * List of JS dependencies that should never appear in the license listing.
     *
     * @const string[] Elements should be given as `package-name`
     */
    private const JS_PACKAGE_DENYLIST = [
        '@demos-europe/demosplan-ui',
    ];

    /**
     * List of PHP dependencies that should never appear in the license listing.
     *
     * @const string[] Elements should be given as `vendor/package`
     */
    private const PHP_PACKAGE_DENYLIST = [];

    protected static $defaultName = 'dplan:vendorlist:update';
    protected static $defaultDescription = 'Update the list of external dependencies';

    final public const JS_PATH_JSON = 'demosplan/DemosPlanCoreBundle/Resources/static/js_licenses.json';
    final public const JS_PATH_TEXT = 'licenses/js_licenses.txt';
    final public const PHP_PATH_JSON = 'demosplan/DemosPlanCoreBundle/Resources/static/php_licenses.json';
    final public const PHP_PATH_TEXT = 'licenses/php_licenses.txt';

    protected $storagePath = '';
    /** @var SymfonyStyle */
    protected $io;

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->fetchPHPDependencyLicenses();
        $this->fetchNodeDependencyLicenses();

        return Command::SUCCESS;
    }

    protected function fetchPHPDependencyLicenses(): void
    {
        $this->io->writeln('Updating the PHP vendor list');

        try {
            $composerLicensesCommand = new Process(['composer', 'licenses', '--format=json']);
            $composerLicensesCommand->setWorkingDirectory(DemosPlanPath::getRootPath());
            $composerLicensesCommand->mustRun();

            $composerInfoCommand = new Process(['composer', 'info', '--format=json', '--direct']);
            $composerInfoCommand->setWorkingDirectory(DemosPlanPath::getRootPath());
            $composerInfoCommand->mustRun();

            $dependencies = Json::decodeToArray($composerLicensesCommand->getOutput())['dependencies'];
            $directDependencies = Json::decodeToArray($composerInfoCommand->getOutput())['installed'];

            $phpLicenses = collect($dependencies)
                ->filter(
                    // only add license information for packages which are
                    // direct dependencies (e.g. listed in the composer.json)
                    // of demosplan
                    static fn ($_, $package): bool => 1 === count(
                        (array) array_filter(
                            $directDependencies,
                            static fn ($directDependency): bool => $directDependency['name'] === $package
                        )
                    )
                );

            $progressBar = new ProgressBar($this->io, $phpLicenses->count());
            $progressBar->start();

            $phpLicenses = $phpLicenses
                ->map(
                    function ($info, $package) use ($progressBar): array {
                        $license = $info['license'][0] ?? '';

                        // set variables manually for forks
                        /* @noinspection DegradedSwitchInspection */
                        switch ($package) {
                            case 'forks/phpword':
                                $package = 'phpoffice/phpword';
                                $website = 'https://github.com/PHPOffice/PHPWord';
                                break;
                            default:
                                $website = $this->getProjectURLFromPackagist($package);
                        }

                        $progressBar->advance();

                        return compact('license', 'package', 'website');
                    }
                )
                ->filter(static fn ($packageInfo): bool =>
                    // only output packages that do actually have license info
                    '' !== $packageInfo['license'])
                ->filter(static fn ($packageInfo): bool =>
                    // we may have private packages that should be hidden
                    !in_array($packageInfo['package'], self::PHP_PACKAGE_DENYLIST, true))
                ->values();

            $progressBar->finish();

            // local file only, no need for flysystem
            $fs = new Filesystem();
            $this->dumpPhpLicenseFile($fs, $phpLicenses);

            $filename = DemosPlanPath::getRootPath(self::PHP_PATH_JSON);
            $fs->dumpFile($filename, $phpLicenses->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->io->success("Updated PHP vendor information to file {$filename}");
        } catch (Exception $e) {
            $this->io->error('An error occured: '.$e->getMessage());
        }
    }

    protected function getProjectURLFromPackagist($package)
    {
        if (!preg_match('/[A-Za-z0-9][A-Za-z0-9_.-]*\/[A-Za-z0-9][A-Za-z0-9_.-]*/', (string) $package)) {
            throw new InvalidArgumentException('Invalid composer package name');
        }

        $client = new Client();

        try {
            $response = $client->get(
                sprintf('https://packagist.org/packages/%s.json', $package)
            );

            if (200 === $response->getStatusCode()) {
                $json = Json::decodeToArray($response->getBody());

                return data_get($json, 'package.repository');
            }
        } catch (Exception) {
            $this->io->warning('Could not fetch URL for package '.$package);
        }

        return '';
    }

    protected function fetchNodeDependencyLicenses(): void
    {
        $this->io->writeln('Updating the js vendor list');

        try {
            $yarn = new Process(['yarn', 'licenses', 'list', '--no-progress', '--json']);
            $yarn->setWorkingDirectory(DemosPlanPath::getRootPath());
            $yarn->run();

            $json = collect(explode("\n", trim($yarn->getOutput())))->last();
            $dependencies = Json::decodeToArray($json)['data']['body'];

            // uses local file, no need for flysystem
            $packageJson = Json::decodeToArray(
                \file_get_contents(DemosPlanPath::getRootPath('package.json'))
            );

            $packageJsonDependencies = \collect([])
                ->merge(\array_keys($packageJson['dependencies']))
                ->merge(\array_keys($packageJson['devDependencies']))
                ->flip();

            $progressBar = new ProgressBar($this->io, is_countable($dependencies) ? count($dependencies) : 0);
            $progressBar->start();

            $jsLicenses = collect($dependencies)
                ->map(
                    static function ($info) use ($progressBar): array {
                        [$package, $version, $license, $_, $website] = $info;

                        $progressBar->advance();

                        return compact('package', 'license', 'website');
                    }
                )
                ->filter(
                    static fn ($item): bool =>
                        // do not include items without license
                        // may occur if package could not be fetched
                        null !== $item['license']
                )
                ->filter(
                    static fn ($item): bool => $packageJsonDependencies->has($item['package'])
                )
                ->filter(static fn ($packageInfo): bool =>
                    // we may have private packages that should be hidden
                    !in_array($packageInfo['package'], self::JS_PACKAGE_DENYLIST, true))
                ->unique('package')
                ->sortBy('package');

            $progressBar->finish();

            // local file only, no need for flysystem
            $fs = new Filesystem();
            $filename = DemosPlanPath::getRootPath(self::JS_PATH_JSON);
            $formattedJsLicenses = $jsLicenses->values()->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $fs->dumpFile($filename, $formattedJsLicenses);
            $this->io->success("Updated the js vendor information to file {$filename}");

            $this->dumpJsLicenseFile($fs, $jsLicenses);
        } catch (Exception $e) {
            $this->io->error('An error occured during the update: '.$e->getMessage());
        }
    }

    protected function dumpJsLicenseFile(Filesystem $fs, Collection $jsLicenses): void
    {
        $jsFilenameLicenseFile = DemosPlanPath::getRootPath(self::JS_PATH_TEXT);

        if (!$fs->exists($jsFilenameLicenseFile)) {
            $fs->mkdir(dirname($jsFilenameLicenseFile));
        }

        $licenseString = '';
        foreach ($jsLicenses as $info) {
            // do not include packages where get Infos failed
            if ('' === $info['license']) {
                continue;
            }
            $licenseString .= $info['package'].': '.$info['license']."\n";
        }

        $licenseString .= "\nAs of ".Carbon::now()->format('d.m.Y H:i');
        $fs->dumpFile($jsFilenameLicenseFile, $licenseString);
        $this->io->success("Updated the js vendor information to file {$jsFilenameLicenseFile}");
    }

    protected function dumpPhpLicenseFile(Filesystem $fs, Collection $phpLicenses): void
    {
        $filenameLicenseFile = DemosPlanPath::getRootPath(self::PHP_PATH_TEXT);
        if (!$fs->exists($filenameLicenseFile)) {
            $fs->mkdir(dirname($filenameLicenseFile));
        }
        $licenseString = '';
        foreach ($phpLicenses as $info) {
            $licenseString .= $info['package'].': '.$info['license']."\n";
        }

        $licenseString .= "\nAs of ".Carbon::now()->format('d.m.Y H:i');
        $fs->dumpFile($filenameLicenseFile, $licenseString);
        $this->io->success("Updated PHP vendor information to file {$filenameLicenseFile}");
    }
}
