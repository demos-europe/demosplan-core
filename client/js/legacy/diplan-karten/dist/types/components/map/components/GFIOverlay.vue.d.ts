import { PropType } from 'vue';
import { Feature } from '../utils/ol';
declare const _default: import('vue').DefineComponent<import('vue').ExtractPropTypes<{
    geometry: {
        type: PropType<Feature>;
        default: undefined;
    };
}>, {}, {}, {}, {}, import('vue').ComponentOptionsMixin, import('vue').ComponentOptionsMixin, {} & {
    "close:gfi": (value: boolean) => any;
}, string, import('vue').PublicProps, Readonly<import('vue').ExtractPropTypes<{
    geometry: {
        type: PropType<Feature>;
        default: undefined;
    };
}>> & Readonly<{
    "onClose:gfi"?: ((value: boolean) => any) | undefined;
}>, {
    geometry: Feature<import('ol/geom/Geometry').default>;
}, {}, {}, {}, string, import('vue').ComponentProvideOptions, true, {}, HTMLDivElement>;
export default _default;
