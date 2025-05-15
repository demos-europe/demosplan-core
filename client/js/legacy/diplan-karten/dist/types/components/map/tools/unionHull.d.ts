import { Feature } from '../utils';
/**
 * Calculates the union hull of all features within the given capture feature.
 *
 * @param features List of features
 * @param capture Poylgon feature which marks the concave hull
 * @returns A feature that represents all features that are within the given feature
 */
export declare function unionHull(features: Feature[], capture: Feature): Feature | undefined;
