<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use GuzzleHttp\Exception\InvalidArgumentException;
use RuntimeException;
use Illuminate\Support\Collection;
use Twig\TwigFunction;

class WebpackBundleExtension extends ExtensionBase
{
    /**
     * These bundles will not generate with a data-bundle attribute.
     */
    private const NON_DATA_BUNDLES = [
        // these bundles initialize demosplan's frontend
        'bs.js',
        'common.js',
        'core.js',

        // extracted vendors
        'd3.js',
        'ol.js',
        'leaflet.js',
        'jquery-3.5.1.min.js',

        // these bundles do not require a vue instance on #app. see T14094
        'core-sidenav.js',
    ];

    /**
     * The webpack manifest.
     *
     * This is the combination of `dplan.manifest.json` and `styles.manifest.json`.
     *
     * @var array
     */
    protected $dplanManifest = [];

    /**
     * The legacy files manifest.
     *
     * @var array
     */
    protected $legacyManifest = [];

    /**
     * Initially load manifests.
     *
     * @throws JsonException
     */
    private function loadManifests(): void
    {
        $dplanManifest = $this->loadManifest('dplan');
        $stylesManifest = $this->loadManifest('styles');

        // Atm the styles manifest contains several js entries which would replace
        // the $dplanManifest equivalents which leads to resolve errors in the frontend.
        $cssIdentifier = '.css';
        $trimmedStylesManifest = array_filter($stylesManifest, function ($key, $value) use ($cssIdentifier) {
            return str_contains($key, $cssIdentifier) && str_contains($value, $cssIdentifier);
        }, ARRAY_FILTER_USE_BOTH);

        $this->dplanManifest = array_merge($dplanManifest, $trimmedStylesManifest);

        $this->legacyManifest = $this->loadManifest('legacy');
    }

    private function areManifestsLoaded(): bool
    {
        return 0 < count($this->dplanManifest) && 0 < count($this->legacyManifest);
    }

    private function loadManifestsIfRequired(): void
    {
        if (!$this->areManifestsLoaded()) {
            $this->loadManifests();
        }
    }

    /**
     * Provide `webpackBundle` and `webpackBundles` functions to twig.
     *
     * @return array<int, TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('webpackBundle', $this->webpackBundle(...), ['is_safe' => ['html']]),
            new TwigFunction('webpackBundles', $this->webpackBundles(...), ['is_safe' => ['html']]),
            new TwigFunction('webpackBundlePath', $this->webpackBundlePath(...)),
        ];
    }

    /**
     * Generate html code for a set of webpack bundles.
     *
     * @param bool $legacy
     */
    public function webpackBundles(array $bundles, $legacy = false): string
    {
        $this->loadManifestsIfRequired();

        return collect($bundles)->map(
            fn ($bundleName) => $this->webpackBundle($bundleName, $legacy)
        )
            ->implode("\n");
    }

    /**
     * Return an appropriate script or link tag for referencing a webpack bundle.
     *
     * @param string $bundleName
     * @param bool   $legacy
     */
    public function webpackBundle($bundleName, $legacy = false): string
    {
        $this->loadManifests();

        if (array_key_exists($bundleName, $this->dplanManifest)) {
            $bundleSrcs = $this->getBundleAndRelatedChunkSplits($bundleName, 'dplanManifest');
        } elseif ($legacy && array_key_exists($bundleName, $this->legacyManifest)) {
            $bundleSrcs = $this->getBundleAndRelatedChunkSplits($bundleName, 'legacyManifest');
        } else {
            $bundleSrcs = collect([$bundleName]);
        }

        return $bundleSrcs->map(
            function ($bundleSrc) use ($bundleName, $legacy) {
                $dataBundle = $bundleName;

                return $this->renderTag($bundleSrc, $legacy, $bundleName, $dataBundle);
            }
        )
            ->implode("\n");
    }

    /**
     * Just return the mapping result of a bundle, represents assetics' asset() twig function.
     */
    public function webpackBundlePath(string $bundleName): string
    {
        if (array_key_exists($bundleName, $this->dplanManifest)) {
            return $this->formatBundlePath($this->dplanManifest[$bundleName]);
        }

        if (array_key_exists($bundleName, $this->legacyManifest)) {
            return $this->formatBundlePath($this->legacyManifest[$bundleName]);
        }

        return '';
    }

    protected function formatBundlePath(string $bundlePath): string
    {
        if ('/' !== $bundlePath[0]) {
            $bundlePath = '/'.$bundlePath;
        }

        return $bundlePath;
    }

    /**
     * @param string $bundleSrc
     */
    protected function renderTag($bundleSrc, bool $legacy, string $bundleName, string $dataBundle): string
    {
        $tagTemplate = '<script src="%s"></script>';

        if (!$legacy && !in_array($bundleName, self::NON_DATA_BUNDLES, true)) {
            $dataBundle = explode('.', $dataBundle)[0];
            $tagTemplate = '<script src="%s" data-bundle="%s"></script>';
        }

        if (strpos($bundleName, '.css') > 0) {
            $tagTemplate = '<link rel="stylesheet" href="%s">';
        }

        return sprintf($tagTemplate, $this->formatBundlePath($bundleSrc), $dataBundle);
    }

    /**
     * @return array<string,string> A webpack manifest
     *
     * @throws JsonException
     */
    protected function loadManifest(string $manifest): array
    {
        $manifestFile = DemosPlanPath::getProjectPath("web/{$manifest}.manifest.json");

        $manifestArray = [];
        if (file_exists($manifestFile)) {
            try {
                $manifestArray = Json::decodeToArray(file_get_contents($manifestFile));
            } catch (InvalidArgumentException) {
                throw new RuntimeException(<<<ERR
The manifest

    $manifestFile

could not be loaded because of a syntax error.
This likely happened due to a broken webpack build.
Please delete the

    $manifestFile

and re-run webpack (fe build <project>).
ERR);
            }
        }

        return $manifestArray;
    }

    protected function getBundleAndRelatedChunkSplits(string $bundleName, string $manifest): Collection
    {
        return collect($this->$manifest)
            ->keys()
            ->filter(
                static fn ($possibleBundleName) => str_contains((string) $possibleBundleName, $bundleName)
            )
            ->map(
                fn ($relatedBundleName) => $this->{$manifest}[$relatedBundleName]
            );
    }

    public static function getSubscribedServices(): array
    {
        return [
            GlobalConfigInterface::class => GlobalConfig::class,
        ];
    }

    public function getGlobalConfig(): GlobalConfigInterface
    {
        // this is not the huge symfony container but a special small one
        // to avoid loading dependencies on every twig call
        // https://symfonycasts.com/screencast/symfony-doctrine/service-subscriber
        return $this->container->get(GlobalConfigInterface::class);
    }
}
