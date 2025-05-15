import { GeoJSONFeature } from '../types';
declare const _default: import('vue').DefineComponent<{}, {}, {}, {}, {}, import('vue').ComponentOptionsMixin, import('vue').ComponentOptionsMixin, {} & {
    select: (geometry: GeoJSONFeature<import('geojson').Geometry, import('geojson').GeoJsonProperties>) => any;
    "search:input": (payload: boolean) => any;
}, string, import('vue').PublicProps, Readonly<{}> & Readonly<{
    onSelect?: ((geometry: GeoJSONFeature<import('geojson').Geometry, import('geojson').GeoJsonProperties>) => any) | undefined;
    "onSearch:input"?: ((payload: boolean) => any) | undefined;
}>, {}, {}, {}, {}, string, import('vue').ComponentProvideOptions, true, {}, any>;
export default _default;
