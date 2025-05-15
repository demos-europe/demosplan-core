import { OptionsPropItem } from '@oruga-ui/oruga-next';
import { GeoJSONFeature } from './geojson';
/**
 * this type is derived from the answer of the search endpoint of the masterportal:
 * @source https://bitbucket.org/geowerkstatt-hamburg/masterportalapi/src/master/src/searchAddress/search.js
 */
export type SearchResponse = {
    geometry: GeoJSONFeature | undefined;
    name: string;
    type: "street" | "houseNumbersForStreet";
    [key: string]: unknown;
};
export type SearchOption = OptionsPropItem<GeoJSONFeature | undefined>;
