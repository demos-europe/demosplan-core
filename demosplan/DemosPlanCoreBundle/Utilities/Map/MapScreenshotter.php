<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utilities\Map;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Logic\Map\FeaturesToMapLayersConverter;
use demosplan\DemosPlanCoreBundle\Logic\Map\GeoJsonToFeaturesConverter;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapImageToCoordinatesCropper;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapImageToPolygonCropper;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapLayerMerger;
use demosplan\DemosPlanCoreBundle\Logic\Map\MinCoordinatesExtractor;
use demosplan\DemosPlanCoreBundle\Logic\Map\PolygonIntoMapLayerMerger;
use demosplan\DemosPlanCoreBundle\Logic\Map\WmsToWmtsCoordinatesConverter;
use demosplan\DemosPlanCoreBundle\Logic\Maps\WktToGeoJsonConverter;
use demosplan\DemosPlanCoreBundle\Logic\TextIntoImageInserter;
use demosplan\DemosPlanCoreBundle\Logic\UrlFileReader;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\CoordinatesViewport;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\MapLayer;
use Exception;
use GdImage;
use GeoJson\GeoJson;
use geoPHP\geoPHP;
use Illuminate\Support\Collection;
use Intervention\Image\ImageManager;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Contracts\Translation\TranslatorInterface;

