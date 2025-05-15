import { PropType } from 'vue';
import { Feature } from '../utils/ol';
import { OverlayGeometryVariant } from '../types';
type Geometries = {
    feature: Feature;
    variant: OverlayGeometryVariant;
    attrs?: Record<string, unknown>;
}[];
declare const _default: import('vue').DefineComponent<import('vue').ExtractPropTypes<{
    geometries: {
        type: PropType<Geometries>;
        required: true;
    };
    names: {
        type: PropType<string[]>;
        required: true;
    };
}>, {}, {}, {}, {}, import('vue').ComponentOptionsMixin, import('vue').ComponentOptionsMixin, {} & {
    add: (geometry: Feature<import('ol/geom/Geometry').default>) => any;
    subtract: (geometry: Feature<import('ol/geom/Geometry').default>) => any;
    update: (geometry: Feature<import('ol/geom/Geometry').default>) => any;
}, string, import('vue').PublicProps, Readonly<import('vue').ExtractPropTypes<{
    geometries: {
        type: PropType<Geometries>;
        required: true;
    };
    names: {
        type: PropType<string[]>;
        required: true;
    };
}>> & Readonly<{
    onAdd?: ((geometry: Feature<import('ol/geom/Geometry').default>) => any) | undefined;
    onSubtract?: ((geometry: Feature<import('ol/geom/Geometry').default>) => any) | undefined;
    onUpdate?: ((geometry: Feature<import('ol/geom/Geometry').default>) => any) | undefined;
}>, {}, {}, {}, {}, string, import('vue').ComponentProvideOptions, true, {}, HTMLDivElement>;
export default _default;
