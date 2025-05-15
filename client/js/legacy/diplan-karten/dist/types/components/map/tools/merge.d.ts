import { Feature } from '../utils/ol';
/**
 * The merge-algorithm relies on one assumption:
 * When mergeFeatures gets called, the other features are compared against
 * a specific geometry.
 * @param features
 * @returns
 */
export declare function mergeFeatures(features: Feature[], mergingFeature: Feature): Feature[];
