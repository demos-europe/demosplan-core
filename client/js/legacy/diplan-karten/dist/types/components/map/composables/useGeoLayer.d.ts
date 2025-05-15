import { VectorLayer, VectorSource, Draw, Select, DragBox, Modify, Snap, Feature, Geometry, Layer } from '../utils/ol';
import { ColorConfig } from '../utils';
export type LayerStyleDefinition = {
    /** base layer geometry stroke with */
    strokeWidth: number;
    /** base layer geometry color */
    baseColor: keyof typeof ColorConfig;
    /** define if the layer is a passive layer */
    passive?: boolean;
};
export type LayerDefinition = LayerStyleDefinition & {
    /**
     * Adds select interaction with a handler
     * @param feature the selected feature
     * @param features all selected features
     */
    selectOn?: (feature: Feature, features: Feature<Geometry>[]) => void;
    /**
     * Adds select interaction with a handler
     * @param feature the selected feature
     * @param features all selected features
     */
    selectOff?: (feature: Feature, features: Feature<Geometry>[]) => void;
    /**
     * Adds dragbox interaction with a interaction end handler
     * @param dragBox polygon feature of the dragbox
     */
    onDragBox?: (dragBox: Feature<Geometry>) => void;
    /**
     * Adds line interaction with a draw end handler
     * @param feature the drawed line feature
     */
    onLineDrawEnd?: (feature: Feature) => void;
    /**
     * Adds polygon interaction with a draw end handler
     * @param feature the drawed polygon feature
     */
    onPolygonDrawEnd?: (feature: Feature) => void;
    /**
     * Adds circle interaction with a draw end handler
     * @param feature the drawed circle feature
     */
    onCircleDrawEnd?: (feature: Feature) => void;
    /** if any draw is enabled (line, polygon, circle) adds a draw start handler */
    onDrawStart?: () => void;
    /** detects changes of the polygon and applies points to it if needed */
    onModifyEnd?: () => void;
};
export type InteractionMap = {
    select?: Select;
    dragBox?: DragBox;
    polygon?: Draw;
    circle?: Draw;
    line?: Draw;
    modify?: Modify;
    snap?: Snap;
};
export declare function getLayerGeometryStyle(layer: Layer): LayerStyleDefinition;
/** create an ol layer with defined interactions */
export declare function useGeoLayer(layerKey: string, layerDefinition: LayerDefinition): {
    source: VectorSource<Feature<Geometry>>;
    layer: VectorLayer<VectorSource<Feature<Geometry>>, Feature<Geometry>>;
    interactions: InteractionMap;
    getFeatures: (types?: string[]) => Feature<Geometry>[];
    addFeatures: (features: Feature<Geometry>[]) => void;
    removeFeature: (feature: Feature<Geometry>) => void;
    clear: () => void;
    /** base layer geometry stroke with */
    strokeWidth: number;
    /** base layer geometry color */
    baseColor: keyof typeof ColorConfig;
    /** define if the layer is a passive layer */
    passive?: boolean;
    /**
     * Adds select interaction with a handler
     * @param feature the selected feature
     * @param features all selected features
     */
    selectOn?: (feature: Feature, features: Feature<Geometry>[]) => void;
    /**
     * Adds select interaction with a handler
     * @param feature the selected feature
     * @param features all selected features
     */
    selectOff?: (feature: Feature, features: Feature<Geometry>[]) => void;
    /**
     * Adds dragbox interaction with a interaction end handler
     * @param dragBox polygon feature of the dragbox
     */
    onDragBox?: (dragBox: Feature<Geometry>) => void;
    /**
     * Adds line interaction with a draw end handler
     * @param feature the drawed line feature
     */
    onLineDrawEnd?: (feature: Feature) => void;
    /**
     * Adds polygon interaction with a draw end handler
     * @param feature the drawed polygon feature
     */
    onPolygonDrawEnd?: (feature: Feature) => void;
    /**
     * Adds circle interaction with a draw end handler
     * @param feature the drawed circle feature
     */
    onCircleDrawEnd?: (feature: Feature) => void;
    /** if any draw is enabled (line, polygon, circle) adds a draw start handler */
    onDrawStart?: () => void;
    /** detects changes of the polygon and applies points to it if needed */
    onModifyEnd?: () => void;
    key: string;
};
