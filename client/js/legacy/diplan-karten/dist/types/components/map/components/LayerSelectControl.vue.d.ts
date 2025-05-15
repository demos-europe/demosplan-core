import { PropType } from 'vue';
import { ApplicationLayer, MasterportalLayer, MapLayer } from '../types';
declare const _default: import('vue').DefineComponent<import('vue').ExtractPropTypes<{
    applicationData: {
        type: BooleanConstructor;
        default: boolean;
    };
    baseLayer: {
        type: PropType<MasterportalLayer>;
        required: true;
    };
    backgroundLayers: {
        type: PropType<MapLayer[]>;
        required: true;
    };
    dataLayers: {
        type: PropType<MapLayer[]>;
        required: true;
    };
    xplanLayers: {
        type: PropType<MapLayer[]>;
        default: () => never[];
    };
}>, {}, {}, {}, {}, import('vue').ComponentOptionsMixin, import('vue').ComponentOptionsMixin, {} & {
    toggle: (type: ApplicationLayer, isActive: boolean) => any;
    backgroundChange: (value: MapLayer) => any;
    dataChange: (value: MapLayer[]) => any;
}, string, import('vue').PublicProps, Readonly<import('vue').ExtractPropTypes<{
    applicationData: {
        type: BooleanConstructor;
        default: boolean;
    };
    baseLayer: {
        type: PropType<MasterportalLayer>;
        required: true;
    };
    backgroundLayers: {
        type: PropType<MapLayer[]>;
        required: true;
    };
    dataLayers: {
        type: PropType<MapLayer[]>;
        required: true;
    };
    xplanLayers: {
        type: PropType<MapLayer[]>;
        default: () => never[];
    };
}>> & Readonly<{
    onToggle?: ((type: ApplicationLayer, isActive: boolean) => any) | undefined;
    onBackgroundChange?: ((value: MapLayer) => any) | undefined;
    onDataChange?: ((value: MapLayer[]) => any) | undefined;
}>, {
    applicationData: boolean;
    xplanLayers: MapLayer[];
}, {}, {}, {}, string, import('vue').ComponentProvideOptions, true, {}, HTMLDivElement>;
export default _default;
