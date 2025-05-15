import { nextTick, PropType } from 'vue';
import { MapProfile, MasterportalLayer, GeoJSONFeature, GeoJSONMultiPolygon, LayerConfig, MapLayer, MasterportalConfig } from './types';
declare const _default: import('vue').DefineComponent<import('vue').ExtractPropTypes<{
    enableDraw: {
        type: BooleanConstructor;
        default: boolean;
    };
    disableZoom: {
        type: BooleanConstructor;
        default: boolean;
    };
    baseData: {
        type: PropType<GeoJSONFeature[]>;
        default: () => never[];
    };
    geojson: {
        type: PropType<GeoJSONMultiPolygon[]>;
        default: undefined;
    };
    xplanWms: {
        type: StringConstructor;
        default: undefined;
    };
    isReduced: {
        type: BooleanConstructor;
        default: boolean;
    };
    profile: {
        type: PropType<MapProfile>;
        default: MapProfile;
    };
    baseLayer: {
        type: PropType<MasterportalLayer>;
        default: MasterportalLayer;
    };
    /** the name is necessary for a named download. Without a given name, the download option will default to the name "Belegenheit" */
    name: {
        type: StringConstructor;
        default: undefined;
    };
    portalConfig: {
        type: PropType<MasterportalConfig>;
        default: () => void;
    };
    layerConfig: {
        type: PropType<LayerConfig>;
        default: () => void;
    };
}>, {}, {}, {}, {}, import('vue').ComponentOptionsMixin, import('vue').ComponentOptionsMixin, {} & {
    "diplan-karte:background-layer-update": (value: MapLayer) => any;
    "diplan-karte:data-layer-update": (value: MapLayer[]) => any;
    "diplan-karte:fullscreen-update": (value: boolean) => any;
    "diplan-karte:geojson-update": (value: GeoJSONMultiPolygon[]) => any;
}, string, import('vue').PublicProps, Readonly<import('vue').ExtractPropTypes<{
    enableDraw: {
        type: BooleanConstructor;
        default: boolean;
    };
    disableZoom: {
        type: BooleanConstructor;
        default: boolean;
    };
    baseData: {
        type: PropType<GeoJSONFeature[]>;
        default: () => never[];
    };
    geojson: {
        type: PropType<GeoJSONMultiPolygon[]>;
        default: undefined;
    };
    xplanWms: {
        type: StringConstructor;
        default: undefined;
    };
    isReduced: {
        type: BooleanConstructor;
        default: boolean;
    };
    profile: {
        type: PropType<MapProfile>;
        default: MapProfile;
    };
    baseLayer: {
        type: PropType<MasterportalLayer>;
        default: MasterportalLayer;
    };
    /** the name is necessary for a named download. Without a given name, the download option will default to the name "Belegenheit" */
    name: {
        type: StringConstructor;
        default: undefined;
    };
    portalConfig: {
        type: PropType<MasterportalConfig>;
        default: () => void;
    };
    layerConfig: {
        type: PropType<LayerConfig>;
        default: () => void;
    };
}>> & Readonly<{
    "onDiplan-karte:background-layer-update"?: ((value: MapLayer) => any) | undefined;
    "onDiplan-karte:data-layer-update"?: ((value: MapLayer[]) => any) | undefined;
    "onDiplan-karte:fullscreen-update"?: ((value: boolean) => any) | undefined;
    "onDiplan-karte:geojson-update"?: ((value: GeoJSONMultiPolygon[]) => any) | undefined;
}>, {
    name: string;
    baseLayer: MasterportalLayer;
    enableDraw: boolean;
    disableZoom: boolean;
    baseData: GeoJSONFeature<import('geojson').Geometry, import('geojson').GeoJsonProperties>[];
    geojson: GeoJSONMultiPolygon[];
    xplanWms: string;
    isReduced: boolean;
    profile: MapProfile;
    portalConfig: MasterportalConfig;
    layerConfig: LayerConfig;
}, {}, {}, {}, string, import('vue').ComponentProvideOptions, true, {
    mapRootRef: HTMLDivElement;
    controlPanelLeftRef: ({
        $: import('vue').ComponentInternalInstance;
        $data: {};
        $props: Partial<{
            invisible: boolean;
        }> & Omit<{
            readonly position: "left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right";
            readonly invisible: boolean;
        } & import('vue').VNodeProps & import('vue').AllowedComponentProps & import('vue').ComponentCustomProps, "invisible">;
        $attrs: {
            [x: string]: unknown;
        };
        $refs: {
            [x: string]: unknown;
        };
        $slots: Readonly<{
            [name: string]: import('vue').Slot<any> | undefined;
        }>;
        $root: import('vue').ComponentPublicInstance | null;
        $parent: import('vue').ComponentPublicInstance | null;
        $host: Element | null;
        $emit: (event: string, ...args: any[]) => void;
        $el: HTMLDivElement;
        $options: import('vue').ComponentOptionsBase<Readonly<import('vue').ExtractPropTypes<{
            position: {
                type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
                required: true;
            };
            invisible: {
                type: BooleanConstructor;
                default: boolean;
            };
        }>> & Readonly<{}>, {}, {}, {}, {}, import('vue').ComponentOptionsMixin, import('vue').ComponentOptionsMixin, {}, string, {
            invisible: boolean;
        }, {}, string, {}, import('vue').GlobalComponents, import('vue').GlobalDirectives, string, import('vue').ComponentProvideOptions> & {
            beforeCreate?: (() => void) | (() => void)[];
            created?: (() => void) | (() => void)[];
            beforeMount?: (() => void) | (() => void)[];
            mounted?: (() => void) | (() => void)[];
            beforeUpdate?: (() => void) | (() => void)[];
            updated?: (() => void) | (() => void)[];
            activated?: (() => void) | (() => void)[];
            deactivated?: (() => void) | (() => void)[];
            beforeDestroy?: (() => void) | (() => void)[];
            beforeUnmount?: (() => void) | (() => void)[];
            destroyed?: (() => void) | (() => void)[];
            unmounted?: (() => void) | (() => void)[];
            renderTracked?: ((e: import('vue').DebuggerEvent) => void) | ((e: import('vue').DebuggerEvent) => void)[];
            renderTriggered?: ((e: import('vue').DebuggerEvent) => void) | ((e: import('vue').DebuggerEvent) => void)[];
            errorCaptured?: ((err: unknown, instance: import('vue').ComponentPublicInstance | null, info: string) => boolean | void) | ((err: unknown, instance: import('vue').ComponentPublicInstance | null, info: string) => boolean | void)[];
        };
        $forceUpdate: () => void;
        $nextTick: typeof nextTick;
        $watch<T extends string | ((...args: any) => any)>(source: T, cb: T extends (...args: any) => infer R ? (...args: [R, R, import('@vue/reactivity').OnCleanup]) => any : (...args: [any, any, import('@vue/reactivity').OnCleanup]) => any, options?: import('vue').WatchOptions): import('vue').WatchStopHandle;
    } & Readonly<{
        invisible: boolean;
    }> & Omit<Readonly<import('vue').ExtractPropTypes<{
        position: {
            type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
            required: true;
        };
        invisible: {
            type: BooleanConstructor;
            default: boolean;
        };
    }>> & Readonly<{}>, "invisible"> & import('vue').ShallowUnwrapRef<{}> & {} & import('vue').ComponentCustomProperties & {} & {
        $slots: {
            extra?(_: {}): any;
            default?(_: {}): any;
        };
    }) | null;
    controlPanelTopRightRef: ({
        $: import('vue').ComponentInternalInstance;
        $data: {};
        $props: Partial<{
            invisible: boolean;
        }> & Omit<{
            readonly position: "left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right";
            readonly invisible: boolean;
        } & import('vue').VNodeProps & import('vue').AllowedComponentProps & import('vue').ComponentCustomProps, "invisible">;
        $attrs: {
            [x: string]: unknown;
        };
        $refs: {
            [x: string]: unknown;
        };
        $slots: Readonly<{
            [name: string]: import('vue').Slot<any> | undefined;
        }>;
        $root: import('vue').ComponentPublicInstance | null;
        $parent: import('vue').ComponentPublicInstance | null;
        $host: Element | null;
        $emit: (event: string, ...args: any[]) => void;
        $el: HTMLDivElement;
        $options: import('vue').ComponentOptionsBase<Readonly<import('vue').ExtractPropTypes<{
            position: {
                type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
                required: true;
            };
            invisible: {
                type: BooleanConstructor;
                default: boolean;
            };
        }>> & Readonly<{}>, {}, {}, {}, {}, import('vue').ComponentOptionsMixin, import('vue').ComponentOptionsMixin, {}, string, {
            invisible: boolean;
        }, {}, string, {}, import('vue').GlobalComponents, import('vue').GlobalDirectives, string, import('vue').ComponentProvideOptions> & {
            beforeCreate?: (() => void) | (() => void)[];
            created?: (() => void) | (() => void)[];
            beforeMount?: (() => void) | (() => void)[];
            mounted?: (() => void) | (() => void)[];
            beforeUpdate?: (() => void) | (() => void)[];
            updated?: (() => void) | (() => void)[];
            activated?: (() => void) | (() => void)[];
            deactivated?: (() => void) | (() => void)[];
            beforeDestroy?: (() => void) | (() => void)[];
            beforeUnmount?: (() => void) | (() => void)[];
            destroyed?: (() => void) | (() => void)[];
            unmounted?: (() => void) | (() => void)[];
            renderTracked?: ((e: import('vue').DebuggerEvent) => void) | ((e: import('vue').DebuggerEvent) => void)[];
            renderTriggered?: ((e: import('vue').DebuggerEvent) => void) | ((e: import('vue').DebuggerEvent) => void)[];
            errorCaptured?: ((err: unknown, instance: import('vue').ComponentPublicInstance | null, info: string) => boolean | void) | ((err: unknown, instance: import('vue').ComponentPublicInstance | null, info: string) => boolean | void)[];
        };
        $forceUpdate: () => void;
        $nextTick: typeof nextTick;
        $watch<T extends string | ((...args: any) => any)>(source: T, cb: T extends (...args: any) => infer R ? (...args: [R, R, import('@vue/reactivity').OnCleanup]) => any : (...args: [any, any, import('@vue/reactivity').OnCleanup]) => any, options?: import('vue').WatchOptions): import('vue').WatchStopHandle;
    } & Readonly<{
        invisible: boolean;
    }> & Omit<Readonly<import('vue').ExtractPropTypes<{
        position: {
            type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
            required: true;
        };
        invisible: {
            type: BooleanConstructor;
            default: boolean;
        };
    }>> & Readonly<{}>, "invisible"> & import('vue').ShallowUnwrapRef<{}> & {} & import('vue').ComponentCustomProperties & {} & {
        $slots: {
            extra?(_: {}): any;
            default?(_: {}): any;
        };
    }) | null;
    controlPanelLayerRef: import('vue').CreateComponentPublicInstanceWithMixins<Readonly<import('vue').ExtractPropTypes<{
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
        onToggle?: ((type: import('./types').ApplicationLayer, isActive: boolean) => any) | undefined;
        onBackgroundChange?: ((value: MapLayer) => any) | undefined;
        onDataChange?: ((value: MapLayer[]) => any) | undefined;
    }>, {}, {}, {}, {}, import('vue').ComponentOptionsMixin, import('vue').ComponentOptionsMixin, {} & {
        toggle: (type: import('./types').ApplicationLayer, isActive: boolean) => any;
        backgroundChange: (value: MapLayer) => any;
        dataChange: (value: MapLayer[]) => any;
    }, import('vue').PublicProps, {
        applicationData: boolean;
        xplanLayers: MapLayer[];
    }, true, {}, {}, import('vue').GlobalComponents, import('vue').GlobalDirectives, string, {}, HTMLDivElement, import('vue').ComponentProvideOptions, {
        P: {};
        B: {};
        D: {};
        C: {};
        M: {};
        Defaults: {};
    }, Readonly<import('vue').ExtractPropTypes<{
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
        onToggle?: ((type: import('./types').ApplicationLayer, isActive: boolean) => any) | undefined;
        onBackgroundChange?: ((value: MapLayer) => any) | undefined;
        onDataChange?: ((value: MapLayer[]) => any) | undefined;
    }>, {}, {}, {}, {}, {
        applicationData: boolean;
        xplanLayers: MapLayer[];
    }> | null;
    controlPanelTop: ({
        $: import('vue').ComponentInternalInstance;
        $data: {};
        $props: Partial<{
            invisible: boolean;
        }> & Omit<{
            readonly position: "left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right";
            readonly invisible: boolean;
        } & import('vue').VNodeProps & import('vue').AllowedComponentProps & import('vue').ComponentCustomProps, "invisible">;
        $attrs: {
            [x: string]: unknown;
        };
        $refs: {
            [x: string]: unknown;
        };
        $slots: Readonly<{
            [name: string]: import('vue').Slot<any> | undefined;
        }>;
        $root: import('vue').ComponentPublicInstance | null;
        $parent: import('vue').ComponentPublicInstance | null;
        $host: Element | null;
        $emit: (event: string, ...args: any[]) => void;
        $el: HTMLDivElement;
        $options: import('vue').ComponentOptionsBase<Readonly<import('vue').ExtractPropTypes<{
            position: {
                type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
                required: true;
            };
            invisible: {
                type: BooleanConstructor;
                default: boolean;
            };
        }>> & Readonly<{}>, {}, {}, {}, {}, import('vue').ComponentOptionsMixin, import('vue').ComponentOptionsMixin, {}, string, {
            invisible: boolean;
        }, {}, string, {}, import('vue').GlobalComponents, import('vue').GlobalDirectives, string, import('vue').ComponentProvideOptions> & {
            beforeCreate?: (() => void) | (() => void)[];
            created?: (() => void) | (() => void)[];
            beforeMount?: (() => void) | (() => void)[];
            mounted?: (() => void) | (() => void)[];
            beforeUpdate?: (() => void) | (() => void)[];
            updated?: (() => void) | (() => void)[];
            activated?: (() => void) | (() => void)[];
            deactivated?: (() => void) | (() => void)[];
            beforeDestroy?: (() => void) | (() => void)[];
            beforeUnmount?: (() => void) | (() => void)[];
            destroyed?: (() => void) | (() => void)[];
            unmounted?: (() => void) | (() => void)[];
            renderTracked?: ((e: import('vue').DebuggerEvent) => void) | ((e: import('vue').DebuggerEvent) => void)[];
            renderTriggered?: ((e: import('vue').DebuggerEvent) => void) | ((e: import('vue').DebuggerEvent) => void)[];
            errorCaptured?: ((err: unknown, instance: import('vue').ComponentPublicInstance | null, info: string) => boolean | void) | ((err: unknown, instance: import('vue').ComponentPublicInstance | null, info: string) => boolean | void)[];
        };
        $forceUpdate: () => void;
        $nextTick: typeof nextTick;
        $watch<T extends string | ((...args: any) => any)>(source: T, cb: T extends (...args: any) => infer R ? (...args: [R, R, import('@vue/reactivity').OnCleanup]) => any : (...args: [any, any, import('@vue/reactivity').OnCleanup]) => any, options?: import('vue').WatchOptions): import('vue').WatchStopHandle;
    } & Readonly<{
        invisible: boolean;
    }> & Omit<Readonly<import('vue').ExtractPropTypes<{
        position: {
            type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
            required: true;
        };
        invisible: {
            type: BooleanConstructor;
            default: boolean;
        };
    }>> & Readonly<{}>, "invisible"> & import('vue').ShallowUnwrapRef<{}> & {} & import('vue').ComponentCustomProperties & {} & {
        $slots: {
            extra?(_: {}): any;
            default?(_: {}): any;
        };
    }) | null;
    gfiOverlayRef: HTMLDivElement;
    controlPanelTopLeftRef: ({
        $: import('vue').ComponentInternalInstance;
        $data: {};
        $props: Partial<{
            invisible: boolean;
        }> & Omit<{
            readonly position: "left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right";
            readonly invisible: boolean;
        } & import('vue').VNodeProps & import('vue').AllowedComponentProps & import('vue').ComponentCustomProps, "invisible">;
        $attrs: {
            [x: string]: unknown;
        };
        $refs: {
            [x: string]: unknown;
        };
        $slots: Readonly<{
            [name: string]: import('vue').Slot<any> | undefined;
        }>;
        $root: import('vue').ComponentPublicInstance | null;
        $parent: import('vue').ComponentPublicInstance | null;
        $host: Element | null;
        $emit: (event: string, ...args: any[]) => void;
        $el: HTMLDivElement;
        $options: import('vue').ComponentOptionsBase<Readonly<import('vue').ExtractPropTypes<{
            position: {
                type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
                required: true;
            };
            invisible: {
                type: BooleanConstructor;
                default: boolean;
            };
        }>> & Readonly<{}>, {}, {}, {}, {}, import('vue').ComponentOptionsMixin, import('vue').ComponentOptionsMixin, {}, string, {
            invisible: boolean;
        }, {}, string, {}, import('vue').GlobalComponents, import('vue').GlobalDirectives, string, import('vue').ComponentProvideOptions> & {
            beforeCreate?: (() => void) | (() => void)[];
            created?: (() => void) | (() => void)[];
            beforeMount?: (() => void) | (() => void)[];
            mounted?: (() => void) | (() => void)[];
            beforeUpdate?: (() => void) | (() => void)[];
            updated?: (() => void) | (() => void)[];
            activated?: (() => void) | (() => void)[];
            deactivated?: (() => void) | (() => void)[];
            beforeDestroy?: (() => void) | (() => void)[];
            beforeUnmount?: (() => void) | (() => void)[];
            destroyed?: (() => void) | (() => void)[];
            unmounted?: (() => void) | (() => void)[];
            renderTracked?: ((e: import('vue').DebuggerEvent) => void) | ((e: import('vue').DebuggerEvent) => void)[];
            renderTriggered?: ((e: import('vue').DebuggerEvent) => void) | ((e: import('vue').DebuggerEvent) => void)[];
            errorCaptured?: ((err: unknown, instance: import('vue').ComponentPublicInstance | null, info: string) => boolean | void) | ((err: unknown, instance: import('vue').ComponentPublicInstance | null, info: string) => boolean | void)[];
        };
        $forceUpdate: () => void;
        $nextTick: typeof nextTick;
        $watch<T extends string | ((...args: any) => any)>(source: T, cb: T extends (...args: any) => infer R ? (...args: [R, R, import('@vue/reactivity').OnCleanup]) => any : (...args: [any, any, import('@vue/reactivity').OnCleanup]) => any, options?: import('vue').WatchOptions): import('vue').WatchStopHandle;
    } & Readonly<{
        invisible: boolean;
    }> & Omit<Readonly<import('vue').ExtractPropTypes<{
        position: {
            type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
            required: true;
        };
        invisible: {
            type: BooleanConstructor;
            default: boolean;
        };
    }>> & Readonly<{}>, "invisible"> & import('vue').ShallowUnwrapRef<{}> & {} & import('vue').ComponentCustomProperties & {} & {
        $slots: {
            extra?(_: {}): any;
            default?(_: {}): any;
        };
    }) | null;
    controlPanelRightRef: ({
        $: import('vue').ComponentInternalInstance;
        $data: {};
        $props: Partial<{
            invisible: boolean;
        }> & Omit<{
            readonly position: "left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right";
            readonly invisible: boolean;
        } & import('vue').VNodeProps & import('vue').AllowedComponentProps & import('vue').ComponentCustomProps, "invisible">;
        $attrs: {
            [x: string]: unknown;
        };
        $refs: {
            [x: string]: unknown;
        };
        $slots: Readonly<{
            [name: string]: import('vue').Slot<any> | undefined;
        }>;
        $root: import('vue').ComponentPublicInstance | null;
        $parent: import('vue').ComponentPublicInstance | null;
        $host: Element | null;
        $emit: (event: string, ...args: any[]) => void;
        $el: HTMLDivElement;
        $options: import('vue').ComponentOptionsBase<Readonly<import('vue').ExtractPropTypes<{
            position: {
                type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
                required: true;
            };
            invisible: {
                type: BooleanConstructor;
                default: boolean;
            };
        }>> & Readonly<{}>, {}, {}, {}, {}, import('vue').ComponentOptionsMixin, import('vue').ComponentOptionsMixin, {}, string, {
            invisible: boolean;
        }, {}, string, {}, import('vue').GlobalComponents, import('vue').GlobalDirectives, string, import('vue').ComponentProvideOptions> & {
            beforeCreate?: (() => void) | (() => void)[];
            created?: (() => void) | (() => void)[];
            beforeMount?: (() => void) | (() => void)[];
            mounted?: (() => void) | (() => void)[];
            beforeUpdate?: (() => void) | (() => void)[];
            updated?: (() => void) | (() => void)[];
            activated?: (() => void) | (() => void)[];
            deactivated?: (() => void) | (() => void)[];
            beforeDestroy?: (() => void) | (() => void)[];
            beforeUnmount?: (() => void) | (() => void)[];
            destroyed?: (() => void) | (() => void)[];
            unmounted?: (() => void) | (() => void)[];
            renderTracked?: ((e: import('vue').DebuggerEvent) => void) | ((e: import('vue').DebuggerEvent) => void)[];
            renderTriggered?: ((e: import('vue').DebuggerEvent) => void) | ((e: import('vue').DebuggerEvent) => void)[];
            errorCaptured?: ((err: unknown, instance: import('vue').ComponentPublicInstance | null, info: string) => boolean | void) | ((err: unknown, instance: import('vue').ComponentPublicInstance | null, info: string) => boolean | void)[];
        };
        $forceUpdate: () => void;
        $nextTick: typeof nextTick;
        $watch<T extends string | ((...args: any) => any)>(source: T, cb: T extends (...args: any) => infer R ? (...args: [R, R, import('@vue/reactivity').OnCleanup]) => any : (...args: [any, any, import('@vue/reactivity').OnCleanup]) => any, options?: import('vue').WatchOptions): import('vue').WatchStopHandle;
    } & Readonly<{
        invisible: boolean;
    }> & Omit<Readonly<import('vue').ExtractPropTypes<{
        position: {
            type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
            required: true;
        };
        invisible: {
            type: BooleanConstructor;
            default: boolean;
        };
    }>> & Readonly<{}>, "invisible"> & import('vue').ShallowUnwrapRef<{}> & {} & import('vue').ComponentCustomProperties & {} & {
        $slots: {
            extra?(_: {}): any;
            default?(_: {}): any;
        };
    }) | null;
    controlPanelBottomLeftRef: ({
        $: import('vue').ComponentInternalInstance;
        $data: {};
        $props: Partial<{
            invisible: boolean;
        }> & Omit<{
            readonly position: "left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right";
            readonly invisible: boolean;
        } & import('vue').VNodeProps & import('vue').AllowedComponentProps & import('vue').ComponentCustomProps, "invisible">;
        $attrs: {
            [x: string]: unknown;
        };
        $refs: {
            [x: string]: unknown;
        };
        $slots: Readonly<{
            [name: string]: import('vue').Slot<any> | undefined;
        }>;
        $root: import('vue').ComponentPublicInstance | null;
        $parent: import('vue').ComponentPublicInstance | null;
        $host: Element | null;
        $emit: (event: string, ...args: any[]) => void;
        $el: HTMLDivElement;
        $options: import('vue').ComponentOptionsBase<Readonly<import('vue').ExtractPropTypes<{
            position: {
                type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
                required: true;
            };
            invisible: {
                type: BooleanConstructor;
                default: boolean;
            };
        }>> & Readonly<{}>, {}, {}, {}, {}, import('vue').ComponentOptionsMixin, import('vue').ComponentOptionsMixin, {}, string, {
            invisible: boolean;
        }, {}, string, {}, import('vue').GlobalComponents, import('vue').GlobalDirectives, string, import('vue').ComponentProvideOptions> & {
            beforeCreate?: (() => void) | (() => void)[];
            created?: (() => void) | (() => void)[];
            beforeMount?: (() => void) | (() => void)[];
            mounted?: (() => void) | (() => void)[];
            beforeUpdate?: (() => void) | (() => void)[];
            updated?: (() => void) | (() => void)[];
            activated?: (() => void) | (() => void)[];
            deactivated?: (() => void) | (() => void)[];
            beforeDestroy?: (() => void) | (() => void)[];
            beforeUnmount?: (() => void) | (() => void)[];
            destroyed?: (() => void) | (() => void)[];
            unmounted?: (() => void) | (() => void)[];
            renderTracked?: ((e: import('vue').DebuggerEvent) => void) | ((e: import('vue').DebuggerEvent) => void)[];
            renderTriggered?: ((e: import('vue').DebuggerEvent) => void) | ((e: import('vue').DebuggerEvent) => void)[];
            errorCaptured?: ((err: unknown, instance: import('vue').ComponentPublicInstance | null, info: string) => boolean | void) | ((err: unknown, instance: import('vue').ComponentPublicInstance | null, info: string) => boolean | void)[];
        };
        $forceUpdate: () => void;
        $nextTick: typeof nextTick;
        $watch<T extends string | ((...args: any) => any)>(source: T, cb: T extends (...args: any) => infer R ? (...args: [R, R, import('@vue/reactivity').OnCleanup]) => any : (...args: [any, any, import('@vue/reactivity').OnCleanup]) => any, options?: import('vue').WatchOptions): import('vue').WatchStopHandle;
    } & Readonly<{
        invisible: boolean;
    }> & Omit<Readonly<import('vue').ExtractPropTypes<{
        position: {
            type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
            required: true;
        };
        invisible: {
            type: BooleanConstructor;
            default: boolean;
        };
    }>> & Readonly<{}>, "invisible"> & import('vue').ShallowUnwrapRef<{}> & {} & import('vue').ComponentCustomProperties & {} & {
        $slots: {
            extra?(_: {}): any;
            default?(_: {}): any;
        };
    }) | null;
    controlPanelLayerInfoRef: import('vue').CreateComponentPublicInstanceWithMixins<Readonly<import('vue').ExtractPropTypes<{
        activeLayerIds: {
            type: PropType<string[]>;
            required: true;
        };
        themenConfig: {
            type: PropType<import('./types').Themenconfig>;
            required: true;
        };
    }>> & Readonly<{}>, {}, {}, {}, {}, import('vue').ComponentOptionsMixin, import('vue').ComponentOptionsMixin, {}, import('vue').PublicProps, {}, true, {}, {}, import('vue').GlobalComponents, import('vue').GlobalDirectives, string, {}, HTMLDivElement, import('vue').ComponentProvideOptions, {
        P: {};
        B: {};
        D: {};
        C: {};
        M: {};
        Defaults: {};
    }, Readonly<import('vue').ExtractPropTypes<{
        activeLayerIds: {
            type: PropType<string[]>;
            required: true;
        };
        themenConfig: {
            type: PropType<import('./types').Themenconfig>;
            required: true;
        };
    }>> & Readonly<{}>, {}, {}, {}, {}, {}> | null;
    controlPanelBottomRightRef: ({
        $: import('vue').ComponentInternalInstance;
        $data: {};
        $props: Partial<{
            invisible: boolean;
        }> & Omit<{
            readonly position: "left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right";
            readonly invisible: boolean;
        } & import('vue').VNodeProps & import('vue').AllowedComponentProps & import('vue').ComponentCustomProps, "invisible">;
        $attrs: {
            [x: string]: unknown;
        };
        $refs: {
            [x: string]: unknown;
        };
        $slots: Readonly<{
            [name: string]: import('vue').Slot<any> | undefined;
        }>;
        $root: import('vue').ComponentPublicInstance | null;
        $parent: import('vue').ComponentPublicInstance | null;
        $host: Element | null;
        $emit: (event: string, ...args: any[]) => void;
        $el: HTMLDivElement;
        $options: import('vue').ComponentOptionsBase<Readonly<import('vue').ExtractPropTypes<{
            position: {
                type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
                required: true;
            };
            invisible: {
                type: BooleanConstructor;
                default: boolean;
            };
        }>> & Readonly<{}>, {}, {}, {}, {}, import('vue').ComponentOptionsMixin, import('vue').ComponentOptionsMixin, {}, string, {
            invisible: boolean;
        }, {}, string, {}, import('vue').GlobalComponents, import('vue').GlobalDirectives, string, import('vue').ComponentProvideOptions> & {
            beforeCreate?: (() => void) | (() => void)[];
            created?: (() => void) | (() => void)[];
            beforeMount?: (() => void) | (() => void)[];
            mounted?: (() => void) | (() => void)[];
            beforeUpdate?: (() => void) | (() => void)[];
            updated?: (() => void) | (() => void)[];
            activated?: (() => void) | (() => void)[];
            deactivated?: (() => void) | (() => void)[];
            beforeDestroy?: (() => void) | (() => void)[];
            beforeUnmount?: (() => void) | (() => void)[];
            destroyed?: (() => void) | (() => void)[];
            unmounted?: (() => void) | (() => void)[];
            renderTracked?: ((e: import('vue').DebuggerEvent) => void) | ((e: import('vue').DebuggerEvent) => void)[];
            renderTriggered?: ((e: import('vue').DebuggerEvent) => void) | ((e: import('vue').DebuggerEvent) => void)[];
            errorCaptured?: ((err: unknown, instance: import('vue').ComponentPublicInstance | null, info: string) => boolean | void) | ((err: unknown, instance: import('vue').ComponentPublicInstance | null, info: string) => boolean | void)[];
        };
        $forceUpdate: () => void;
        $nextTick: typeof nextTick;
        $watch<T extends string | ((...args: any) => any)>(source: T, cb: T extends (...args: any) => infer R ? (...args: [R, R, import('@vue/reactivity').OnCleanup]) => any : (...args: [any, any, import('@vue/reactivity').OnCleanup]) => any, options?: import('vue').WatchOptions): import('vue').WatchStopHandle;
    } & Readonly<{
        invisible: boolean;
    }> & Omit<Readonly<import('vue').ExtractPropTypes<{
        position: {
            type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
            required: true;
        };
        invisible: {
            type: BooleanConstructor;
            default: boolean;
        };
    }>> & Readonly<{}>, "invisible"> & import('vue').ShallowUnwrapRef<{}> & {} & import('vue').ComponentCustomProperties & {} & {
        $slots: {
            extra?(_: {}): any;
            default?(_: {}): any;
        };
    }) | null;
    mapOverlayRef: HTMLDivElement;
}, HTMLDivElement>;
export default _default;
