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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Yaml\Yaml;

final class FrontendAssetProvider
{
    public function __construct(
        private readonly PermissionsInterface $permissions,
        private readonly AddonRegistry $registry,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getFrontendClassesForHook(string $hookName): array
    {
        $assetList = array_map(function (AddonInfo $addonInfo) use ($hookName) {
            if (!$addonInfo->isEnabled() || !$addonInfo->hasUIHooks()) {
                return [];
            }

            $uiData = $addonInfo->getUIHooks();

            if (!array_key_exists($hookName, $uiData['hooks'])) {
                return [];
            }

            $hookData = $uiData['hooks'][$hookName];
            $manifestPath = DemosPlanPath::getRootPath($addonInfo->getInstallPath()).'/'.$uiData['manifest'];

            // Return if no access granted for that addon at that entrypoint
            if (array_key_exists('permissions', $hookData['options'])
                && !$this->permissions->hasPermissions($hookData['options']['permissions'], 'OR')) {
                return [];
            }

            try {
                $entries = $this->getAssetPathsFromManifest($manifestPath, $hookData['entry']);

                if (!array_key_exists('js', $entries)) {
                    throw new AddonException('Entry has no javascript and is thus pretty much useless');
                }

                $assetContents = [];
                $assetUrls = [];

                foreach ($entries['js'] as $entry) {
                    if (str_ends_with($entry, '.esm.js')) {
                        // new-format addon build: serve by URL, frontend `import()`s it directly
                        $assetUrls[$entry] = $this->urlGenerator->generate(
                            'core_addon_asset',
                            ['addonName' => $addonInfo->getName(), 'filename' => $entry],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        );
                        continue;
                    }

                    // legacy UMD bundle: transport source inline, frontend still `eval`s it.
                    // Kept until every addon has migrated to ESM output.
                    $entryFilePath = DemosPlanPath::getRootPath($addonInfo->getInstallPath()).'/dist/'.$entry;
                    // uses local file, no need for flysystem
                    $assetContents[$entry] = file_get_contents($entryFilePath);
                }

                if ([] === $assetContents && [] === $assetUrls) {
                    return [];
                }
            } catch (AddonException) {
                return [];
            }

            return $this->createAddonFrontendAssetsEntry($hookData, $assetContents, $assetUrls);
        }, $this->registry->getAddonInfos());

        // avoid exposing addon information unnecessarily
        return array_filter($assetList, fn (array $assetInfo) => [] !== $assetInfo);
    }

    /**
     * Resolve an addon's built asset file to an absolute filesystem path, validating that
     * $filename is actually declared in that addon's manifest first. Used by AddonAssetController
     * to serve ESM bundles by URL without trusting the requested filename as a path fragment.
     *
     * @return string|null the absolute file path, or null if the addon/asset is not valid
     */
    public function resolveAssetFilePath(string $addonName, string $filename): ?string
    {
        $addonInfo = $this->registry->getAddonInfos()[$addonName] ?? null;

        if (!$addonInfo instanceof AddonInfo || !$addonInfo->isEnabled() || !$addonInfo->hasUIHooks()) {
            return null;
        }

        $uiData = $addonInfo->getUIHooks();
        $manifestPath = DemosPlanPath::getRootPath($addonInfo->getInstallPath()).'/'.$uiData['manifest'];

        if (!file_exists($manifestPath) || !$this->manifestDeclaresJsAsset($manifestPath, $filename)) {
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
     * Whether the manifest declares $filename as a js asset of any of its entrypoints.
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
            AddonException::invalidManifest($manifestPath);
        }

        $manifestContent = Yaml::parseFile($manifestPath);

        if (!array_key_exists($entryName, $manifestContent['entrypoints'])) {
            AddonException::manifestEntryNotFound($entryName);
        }

        return $manifestContent['entrypoints'][$entryName]['assets'];
    }
}
