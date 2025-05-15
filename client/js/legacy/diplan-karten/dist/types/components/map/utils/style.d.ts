import { Style, FeatureLike } from './ol';
import { LayerStyleDefinition } from '../composables/useGeoLayer';
export type HexColor = `#${string}`;
export declare const ColorConfig: {
    readonly PRIMARY: "#3375d4";
    readonly SECONDARY: "#7069d1";
    readonly green: "#3bb300";
    readonly deeppink: "#ff38b9";
    readonly skyblue: "#38b9ff";
    readonly magenta: "#83049E";
    readonly CADASTRAL: "#2B2B2B";
    readonly SUPERIORAREA: "#ff5833";
};
/**
 * Get a style for a feature based on the layer configuration.
 * @param feature feature to get the style for
 * @param layer layer configuration where the feature is on
 * @returns a feature style
 */
export declare function createFeatureStyle(feature: FeatureLike, layer: LayerStyleDefinition): Style;
/**
 * Get a style for a feature while its selected, based on on the layer configuration.
 * @param feature feature to get the style for
 * @param layer layer configuration where the feature is on
 * @returns a feature color
 */
export declare function createFeatureSelectedStyle(feature: FeatureLike, layer: LayerStyleDefinition): Style;
/**
 * This shadeColor function accepts two parameters: `color` and `magnitude`.
 * The `color` parameter is the hex color to lighten or darken. It doesn’t need to be preceded by a hashtag, but it can be.
 * However, all hex values must be exactly six letters in length.
 * For example, `#ffffff` and `ffffff` are valid first parameters, but `#fff` is an invalid value for `color`.
 * The `magnitude` parameter is an integer value from -255 to 255 which represents the magnitude by which color should be lightened or darkened.
 * We can lighten a color by passing in a positive value for magnitude and we can darken by passing a a negative integer.
 *
 * @source https://natclark.com/tutorials/javascript-lighten-darken-hex-color/
 * @param color The six letters hex color parameter.
 * @param magnitude An integer by which color should be lightened or darkened.
 * @returns shaded hex color
 */
export declare function shadeColor(color: HexColor, magnitude: number): HexColor;