class MapScreenshotter
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    protected $width = 256;

    protected $minWidth = 256;

    protected $maxWidth = 512;

    protected $height = 256;

    protected $minHeight = 256;

    protected $maxHeight = 512;

    protected $borderWidth = 150;

    protected $outputFormat = 'PNG';

    /**
     * @var stdClass
     */
    public $viewport;

    /**
     * @var bool
     */
    private $mapEnableWmtsExport;

    public function __construct(
        private readonly GeoJsonToFeaturesConverter $geoJsonToFeaturesConverter,
        GlobalConfigInterface $globalConfig,
        private readonly ImageManager $imageManager,
        private readonly WktToGeoJsonConverter $wktToGeoJsonConverter,
        LoggerInterface $logger,
        private readonly MapImageToCoordinatesCropper $mapImageToCoordinatesCropper,
        private readonly MapImageToPolygonCropper $mapImageToPolygonCropper,
        private readonly MapLayerMerger $mapLayerMerger,
        private readonly MinCoordinatesExtractor $minCoordinatesExtractor,
        private readonly PolygonIntoMapLayerMerger $polygonIntoMapLayerMerger,
        private readonly FeaturesToMapLayersConverter $featuresToMapLayersConverter,
        private readonly TextIntoImageInserter $textIntoImageInserter,
        private readonly TranslatorInterface $translator,
        private readonly UrlFileReader $urlFileReader,
        private readonly WmsToWmtsCoordinatesConverter $wmsToWmtsCoordinatesConverter,
    ) {
        $this->logger = $logger;
        $this->mapEnableWmtsExport = $globalConfig->getMapEnableWmtsExport();
    }

    /**
     * @param string[]    $wms
     * @param string|null $copyrightText
     *
     * @throws Exception
     */
    public function makeScreenshot(
        string $polygon,
        $wms,
        $copyrightText = null,
    ): ?string {
        try {
            if ($this->mapEnableWmtsExport && $this->hasWmtsTile($polygon)) {
                return $this->makeScreenshotWmts($polygon, $copyrightText);
            } else {
                if (null === $copyrightText) {
                    $copyrightText = $this->translator->trans('map.attribution.exports', ['currentYear' => date('Y')]);
                }

                $geoJsonString = $this->getGeoJsonString($polygon);
                $viewportString = '{"centerLonLat":null,"left":576618.89258812,"bottom":5949177.4674183,'.
                    '"right":577873.0169109,"top":5950431.5917411}';
                // fetch the request params
                $this->viewport = $this->getBoundingBox($geoJsonString, Json::decodeToMatchingType($viewportString));
                $this->logger->debug('set bounding box to: '.Json::encode($this->viewport));

                // convert FeatureCollection into DTO
                $features = $this->geoJsonToFeaturesConverter->convert($polygon);

                $mapWithFeaturesResource = $this->makeScreenshotWms($features, $wms, $copyrightText);

                return $this->saveImageToFile($mapWithFeaturesResource);
            }
        } catch (Exception $e) {
            $this->logger->error('Error -> ', [$e, $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Note that this method requires the $this->viewport to be set before it is used.
     *
     * @param string[] $wmsUrls
     *
     * @throws Exception
     */
    public function makeScreenshotWms(Collection $geo, array $wmsUrls, string $copyrightText): GdImage
    {
        /* einheitliche BBOX setzen */
        $bbox = $this->viewport->left.','.$this->viewport->bottom.','.$this->viewport->right.','.$this->viewport->top;
        $image = $this->preparePlaceholderMapWms();
        $this->getLayersTilesAndMergeThemIntoMap($wmsUrls, $bbox, $image);
        $image = $this->imageManager->make($image);

        $mapLayer = new MapLayer(
            new CoordinatesViewport(
                $this->viewport->left,
                $this->viewport->bottom,
                $this->viewport->right,
                $this->viewport->top
            ),
            $image,
            ''
        );
        $imageWithPolygon = $this->polygonIntoMapLayerMerger->merge($geo, $mapLayer);
        $this->textIntoImageInserter->insert($imageWithPolygon, $this->height, $copyrightText);

        return $imageWithPolygon;
    }

    /**
     * @param string|null $copyrightText
     *
     * @throws Exception
     */
    public function makeScreenshotWmts(
        string $geoJson,
        $copyrightText = null,
    ): ?string {
        try {
            $copyrightText ??= $this->translator->trans('map.attribution.exports', ['currentYear' => date('Y')]);

            $features = $this
                ->geoJsonToFeaturesConverter
                ->convert($geoJson);

            $layerImages = $this
                ->featuresToMapLayersConverter
                ->convert($features);

            $adaptedWmsCoordinatesLayers = $this
                ->wmsToWmtsCoordinatesConverter
                ->convert($layerImages->toArray());

            $minLayerCoordinates = $this
                ->minCoordinatesExtractor
                ->extract($adaptedWmsCoordinatesLayers);

            $mergedLayerImage = $this
                ->mapLayerMerger
                ->merge($adaptedWmsCoordinatesLayers);

            $minMergedLayerImage = $this
                ->mapImageToCoordinatesCropper
                ->crop($mergedLayerImage, $minLayerCoordinates);

            $croppedImage = $this
                ->mapImageToPolygonCropper
                ->crop($minMergedLayerImage, $features);

            $mapWithPolygon = $this
                ->polygonIntoMapLayerMerger
                ->merge($features, $croppedImage);

            $this
                ->textIntoImageInserter
                ->insert($mapWithPolygon, $croppedImage->getHeightInPixels(), $copyrightText);

            return $this->saveImageToFile($mapWithPolygon);
        } catch (Exception $e) {
            $this->logger->error('Error -> ', [$e, $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function getGeoJsonString(string $polygon): string
    {
        return $this->wktToGeoJsonConverter->convertIfNeeded($polygon);
    }

    /**
     * Check whether any of the recorded layers is a wmts layer.
     * For that we need to go over all the Tiles in all the Print Layers in the polygon and
     * check if any uses a WMTS service.
     *
     * @throws Exception
     */
    protected function hasWmtsTile(string $polygon): bool
    {
        $geo = GeoJson::jsonUnserialize(Json::decodeToMatchingType($polygon));
        $features = $geo->getFeatures();
        foreach ($features as $feature) {
            $properties = $feature->getProperties();
            if (null === $properties) {
                continue;
            }
            $metadata = $properties['metadata'] ?? null;
            if (!$metadata instanceof stdClass || !property_exists($metadata, 'printLayers')) {
                continue;
            }
            $printLayers = $metadata->printLayers;
            if (null !== $printLayers && is_array($printLayers)) {
                foreach ($printLayers as $printLayer) {
                    $tiles = data_get($printLayer, 'tiles');
                    if (null !== $tiles && is_array($tiles)) {
                        foreach ($printLayer->tiles as $tile) {
                            if (stripos($tile->url ?? '', 'tilematrixset')) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Uses the GD library.
     */
    private function preparePlaceholderMapWms(): GdImage
    {
        $image = imagecreatetruecolor($this->width, $this->height);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);

        return $image;
    }

    /**
     * Diese Funktion berechnet anhand der Einzeichnungen die Bildgröße und Bounding Box.
     */
    public function getBoundingBox(string $geoJsonString, $viewport): stdClass
    {
        $geoPhp = geoPHP::load($geoJsonString);
        $bBox = $geoPhp->getBBox();

        $viewport = $this->setViewportDimensions($bBox, $viewport);

        $this->height = (int) ($viewport->top - $viewport->bottom);
        $this->width = (int) ($viewport->right - $viewport->left);
        $this->adjustPictureSize($bBox['maxy'], $viewport, $bBox['miny'], $bBox['minx'], $bBox['maxx']);

        return $viewport;
    }

    /**
     * getImage().
     *
     * @param string $path
     * @param string $type
     *
     * @throws Exception
     */
    private function getImage($path, $type = ''): GdImage|false
    {
        // Bild-Typ ermitteln, wenn nicht mit übergeben
        if (empty($type)) {
            // Bilder, die nicht statisch sind (WMS-Aufrufe bspw.)
            if (!str_contains(substr($path, -4), '.')
                || 'http' === strtolower(substr($path, 0, 4))) {
                // @todo: use Symfony HttpClient see UrlFileReader
                $func = 'https:' === strtolower(substr($path, 0, 6)) ? 'file_get_contents_https'
                    : 'fileGetContentsCurl';
                if (false === ($imageContent = @$func($path))) {
                    return false;
                }

                $tempPath = 'temp_'.md5(microtime().random_int(0, mt_getrandmax())).'.img';
                // local file is valid, no need for flysystem
                file_put_contents($tempPath, $imageContent);
                $path = $tempPath;
            }
            [$w, $h, $type] = @getimagesize($path);
        }

        $image = false;

        switch ($type) {
            case IMAGETYPE_GIF:
                $image = @imagecreatefromgif($path);
                break;
            case IMAGETYPE_JPEG:
                $image = @imagecreatefromjpeg($path);
                break;
            case IMAGETYPE_PNG:
                $image = @imagecreatefrompng($path);
                break;
            case IMAGETYPE_BMP:
                $image = @imagecreatefromwbmp($path);
                break;
        }

        return $image;
    }

    /**
     * save to disk and tell the client where they can pick it up.
     */
    private function saveImage(GdImage $image, string $file, string $format): bool
    {
        return match ($format) {
            'PNG'   => imagepng($image, $file),
            'GIF'   => imagegif($image, $file),
            'BMP'   => imagebmp($image, $file),
            'JPG'   => imagejpeg($image, $file),
            default => false,
        };
    }

    private function imagecopymergeAlpha(
        GdImage $dst_im,
        GdImage $src_im,
        int $dst_x,
        int $dst_y,
        int $src_x,
        int $src_y,
        int $src_w,
        int $src_h,
        int $opacity,
    ): void {
        // Zwischenbild erzeugen
        $cut = imagecreatetruecolor($src_w, $src_h);
        // Quell- und Zielbild hineinkopieren (zuerst Ziel, dann Quelle)
        imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
        imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);

        // Zwischenbild in Zielbild kopieren
        imagecopymerge($dst_im, $cut, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $opacity);
    }

    private function getTemporaryPath(): string
    {
        return DemosPlanPath::getTemporaryPath();
    }

    /**
     * @throws Exception
     */
    private function saveImageToFile(GdImage $image): string
    {
        $format = $this->outputFormat;
        $file = $this->getTemporaryPath().md5(microtime().random_int(0, mt_getrandmax())).'.'.$format;
        $this->saveImage($image, $file, $format);

        return $file;
    }

    /**
     * @throws Exception
     */
    private function getLayersTilesAndMergeThemIntoMap(array $wmsUrls, string $bbox, GdImage $image): void
    {
        foreach ($wmsUrls as $tile) {
            $tile['url'] .= "&bbox=$bbox&width=$this->width&height=$this->height";

            $tempFile = $this->getTemporaryPath().'/tmp_wms_'.md5(
                microtime().random_int(0, mt_getrandmax())
            ).'.png';

            $wmsUrl = str_replace(' ', '%20', trim($tile['url']));
            $imageContent = $this->urlFileReader->getFileContents($wmsUrl);
            if ('' === $imageContent) {
                $this->logger->error(
                    'Konnte eines der Bilder nicht erzeugen... ',
                    ['wmsUrl' => $wmsUrl]
                );
                continue;
            }

            // local file is valid, no need for flysystem
            file_put_contents($tempFile, $imageContent);
            if (false === ($imageData = getimagesize($tempFile))) {
                @unlink($tempFile);
                $this->logger->error(
                    'Konnte Bildinformationen eines Bildes nicht ermitteln ... ',
                    ['wmsUrl' => $tile]
                );
                continue;
            }

            [$tileWidth, $tileHeight, $tileFormat] = $imageData;
            if (false !== $tileImage = $this->getImage($tempFile, $tileFormat)) {
                $this->imagecopymergeAlpha($image, $tileImage, 0, 0, 0, 0, $tileWidth, $tileHeight, 100);
            }
            @unlink($tempFile);
        }
    }

    protected function adjustPictureSize(float $top, stdClass $viewport, float $bottom, float $left, float $right): void
    {
        // Anpassen der Bildhöhe wenn die ausgerechnete Höhe nicht der minimal Höhe entspricht
        if ($this->height < $this->minHeight) {
            $height = ($this->minHeight - $this->height) / 2;
            $viewport->top = $top + $height;
            $viewport->bottom = $bottom - $height;
            $this->height = $this->minHeight;
        }

        // Anpassen der Bildbreite wenn die ausgerechnete Höhe nicht der minimal Höhe entspricht
        if ($this->width < $this->minWidth) {
            $width = ($this->minWidth - $this->width) / 2;
            $viewport->left = $left - $width;
            $viewport->right = $right + $width;
            $this->width = $this->minWidth;
        }

        // Anpassen der Bildbreite und -höhe wenn die maximale Größe überschritten wird
        if ($this->width > $this->maxWidth || $this->height > $this->maxHeight) {
            $this->adjustPictureSizeWhenTooBig($top, $viewport, $bottom, $left, $right);
        }
    }

    /**
     * @param int[] $viewportDimensions
     */
    private function setViewportDimensions(array $viewportDimensions, stdClass $viewport): stdClass
    {
        $viewport->left = $viewportDimensions['minx'] - $this->borderWidth;
        $viewport->right = $viewportDimensions['maxx'] + $this->borderWidth;
        $viewport->top = $viewportDimensions['maxy'] + $this->borderWidth;
        $viewport->bottom = $viewportDimensions['miny'] - $this->borderWidth;

        return $viewport;
    }

    private function adjustPictureSizeWhenTooBig(float $top, stdClass $viewport, float $bottom, float $left, float $right): void
    {
        $factorWidth = 0;
        $factorHeight = 0;

        if ($this->width > $this->maxWidth) {
            $factorWidth = $this->width / $this->maxWidth;
        }

        if ($this->height > $this->maxHeight) {
            $factorHeight = $this->height / $this->maxHeight;
        }

        if ($factorWidth > $factorHeight) {
            $factor = $factorWidth;
        } elseif ($factorWidth < $factorHeight) {
            $factor = $factorHeight;
        } else {
            $factor = $factorWidth;
        }

        if (0 != $factor) {
            $this->width = (int) ($this->width / $factor);
            $this->height = (int) ($this->height / $factor);
            if ($this->height < $this->minHeight) {
                $height = ($this->minHeight - $this->height) / 2;
                $viewport->top = $top + $height * $factor;
                $viewport->bottom = $bottom - $height * $factor;
                $this->height = $this->minHeight;
            }
            if ($this->width < $this->minWidth) {
                $width = ($this->minWidth - $this->width) / 2;
                $viewport->left = $left - $width * $factor;
                $viewport->right = $right + $width * $factor;
                $this->width = $this->minWidth;
            }
        }
    }
}
