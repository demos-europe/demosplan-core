import { Feature, Coordinate } from './ol';
import { GeoJsonGeometryTypes } from 'geojson';
import { GeoJSONFeature, GeoJSONPolygon } from '../types';
/**
 * The function returns a filtered array based on the given filter.
 * @param features array of features that shall be filtered
 * @param filter is of type string and specifies what shall be found in the given feature array
 * @returns the subarray of the given feature array
 */
export declare function filterFeatures(features: Feature[], filter: GeoJsonGeometryTypes): Feature[];
/**
 * Get the center of the given geometry
 * @param feature to get the center from
 * @returns center coordinates
 */
export declare function getGeometryCenter(feature: Feature): Coordinate;
/**
 * Get the point masks of all features
 * @param features array to have their points extracted
 * @returns an array of points to
 */
export declare function getFeaturePoints(features: Feature[]): Feature[];
/**
 * Determine if one feature overlaps another feature.
 * Also checks for inverted enclosing, which result in no overlap (`false`).
 * @param feature the base feature to check the other for
 * @param overlapFeature the overlapping feature who might overlap the base feature
 * @returns Returns `true` if the base feature encloses te overlap feature or both feature partial overlapping, `false` the enclosing is inverted.
 */
export declare function featureOverlap(feature: Feature, overlapFeature: Feature): boolean;
/** merge all properties and the id from the source into the target feature */
export declare function mergeProperties(target_feature: Feature, source_feature: Feature): void;
export declare function convertGeoJsonPolygonsToFeatures(polygons: GeoJSONFeature<GeoJSONPolygon>[], originFeature: Feature): Feature[];
/** Determines if two values are equal */
export declare function areEqual(valueA: unknown, valueB: unknown): boolean;
export declare function convertPolygonsInFeatures(polygons: GeoJSONFeature<GeoJSONPolygon>[], originFeature: Feature): Feature[];
