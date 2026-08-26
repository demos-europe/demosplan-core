<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Exception\AddonException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Yaml\Yaml;

final readonly class FrontendAssetProvider
{
    public function __construct(
        private AddonRegistry $registry,
        private LoggerInterface $logger,
        private PermissionsInterface $permissions,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Collect all frontend assets for a given hook name, returning an array of entries with the entry name, options,
     * and either inline content or a URL to the asset.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFrontendAssets(string $hookName): array
    {
        $assetList = array_map(
            fn (AddonInfo $addonInfo): array => $this->lookupAssetInfo($addonInfo, $hookName),
            $this->registry->getAddonInfos()
        );

        // avoid exposing empty entries for disabled addons or those without permission to use the hook
        return array_filter($assetList, fn (array $assetInfo) => [] !== $assetInfo);
    }

    /**
     * Lookup the assets for a given addon and hook, returning an array with the entry name, options,
     * and either inline content or a URL to the asset.
     *
     * The legacy UMD bundle format is still supported until we can be sure that all addons have migrated to ESM output.
     *
     * @return array{entry: string, options: array, content?: array<string, string>, urls?: array<string, string>}|array{}
     */
    private function lookupAssetInfo(AddonInfo $addonInfo, string $hookName): array
    {
        if (!$addonInfo->isEnabled() || !$addonInfo->hasUIHooks()) {
            return [];
        }

        $uiData = $addonInfo->getUIHooks();
        $hookData = $uiData['hooks'][$hookName] ?? null;
        $addonName = $addonInfo->getName();

        if (!is_array($hookData) || !$this->hasHookPermission($hookData, $addonName, $hookName)) {
            return [];
        }

        $manifestPath = DemosPlanPath::getRootPath($addonInfo->getInstallPath()).'/'.$uiData['manifest'];

        try {
            $entries = $this->getAssetPathsFromManifest($manifestPath, $hookData['entry']);

            if (!array_key_exists('js', $entries)) {
                throw new AddonException('Entry has no javascript and is thus pretty much useless');
            }

            [$assetContents, $assetUrls] = $this->collectJavascriptAssets($addonInfo, $hookName, $entries['js']);
        } catch (AddonException) {
            return [];
        }

        if ([] === $assetContents && [] === $assetUrls) {
            return [];
        }

        return $this->createAddonFrontendAssetsEntry($hookData, $assetContents, $assetUrls);
    }

    /**
     * @param array<string, string|array> $hookData
     */
    private function hasHookPermission(array $hookData, string $addonName, string $hookName): bool
    {
        $permissions = $hookData['options']['permissions'] ?? null;

        if (null === $permissions) {
            $this->logger->warning("Addon hook {$addonName}:{$hookName} has no permissions defined, allowing all users to access it.");

            return true;
        }

        return $this->permissions->hasPermissions($permissions, 'OR');
    }

    /**
     * @param array<int, mixed> $javascriptEntries
     *
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function collectJavascriptAssets(AddonInfo $addonInfo, string $hookName, array $javascriptEntries): array
    {
        $assetContents = [];
        $assetUrls = [];

        foreach ($javascriptEntries as $entry) {
            if (!is_string($entry)) {
                continue;
            }

            if (str_ends_with($entry, '.esm.js')) {
                // new-format addon build: serve by URL, frontend `import()`s it directly
                list($addonVendor, $addonName) = explode('/', $addonInfo->getName(), 2);

                // safeguard against malformed addon names, which would break the URL generation
                if (empty($addonVendor) || empty($addonName)) {
                    $this->logger->error(
                        "Addon {$addonInfo->getName()} has an invalid name, cannot generate asset URL for {$entry}. "
                        .'Please check the addon manifest.'
                    );

                    continue;
                }

                $assetUrls[$entry] = $this->urlGenerator->generate(
                    'core_addon_asset',
                    [
                        'addonVendor' => $addonVendor,
                        'addonName'   => $addonName,
                        'hookName'    => $hookName,
                        'filename'    => $entry,
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                continue;
            }

            $this->logger->warning(
                "Addon {$addonInfo->getName()} is using a legacy UMD bundle for frontend assets. "
                .'Please upgrade demosplan-addon-client-builder to the latest version.'
            );

            // legacy UMD bundle: transport source inline, frontend still `eval`s it.
            // Kept until every addon has migrated to ESM output.
            $entryFilePath = DemosPlanPath::getRootPath($addonInfo->getInstallPath()).'/dist/'.$entry;
            // uses local file, no need for flysystem
            $assetContents[$entry] = file_get_contents($entryFilePath);
        }

        return [$assetContents, $assetUrls];
    }

    /**
     * Resolve an addon's built asset file to an absolute filesystem path, validating that
     * $filename is actually declared in that addon's manifest first. Used by AddonAssetController
     * to serve ESM bundles by URL without trusting the requested filename as a path fragment.
     *
     * @param string $addonName the full addon name (vendor/addon)
     * @param string $hookName  the hook name for which the asset is requested
     * @param string $filename  the requested filename, e.g. "my-bundle.js"
     *
     * @return string|null the absolute file path, or null if the addon/asset is not valid
     */
    public function resolveAssetFilePath(string $addonName, string $hookName, string $filename): ?string
    {
        $addonInfo = $this->registry->getAddonInfos()[$addonName] ?? null;

        if (!$this->checkFileAccess($addonInfo, $hookName, $filename)) {
            return null;
        }

        $filePath = DemosPlanPath::getRootPath($addonInfo->getInstallPath()).'/dist/'.$filename;

        return is_file($filePath) ? $filePath : null;
    }

    /**
     * @param array<string, string|array> $hookData
     * @param array<string, string>       $assetContents
     * @param array<string, string>       $assetUrls
     *
     * @return array{entry: string, options: array, content?: array<string, string>, urls?: array<string, string>}
     */
    private function createAddonFrontendAssetsEntry(array $hookData, array $assetContents, array $assetUrls): array
    {
        $entry = [
            'entry'   => $hookData['entry'],
            'options' => $hookData['options'],
        ];

        if ([] !== $assetContents) {
            $entry['content'] = $assetContents;
        }

        if ([] !== $assetUrls) {
            $entry['urls'] = $assetUrls;
        }

        return $entry;
    }

    /**
     * Whether the manifest declares $filename as a js asset of its entrypoints.
     */
    private function manifestDeclaresJsAsset(string $manifestPath, string $filename): bool
    {
        $manifestContent = Yaml::parseFile($manifestPath);

        foreach ($manifestContent['entrypoints'] ?? [] as $entrypoint) {
            if (in_array($filename, $entrypoint['assets']['js'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the asset dictionary of an entry.
     *
     * Returns a dictionary of assets mapped by file type (i.e. ['js' => ['asset.js']])
     *
     * @return array<string,mixed>
     *
     * @throws AddonException
     */
    private function getAssetPathsFromManifest(string $manifestPath, string $entryName): array
    {
        // uses local file, no need for flysystem
        if (!file_exists($manifestPath)) {
            throw AddonException::invalidManifest($manifestPath);
        }

        $manifestContent = Yaml::parseFile($manifestPath);

        if (!array_key_exists($entryName, $manifestContent['entrypoints'])) {
            throw AddonException::manifestEntryNotFound($entryName);
        }

        return $manifestContent['entrypoints'][$entryName]['assets'];
    }

    /**
     * File-level permission checks for serving an addon's built asset file by URL.
     * Validates for scripts and corresponding sourcemap files.
     */
    private function checkFileAccess(?AddonInfo $addonInfo, string $hookName, string $filename): bool
    {
        if (!$addonInfo instanceof AddonInfo || !$addonInfo->isEnabled() || !$addonInfo->hasUIHooks()) {
            return false;
        }

        $uiData = $addonInfo->getUIHooks();

        $manifestPath = DemosPlanPath::getRootPath($addonInfo->getInstallPath()).'/'.$uiData['manifest'];

        // If the requested filename is a source map, validate against the corresponding JS file instead
        $validationPath = $filename;
        if (str_ends_with($filename, '.js.map')) {
            $validationPath = substr($filename, 0, -4);
        }

        if (!file_exists($manifestPath) || !$this->manifestDeclaresJsAsset($manifestPath, $validationPath)) {
            return false;
        }

        $hookData = $uiData['hooks'][$hookName] ?? null;

        if (!is_array($hookData) || !$this->hasHookPermission($hookData, $addonInfo->getName(), $hookName)) {
            return false;
        }

        return true;
    }
}
