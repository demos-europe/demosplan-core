export * from './MasterportalLayerConfig';
export * from './MasterportalPortalConfig';
export * from './searchGazetteer';
export * from './geojson';
export * from './neighbours';
export declare enum MasterportalLayer {
    SATELLITE = 0,
    STREETMAP = 1
}
export declare enum MapProfile {
    VORHABEN = "vorhaben",
    COCKPIT = "cockpit"
}
export declare enum ShapeType {
    POINT = "Point",
    LINESTRING = "LineString",
    POLYGON = "Polygon",
    MULTIPOLYGON = "MultiPolygon"
}
export declare enum Layer {
    Draw = "draw",
    Base = "base",
    Cadastral = "cadastral"
}
export declare enum OverlayGeometryVariant {
    CADASTRAL = "cadastral",
    NEIGHBOUR = "neighbour",
    READONLY = "read-only",
    STATUS = "status",
    SUPERIORAREAS = "superiorareas"
}
export declare enum ApplicationLayer {
    NEIGBHBOURS = "NEIGHBOURS",
    CONVERSIONS = "KONVERSIONSFLAECHE",
    COMMERCIALS = "GEWERBLICHER_STANDORT",
    CADASTRALS = "FLURSTUECKE"
}
