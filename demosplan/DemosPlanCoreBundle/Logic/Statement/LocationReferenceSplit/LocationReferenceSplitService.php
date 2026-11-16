<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\LocationReferenceSplit;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * Diplankarte sends the location of a statement as two values: a point and a location reference.
 * The statement has only one polygon column, so both are joined into ONE FeatureCollection when saving
 * and separated again when a draft statement is loaded.
 */
class LocationReferenceSplitService
{
    /*
     * Names of the two request fields (FE -> BE). The same names are used to return
     * the two values when a draft statement is loaded (BE -> FE).
     */
    public const POINT_FIELD = 'r_location_geometry';
    public const LOCATION_REFERENCE_FIELD = 'r_location_reference_geometry';

    /*
     * Internal tag, not sent by the FE: every feature in the polygon column gets this property,
     * so the two values can be separated again.
     */
    private const TYPE_PROPERTY = 'locationType';
    private const TYPE_POINT = 'point';
    private const TYPE_LOCATION_REFERENCE = 'locationReference';

    // Permission that switches the point/location reference handling on
    private const PERMISSION = 'feature_diplankarte_split_location_reference';

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Polygon to store for the location 'point': the point geometry or, if the permission is set,
     * point and location reference joined into one FeatureCollection.
     *
     * Lenient on purpose, like the code before: if one of the values is not a valid FeatureCollection,
     * the point geometry is returned as it was sent and the problem is logged, instead of failing the request.
     */
    public function extractGeometry(array $data): string
    {
        $pointGeometry = (string) ($data[self::POINT_FIELD] ?? '');
        if (!$this->currentUser->hasPermission(self::PERMISSION)) {
            return $pointGeometry;
        }

        $locationReferenceGeometry = (string) ($data[self::LOCATION_REFERENCE_FIELD] ?? '');

        try {
            return $this->merge($pointGeometry, $locationReferenceGeometry);
        } catch (JsonException $exception) {
            $this->logger->error(
                'Location geometry from the FE could not be merged: it is not a valid GeoJSON FeatureCollection. '
                .'The point geometry was stored as sent and the location reference was dropped. '
                .'Please check what the FE sends in '.self::POINT_FIELD.' and '.self::LOCATION_REFERENCE_FIELD.'.',
                [
                    'exception'                 => $exception->getMessage(),
                    'pointGeometry'             => mb_substr($pointGeometry, 0, 200),
                    'locationReferenceGeometry' => mb_substr($locationReferenceGeometry, 0, 200),
                ]
            );

            return $pointGeometry;
        }
    }

    /**
     * Adds point and location reference of a loaded draft statement under the two field names the FE sends,
     * if the permission is set. 'polygon' stays untouched.
     */
    public function appendFields(?array $draftStatement): ?array
    {
        if (null === $draftStatement || !$this->currentUser->hasPermission(self::PERMISSION)) {
            return $draftStatement;
        }

        return array_merge($draftStatement, $this->split((string) ($draftStatement['polygon'] ?? '')));
    }

    /**
     * Joins point and location reference into ONE FeatureCollection and tags every feature with its type.
     * Point features come first.
     *
     * @param string $pointGeometry             FeatureCollection as JSON string or ''
     * @param string $locationReferenceGeometry FeatureCollection as JSON string or ''
     *
     * @return string FeatureCollection as JSON string, '' if there is no feature
     *
     * @throws JsonException
     */
    public function merge(string $pointGeometry, string $locationReferenceGeometry): string
    {
        $features = [
            ...$this->getTaggedFeatures($pointGeometry, self::TYPE_POINT),
            ...$this->getTaggedFeatures($locationReferenceGeometry, self::TYPE_LOCATION_REFERENCE),
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
        $split = [self::POINT_FIELD => '', self::LOCATION_REFERENCE_FIELD => ''];
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

        $featuresByField = [self::POINT_FIELD => [], self::LOCATION_REFERENCE_FIELD => []];
        foreach ($features as $feature) {
            $isLocationReference = self::TYPE_LOCATION_REFERENCE === ($feature->properties->{self::TYPE_PROPERTY} ?? null);
            $featuresByField[$isLocationReference ? self::LOCATION_REFERENCE_FIELD : self::POINT_FIELD][] = $feature;
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

        $decoded = Json::decodeToMatchingType($featureCollection);
        if (!$decoded instanceof stdClass || !is_array($decoded->features ?? null)) {
            throw new JsonException('The value is not a GeoJSON FeatureCollection');
        }

        $taggedFeatures = [];
        foreach ($decoded->features as $feature) {
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
