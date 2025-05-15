import { GeoJSONFeature, GeoJSONFeatureCollection, GeoJSONLineString, GeoJSONPoint, GeoJSONPolygon } from '../types';
/*******************************************
 *   This file contains GeoJSON utilities
 *******************************************/
import * as turf from "@turf/turf";
/**
 * Get the subline of a line from first point to second point along the line orientation.
 * @param line The line to slice
 * @param point1 The starting point where the slice starts
 * @param point2 The stoppinf point where the slice ends
 * @returns The sliced line part
 */
export declare function lineSlice(line: Parameters<typeof turf.lineSegment>[0], point1: GeoJSONFeature<GeoJSONPoint> | GeoJSONPoint, point2: GeoJSONFeature<GeoJSONPoint> | GeoJSONPoint): GeoJSONFeature<GeoJSONLineString>;
/**
 * Order a list point on a line along the line.
 * @param line A LineString element
 * @param points A collection of points which have to be on the line element
 * @returns A ordered collection of points
 */
export declare function orderPointsOnLine(line: Parameters<typeof turf.lineSegment>[0], points: GeoJSONFeatureCollection<GeoJSONPoint>): GeoJSONFeatureCollection<GeoJSONPoint>;
/**
 * Check if a point is on a line, by consider a given tolerance.
 * @param line The line where the point should be on
 * @param point The point to check if it's on the line
 * @param epsilon Tolerance for floating-point comparison
 * @returns Determine if the given point is on the line
 */
export declare function pointOnLine(line: GeoJSONFeature<GeoJSONLineString> | GeoJSONLineString, point: turf.Coord, epsilon?: number): boolean;
/**
 * Follows the given orientation of a line an returns the next point in the point array.
 * The point in the point array must be on the line.
 * @param line line to walk along
 * @param points point array to check
 * @returns next point on the line
 */
export declare function getNextPointOnLine(line: Parameters<typeof turf.lineSegment>[0], points: GeoJSONFeatureCollection<GeoJSONPoint> | GeoJSONFeature<GeoJSONPoint>[]): GeoJSONFeature<GeoJSONPoint> | undefined;
/**
 * Check if a point lies inside a polygon.
 * This also consider points on the border edges.
 */
export declare function pointInPolygon(point: turf.Coord, polygon: GeoJSONFeature<GeoJSONPolygon> | GeoJSONPolygon): boolean;
/**
 * Move a closed line string ring to a specific beginning point.
 * This adds the point to the ring if not already there
 * @param line A closed line ring
 * @param point A point on the line
 * @returns The moved line ring
 */
export declare function turnRing(line: GeoJSONFeature<GeoJSONLineString> | GeoJSONLineString, point: GeoJSONFeature<GeoJSONPoint> | GeoJSONPoint): GeoJSONFeature<GeoJSONLineString>;
/**
 * Remove the target polygon from the source polygon by calculating the difference and clipping the target polygon from the source polygon.
 * @param sourcePoly Source polygon to remove the target from
 * @param targetPoly Target polygon to remove in the source
 * @returns The difference between both polygons.
 */
export declare function removePolygonFromPolygon(sourcePoly: GeoJSONFeature<GeoJSONPolygon>, targetPoly: GeoJSONFeature<GeoJSONPolygon>): GeoJSONFeature<GeoJSONPolygon>[];
/**
 * Determine whether the target polygon is completly enclosed by the source polygon.
 * In other words: Checks whether the target polygon lies completly inside the source polygon.
 * @param sourcePoly Source polygon to check if the target is inside it
 * @param targetPoly Target polygon to check if inside the source
 * @returns Boolean if is enclosed.
 */
export declare function enclosesPolygon(sourcePoly: GeoJSONFeature<GeoJSONPolygon> | GeoJSONPolygon, targetPoly: GeoJSONFeature<GeoJSONPolygon> | GeoJSONPolygon): boolean;
export declare function areEqual(a: any, b: any): boolean;
