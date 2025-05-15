import { MaybeRefOrGetter } from 'vue';
import { Interaction, VectorLayer, Map, Feature, Control, Geometry, Coordinate, Pixel, Size } from '../utils/ol';
import { MapLayer, MasterportalConfig, LayerConfig } from '../types';
import { InteractionMap, LayerDefinition, LayerStyleDefinition } from './useGeoLayer';
/** Layer definition Object with property for each layer and the property key as layer key  */
export type LayerDefinitions = Record<string, LayerDefinition>;
type NotEmpty<T> = keyof T extends never ? never : T;
export type Masterportal_Map = Map & {
    addLayer: (layer: string) => void;
};
export type MapOptions = {
    target: MaybeRefOrGetter<HTMLElement | null>;
    portalConfig: MasterportalConfig;
    layerConfig: LayerConfig;
    backgroundLayerId: string;
    startCenter: number[];
    layers: NotEmpty<LayerDefinitions>;
    mapMoveEnd?: (map: Map) => void;
    onHover?: (pixel: Pixel) => void;
};
/** load a config from url and type them as MasterportalConfig */
export declare const loadPortalConfig: (url: string) => Promise<MasterportalConfig>;
/** load a config from url and type them as LayerConfig */
export declare const loadLayerConfig: (url: string) => Promise<LayerConfig>;
/** create a masterportal map instance and render them in the given target */
export declare function useGeoMap(options: MapOptions): {
    getLayer: (key: string) => {
        source: import('ol/source/Vector').default<Feature<Geometry>>;
        layer: VectorLayer<import('ol/source/Vector').default<Feature<Geometry>>, Feature<Geometry>>;
        interactions: InteractionMap;
        getFeatures: (types?: string[]) => Feature<Geometry>[];
        addFeatures: (features: Feature<Geometry>[]) => void;
        removeFeature: (feature: Feature<Geometry>) => void;
        clear: () => void;
        strokeWidth: number;
        baseColor: keyof typeof import('../utils').ColorConfig;
        passive?: boolean;
        selectOn?: (feature: Feature, features: Feature<Geometry>[]) => void;
        selectOff?: (feature: Feature, features: Feature<Geometry>[]) => void;
        onDragBox?: (dragBox: Feature<Geometry>) => void;
        onLineDrawEnd?: (feature: Feature) => void;
        onPolygonDrawEnd?: (feature: Feature) => void;
        onCircleDrawEnd?: (feature: Feature) => void;
        onDrawStart?: () => void;
        onModifyEnd?: () => void;
        key: string;
    };
    getLayers: () => string[];
    addControls: <C extends Control>(...controls: C[]) => void;
    addOverlay: (element: MaybeRefOrGetter<HTMLElement | null>, coordinate: Coordinate) => void;
    focusLayerGeometries: (layer: string) => void;
    focusGeometries: (features: Feature[]) => void;
    getBBox: () => number[];
    getFeatureAtPixel: (pixel: Pixel) => [Feature<Geometry>, LayerStyleDefinition] | undefined;
    getSize: () => Size | undefined;
    getViewport: () => HTMLElement;
    getInteraction: <K extends keyof InteractionMap>(layerKey: string, interactionKey: K) => InteractionMap[K];
    enableInteractions: (...interactions: Interaction[]) => void;
    disableInteractions: (...interactions: Interaction[]) => void;
    removeOverlay: () => void;
    render: () => void;
    setInteractionsTo: (state: boolean) => void;
    switchBackgroundLayer: (layer: MapLayer) => string;
    switchDataLayers: (dataLayers: MapLayer[]) => void;
};
export {};
