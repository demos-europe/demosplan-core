<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Map;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\Feature;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\MapLayer;
use GdImage;
use Point;
use stdClass;
use Illuminate\Support\Collection;

class PolygonIntoMapLayerMerger
{
    /**
     * Resolution factor used to correct the pixel units when writing polygons into an image.
     *
     * @var float
     */
    protected $resFactor = 1.5224666863587708;

    /**
     * @var stdClass
     */
    public $viewport;

    public function __construct()
    {
        $this->viewport = new stdClass();
    }

    /**
     * loop through the features, render each one onto the canvas.
     *
     * @param Collection<int, Feature> $geo
     */
    public function merge(Collection $geo, MapLayer $mapLayer): GdImage
    {
        $image = $mapLayer->getImage()->getCore();
        $image = $this->assertGdImage($image);
        $this->viewport->bottom = $mapLayer->getBottom();
        $this->viewport->left = $mapLayer->getLeft();
        $this->viewport->right = $mapLayer->getRight();
        $this->viewport->top = $mapLayer->getTop();
        $imageWidth = $mapLayer->getWidthInPixels();
        $imageHeight = $mapLayer->getHeightInPixels();

        /** @var Feature $geoJsonFeature */
        foreach ($geo as $geoJsonFeature) {
            $geometry = $geoJsonFeature->getGeometry();
            $feature = $geometry->out('wkt');

            $style = [
                'strokeColor'   => '#104E8B', // Strichfarbe z.B. #00000
                'strokeOpacity' => 1, // Deckkraft der Strichfarbe 1 = 100%
                'strokeWidth'   => 2, // Strichstärke
                'fillColor'     => '#0000FF', // Füllfarbe z.B. #00000
                'fillOpacity'   => 0.4, // Deckkraft der Füllfarbe 1 = 100%
                'pointRadius'   => 2, // Radius bei Punkten
            ];

            $type = $this->determineTypeOfFeature($feature);

            switch ($type) {
                case 'POINT':
                    $dst_x = $geometry->getX();
                    $dst_y = $geometry->getY();
                    $this->wkt2px(
                        $dst_x,
                        $dst_y,
                        $this->viewport,
                        $mapLayer->getWidthInPixels(),
                        $mapLayer->getHeightInPixels()
                    );
                    $color_rgb = $this->html2rgb($style['fillColor']);
                    $color = imagecolorallocatealpha(
                        $image,
                        $color_rgb[0],
                        $color_rgb[1],
                        $color_rgb[2],
                        127 - $style['fillOpacity'] * 127
                    );
                    $radius = $style['pointRadius'] * 2 * $this->resFactor;
                    imagefilledellipse($image, $dst_x, $dst_y, $radius, $radius, $color);
                    break;

                case 'LINE':
                    $color_rgb = $this->html2rgb($style['strokeColor']);
                    $color = imagecolorallocatealpha(
                        $image,
                        $color_rgb[0],
                        $color_rgb[1],
                        $color_rgb[2],
                        127 - $style['strokeOpacity'] * 127
                    );
                    imagesetthickness($image, $style['strokeWidth'] * $this->resFactor);

                    $pts = $geometry->getPoints();
                    $numPoints = is_countable($pts) ? count($pts) : 0;
                    foreach ($pts as $i => $pt) {
                        $dst_x = $pt->getX();
                        $dst_y = $pt->getY();
                        $this->wkt2px($dst_x, $dst_y, $this->viewport, $imageWidth, $imageHeight);
                        $pts[$i] = [
                            $dst_x,
                            $dst_y,
                        ];
                    }

                    for ($i = 0; $i < $numPoints - 1; ++$i) {
                        imageline($image, $pts[$i][0], $pts[$i][1], $pts[$i + 1][0], $pts[$i + 1][1], $color);
                    }
                    break;

                case 'POLYGON':
                    $color_rgb = $this->html2rgb($style['strokeColor']);
                    $lineColor = imagecolorallocatealpha($image, $color_rgb[0], $color_rgb[1], $color_rgb[2], 127 - $style['strokeOpacity'] * 127);
                    $color_rgb = $this->html2rgb($style['fillColor']);
                    $fillColor = imagecolorallocatealpha($image, $color_rgb[0], $color_rgb[1], $color_rgb[2], 127 - $style['fillOpacity'] * 127);

                    imagesetthickness($image, $style['strokeWidth'] * $this->resFactor);

                    $polyPts = [];

                    /** @var Point $point */
                    foreach ($geometry->getPoints() as $point) {
                        $dst_x = $point->getX();
                        $dst_y = $point->getY();
                        $this->wkt2px($dst_x, $dst_y, $this->viewport, $imageWidth, $imageHeight);

                        $polyPts[] = $dst_x;
                        $polyPts[] = $dst_y;
                    }

                    imagepolygon($image, $polyPts, count($polyPts) / 2, $lineColor);
                    imagefilledpolygon($image, $polyPts, count($polyPts) / 2, $fillColor);
                    break;
            }
        }

        return $image;
    }

    private function determineTypeOfFeature(string $feature): string
    {
        $wktSub = substr($feature, 0, 20);

        return false !== stripos($wktSub, 'POINT') ? 'POINT' : (false !== stripos(
            $wktSub,
            'LINE'
        ) ? 'LINE' : 'POLYGON');
    }

    /**
     * wkt2px()
     * Umrechnen einer Koordinate in Pixel.
     *
     * @param mixed    $x
     * @param mixed    $y
     * @param stdClass $viewport
     * @param int      $width
     * @param int      $height
     */
    private function wkt2px(&$x, &$y, $viewport, $width, $height)
    {
        $dx = $viewport->right - $viewport->left;
        $dy = $viewport->top - $viewport->bottom;

        $x = ($x - $viewport->left) * $width / $dx;
        $y = $height - ($y - $viewport->bottom) * $height / $dy;
    }

    /**
     * html2rgb()
     * Transformiert eine CSS-Farbangabe nach RGB.
     *
     * @param mixed $color
     *
     * @return array|false
     */
    private function html2rgb($color)
    {
        if ('#' == $color[0]) {
            $color = substr((string) $color, 1);
        }

        if (6 === strlen((string) $color)) {
            [$r, $g, $b] = [$color[0].$color[1],
                $color[2].$color[3], $color[4].$color[5], ];
        } elseif (3 === strlen((string) $color)) {
            [$r, $g, $b] = [$color[0].$color[0],
                $color[1].$color[1], $color[2].$color[2], ];
        } else {
            return false;
        }

        $r = hexdec($r);
        $g = hexdec($g);
        $b = hexdec($b);

        return [$r, $g, $b];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertGdImage(mixed $image): GdImage
    {
        if (false === $image instanceof GdImage) {
            throw new InvalidArgumentException(sprintf('Argument must be a valid GdImage type. %s given.', gettype($image)));
        }

        return $image;
    }
}
