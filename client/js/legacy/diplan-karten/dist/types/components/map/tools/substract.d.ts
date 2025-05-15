import { Feature } from '../utils';
/**
 * Checks if a feature overlap with some other feature.
 * If features overlap, the overlap will be cut out from the source feature and added as new feature.
 * Return a list of processed features as well as the  substractor feature added to the list.
 * @param features List of features to substract from
 * @param substractor Poylgon feature to substract
 * @returns Feature[]
 */
export declare function subtractFeature(features: Feature[], substractor: Feature, includeSubstractor?: boolean): Feature[];
