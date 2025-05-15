import { Feature } from '../utils';
/**
 * Deletes a point from any feature in a list containing this point
 * @param features List of features
 * @param point Point to be deleted
 * @returns A list of features without the point
 */
export declare function removePoint(features: Feature[], point: Feature): Feature[];
