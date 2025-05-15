import { Feature } from './ol';
import { GeoJSONFeatureCollection, GeoJSONFeature, GeoJSONGeometry, GeoJSONMultiPolygon, GeoJSONPolygon } from '../types/geojson';
/**
 * Converts an OpenLayers feature into GeoJSON notation.
 * GeoJSON is used by turf.js
 * @param feature OpenLayers feature
 * @returns GeoJSON encoded Feature object
 */
export declare function featureToGeoJSON<T extends GeoJSONGeometry>(feature: Feature): GeoJSONFeature<T>;
/**
 * Converts an array of OpenLayers features into GeoJSON notation.
 * GeoJSON is used by turf.js
 * @param features An array of OpenLayers feature
 * @returns An array of GeoJSON encoded Feature object
 */
export declare function featuresToGeoJSON<T extends GeoJSONGeometry>(features: Feature[]): GeoJSONFeatureCollection<T>;
/**
 * Converts a GeoJSON notation into an OpenLayers feature
 * @param feature GeoJSON encoded Feature object
 * @returns OpenLayers feature
 */
export declare function geoJsonToFeature(feature: GeoJSONFeature | GeoJSONGeometry): Feature;
/**
 * Converts a list of GeoJSON notation objects into a list of an OpenLayers features
 * @param feature Array of GeoJSON encoded Feature objects
 * @returns An array of OpenLayers features
 */
export declare function geoJsonToFeatures(features: GeoJSONFeature[]): Feature[];
/**
 * Converts a GeoJSONGeometry object to a GeoJSONFeature object
 * @param geometry GeoJSON encoded Geometry object
 * @returns GeoJSON encoded Feature object
 */
export declare function geometryToFeature(geometry: GeoJSONGeometry): GeoJSONFeature<typeof geometry>;
/**
 * Converts a list of GeoJSONGeometry objects into a list of GeoJSONFeature objects
 * @param geometries Array of GeoJSON encoded Geometry object
 * @returns An array of GeoJSON encoded Feature object
 */
export declare function geometriesToFeatures(geometries: GeoJSONGeometry[]): GeoJSONFeature<(typeof geometries)[number]>[];
/**
 * Converts a list of GeoJSONFeature objects into a GeoJSONFeatureCollection object
 * @param features Array of GeoJSON encoded Feature object
 * @returns GeoJSON encoded FeatureCollection object
 */
export declare function featuresToCollection<T extends GeoJSONGeometry>(features: GeoJSONFeature<T>[]): GeoJSONFeatureCollection<T>;
/**
 * Converts an list of OpenLayers features into an OpenLayers MultiPolygon feature object
 * @param features Array of OpenLayers Feature objects
 * @returns OpenLayers MultiPolygon feature object
 */
export declare function featuresToMultiPolygon(features: Feature[]): Feature;
/**
 * Converts an OpenLayers feature into an OpenLayers MultiPolygon feature object
 * @param feature OpenLayers Feature objects
 * @returns OpenLayers MultiPolygon feature object
 */
export declare function featureToMultiPolygon(feature: Feature): Feature;
export declare function featureToGeoJsonPolygons(feature: Feature): GeoJSONFeature<GeoJSONPolygon>[];
/**
 * Converts a bounding box into a GeoJSONFeature feature object
 * @param bbox coordinates of a bounding box
 * @returns the bounding box as GeoJSONFeature feature object
 */
export declare function bboxToFeature(bbox: number[]): GeoJSONFeature;
/** checks the given feature contains a MultiPolygon. If not it converts it to a MultiPolygon. */
export declare function ensureMultiPolygon(feature: GeoJSONFeature<GeoJSONPolygon | GeoJSONMultiPolygon>): GeoJSONFeature<GeoJSONMultiPolygon>;
