import { GeoJSONFeatureCollection, LayerConfig, MasterportalConfig } from '../types';
/** load a config from url and type them as MasterportalConfig */
export declare const loadPortalConfig: (url: string) => Promise<MasterportalConfig>;
/** load a config from url and type them as LayerConfig */
export declare const loadLayerConfig: (url: string) => Promise<LayerConfig>;
export declare function useGeoService(): GeoService;
export declare function getConfig(authToken: string): {
    headers: {
        Authorization: string;
    };
};
export declare class GeoService {
    static instance: GeoService;
    private api;
    getLayerConfig(authToken: string): Promise<LayerConfig>;
    getPortalConfig(authToken: string): Promise<MasterportalConfig>;
    validateGeometry(input?: any): boolean;
    /**
     * Requests the public API of the city of Hamburg to fetch all cadestral features that lie inside the viewport of the map.
     * @param bbox boundingBox of the viewport
     * @returns an array of features that touch the boundingbox in the CRS EPSG:25832
     */
    fetchCadastralFeatures(bbox: number[]): Promise<GeoJSONFeatureCollection>;
    fetchXPlanInfo(bbox: number[]): Promise<string>;
}
