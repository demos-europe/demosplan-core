import { Feature } from '../utils/ol';
/**
 * Cut a list of features by an intersection line Feature.
 * @param features List of features
 * @param intersectFeature The intersect line polygon feature
 * @returns A new list of features
 */
export declare function cutFeatures(features: Feature[], intersectFeature: Feature): Feature[];
