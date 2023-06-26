<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use demosplan\DemosPlanCoreBundle\Logic\Maps\WktToGeoJsonConverter;
use demosplan\DemosPlanCoreBundle\Utilities\Map\MapScreenshotter;
use Psr\Container\ContainerInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twigrelated functions to Map.
 *
 * @deprecated used at all?
 */
class MapExtension extends ExtensionBase
{
    /**
     * @var array<int, string>
     */
    private $mapDefaultProjection;

    /**
     * @var WktToGeoJsonConverter
     */
    private $wktToGeoJsonConverter;

    /**
     * @param array<int, string> $mapDefaultProjection
     */
    public function __construct(
        array $mapDefaultProjection,
        ContainerInterface $container,
        WktToGeoJsonConverter $wktToGeoJsonConverter)
    {
        parent::__construct($container);
        $this->mapDefaultProjection = $mapDefaultProjection;
        $this->wktToGeoJsonConverter = $wktToGeoJsonConverter;
    }

    /**
     * @return array<int, TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('convertLegacyPolygon', [$this, 'convertLegacyPolygonFilter']),
        ];
    }

    /**
     * @return array<int, TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('defaultProjectionLabel', [$this, 'getDefaultProjectionLabel']),
            new TwigFunction('defaultProjectionValue', [$this, 'getDefaultProjectionValue']),
        ];
    }

    public function getDefaultProjectionLabel(): string
    {
        return $this->mapDefaultProjection['label'];
    }

    public function getDefaultProjectionValue(): string
    {
        return $this->mapDefaultProjection['value'];
    }

    /**
     * Converts legacyformats of polygon strings to valid geojson.
     *
     * @param string $string
     *
     * @return string
     */
    public function convertLegacyPolygonFilter($string)
    {
        return $this->wktToGeoJsonConverter->convertIfNeeded($string);
    }

    public static function getSubscribedServices(): array
    {
        return [
            MapScreenshotter::class,
        ];
    }
}
