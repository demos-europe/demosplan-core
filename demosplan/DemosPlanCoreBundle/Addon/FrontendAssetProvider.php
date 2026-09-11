<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use DemosEurope\DemosplanAddon\Permission\PermissionEvaluatorInterface;
use demosplan\DemosPlanCoreBundle\Exception\AddonException;
use demosplan\DemosPlanCoreBundle\Permissions\RuntimePermissionIdentifier;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Symfony\Component\Yaml\Yaml;

final readonly class FrontendAssetProvider
{
    public function __construct(
        private PermissionEvaluatorInterface $permissionEvaluator,
        private AddonRegistry $registry,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getFrontendClassesForHook(string $hookName): array
    {
        $assetList = [];

        foreach ($this->registry->getAddonInfos() as $addonName => $addonInfo) {
            $assets = $this->getFrontendClassForHook($addonName, $addonInfo, $hookName);

            // avoid exposing addon information unnecessarily
            if ([] !== $assets) {
                $assetList[$addonName] = $assets;
            }
        }

        return $assetList;
    }

    /**
     * @return array<string, mixed>
     */
    private function getFrontendClassForHook(string $addonName, AddonInfo $addonInfo, string $hookName): array
    {
        if (!$addonInfo->isEnabled() || !$addonInfo->hasUIHooks()) {
            return [];
        }

        $uiData = $addonInfo->getUIHooks();

        if (!array_key_exists($hookName, $uiData['hooks'])) {
            return [];
        }

        $hookData = $uiData['hooks'][$hookName];

        // Return if no access granted for that addon at that entrypoint
        if (!$this->isHookEnabled($addonName, $hookData['options'])) {
            return [];
        }

        $manifestPath = DemosPlanPath::getRootPath($addonInfo->getInstallPath()).'/'.$uiData['manifest'];
        $assetContents = $this->readAssetContents($addonInfo, $manifestPath, $hookData['entry']);

        if ([] === $assetContents) {
            return [];
        }

        return $this->createAddonFrontendAssetsEntry($hookData, $assetContents);
    }

    /**
     * @return array<string, string> mapping from asset name to asset content, empty if unreadable
     */
    private function readAssetContents(AddonInfo $addonInfo, string $manifestPath, string $entryName): array
    {
        try {
            $entries = $this->getAssetPathsFromManifest($manifestPath, $entryName);

            if (!array_key_exists('js', $entries)) {
                throw new AddonException('Entry has no javascript and is thus pretty much useless');
            }
        } catch (AddonException) {
            return [];
        }

        $assetContents = [];

        foreach ($entries['js'] as $entry) {
            // Try to get the content of the actual asset
            $entryFilePath = DemosPlanPath::getRootPath($addonInfo->getInstallPath()).'/dist/'.$entry;
            // uses local file, no need for flysystem
            $assetContents[$entry] = file_get_contents($entryFilePath);
        }

        return $assetContents;
    }

    /**
     * A hook may be guarded by permissions declared either by the addon itself or by the core.
     * Addon permissions live in a collection of their own, so they have to be addressed with the
     * owning addon as identifier - a plain name lookup would only ever reach the core permissions.
     *
     * @param array<string, mixed> $hookOptions
     */
    private function isHookEnabled(string $addonName, array $hookOptions): bool
    {
        if (!array_key_exists('permissions', $hookOptions)) {
            return true;
        }

        foreach ($hookOptions['permissions'] as $permissionName) {
            $addonPermission = RuntimePermissionIdentifier::forAddon($permissionName, $addonName);
            $identifier = $this->permissionEvaluator->isPermissionKnown($addonPermission)
                ? $addonPermission
                : RuntimePermissionIdentifier::forCore($permissionName);

            if ($this->permissionEvaluator->isPermissionEnabled($identifier)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string|array> $hookData
     * @param array<string, string>       $assetContents
     *
     * @return array<string, array{entry:string, options:array, content:string}>
     */
    private function createAddonFrontendAssetsEntry(array $hookData, array $assetContents): array
    {
        return [
            'entry'   => $hookData['entry'],
            'options' => $hookData['options'],
            'content' => $assetContents,
        ];
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
}
