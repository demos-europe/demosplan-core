<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\PointCountySplit;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use stdClass;

/**
 * Diplankarte sends the location of a statement as two values: a point and a county.
 * The statement has only one polygon column, so both are joined into ONE FeatureCollection when saving
 * and separated again when a draft statement is loaded.
 */
class PointCountySplitService
{
    /*
     * Names of the two request fields (FE -> BE). The same names are used to return
     * the two values when a draft statement is loaded (BE -> FE).
     */
    public const POINT_FIELD = 'r_location_geometry';
    public const COUNTY_FIELD = 'r_location_county_geometry';

    /*
     * Internal tag, not sent by the FE: every feature in the polygon column gets this property,
     * so the two values can be separated again.
     */
    private const TYPE_PROPERTY = 'locationType';
    private const TYPE_POINT = 'point';
    private const TYPE_COUNTY = 'county';

    // Permission that switches the point/county handling on
    private const PERMISSION = 'feature_diplankarte_split_point_county';

    public function __construct(private readonly CurrentUserInterface $currentUser)
    {
    }

    /**
     * Polygon to store for the location 'point': the point geometry or, if the permission is set,
     * point and county joined into one FeatureCollection.
     *
     * @throws JsonException
     */
    public function extractGeometry(array $data): string
    {
        $pointGeometry = (string) ($data[self::POINT_FIELD] ?? '');
        if (!$this->currentUser->hasPermission(self::PERMISSION)) {
            return $pointGeometry;
        }

        return $this->merge($pointGeometry, (string) ($data[self::COUNTY_FIELD] ?? ''));
    }

    /**
     * Joins point and county into ONE FeatureCollection and tags every feature with its type.
     * Point features come first.
     *
     * @param string $pointGeometry  FeatureCollection as JSON string or ''
     * @param string $countyGeometry FeatureCollection as JSON string or ''
     *
     * @return string FeatureCollection as JSON string, '' if there is no feature
     *
     * @throws JsonException
     */
    public function merge(string $pointGeometry, string $countyGeometry): string
    {
        $features = [
            ...$this->getTaggedFeatures($pointGeometry, self::TYPE_POINT),
            ...$this->getTaggedFeatures($countyGeometry, self::TYPE_COUNTY),
        ];

        return [] === $features ? '' : Json::encode($this->createFeatureCollection($features));
    }

    /**
     * Separates a polygon created by merge(). Untagged features (statements saved before) are points.
     * A value that is not a FeatureCollection is returned as point, untouched.
     *
     * @return array<string, string> keyed by the two field names the FE sends, '' for an empty part
     */
    public function split(string $polygon): array
    {
        $split = [self::POINT_FIELD => '', self::COUNTY_FIELD => ''];
        if ('' === $polygon) {
            return $split;
        }

        try {
            $features = Json::decodeToMatchingType($polygon)->features ?? null;
        } catch (JsonException) {
            $features = null;
        }

        if (!is_array($features)) {
            $split[self::POINT_FIELD] = $polygon;

            return $split;
        }

        $featuresByField = [self::POINT_FIELD => [], self::COUNTY_FIELD => []];
        foreach ($features as $feature) {
            $isCounty = self::TYPE_COUNTY === ($feature->properties->{self::TYPE_PROPERTY} ?? null);
            $featuresByField[$isCounty ? self::COUNTY_FIELD : self::POINT_FIELD][] = $feature;
        }

        foreach ($featuresByField as $field => $fieldFeatures) {
            $split[$field] = [] === $fieldFeatures ? '' : Json::encode($this->createFeatureCollection($fieldFeatures));
        }

        return $split;
    }

    /**
     * @return list<stdClass>
     *
     * @throws JsonException
     */
    private function getTaggedFeatures(string $featureCollection, string $type): array
    {
        if ('' === $featureCollection) {
            return [];
        }

        $taggedFeatures = [];
        foreach (Json::decodeToMatchingType($featureCollection)->features ?? [] as $feature) {
            // geoPHP (map image) cannot handle a feature without geometry
            if (null === ($feature->geometry ?? null)) {
                continue;
            }

            $feature->properties = (object) array_merge(
                (array) ($feature->properties ?? []),
                [self::TYPE_PROPERTY => $type]
            );
            $taggedFeatures[] = $feature;
        }

        return $taggedFeatures;
    }

    private function createFeatureCollection(array $features): array
    {
        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
