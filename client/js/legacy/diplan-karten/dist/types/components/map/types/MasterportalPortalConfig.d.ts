export type MasterportalConfig = {
    Portalconfig: Portalconfig;
    Themenconfig: Themenconfig;
};
export type Portalconfig = {
    portalTitle: PortalTitle;
    quickHelp: QuickHelp;
    tree: PortalconfigTree;
    searchBar: SearchBar;
    mapView: MapView;
    menu: Menu;
    controls: Controls;
    treeType: string;
};
export type Controls = {
    fullScreen: boolean;
    orientation3d: boolean;
    zoom: boolean;
    orientation: Orientation;
    totalView: boolean;
    attributions: boolean;
    overviewMap: OverviewMap;
    mousePosition: boolean;
    button3d: boolean;
    freeze: boolean;
    backForward: boolean;
};
export type Orientation = {
    zoomMode: string;
    poiDistances: number[];
};
export type OverviewMap = {
    layerId: string;
    isInitOpen: boolean;
};
export type MapView = {
    backgroundImage: string;
    startCenter: number[];
    options: Option[];
};
export type Option = {
    resolution: number;
    scale: number;
    zoomLevel: number;
};
export type Menu = {
    tree: MenuTree;
    tools: Tools;
    legend: Legend;
};
export type Legend = {
    name: string;
    icon: string;
    showCollapseAllButton: boolean;
};
export type Tools = {
    name: string;
    icon: string;
    children: Children;
};
export type Children = {
    gfi: Gfi;
    draw: Draw;
    measure: Measure;
    print: Print;
    saveSelection: Draw;
    scaleSwitcher: ScaleSwitcher;
};
export type Draw = {
    name: string;
    icon: string;
    isVisibleInMenu: boolean;
    simpleMap?: boolean;
};
export type Gfi = {
    name: string;
    icon: string;
    active: boolean;
    desktopType: string;
};
export type Measure = {
    name: string;
    icon: string;
};
export type Print = {
    name: string;
    icon: string;
    printServiceId: string;
    printAppId: string;
    filename: string;
    title: string;
    dpiForPdf: number;
};
export type ScaleSwitcher = {
    name: string;
    icon: string;
    isDisplayInFooter: boolean;
};
export type MenuTree = {
    name: string;
    icon: string;
    isInitOpen: boolean;
};
export type PortalTitle = {
    title: string;
    logo: string;
    link: string;
    toolTip: string;
};
export type QuickHelp = {
    configs: Configs;
};
export type Configs = {
    search: boolean;
    tree: boolean;
};
export type SearchBar = {
    gazetteer: Gazetteer;
    elasticSearch: ElasticSearch;
    placeholder: string;
};
export type ElasticSearch = {
    minChars: number;
    serviceId: string;
    type: string;
    payload: Payload;
    searchStringAttribute: string;
    responseEntryPath: string;
    triggerEvent: TriggerEvent;
    hitMap: HitMap;
    hitType: string;
    hitIcon: string;
};
export type HitMap = {
    name: string;
    id: string;
    source: string;
};
export type Payload = {
    id: string;
    params: Params;
};
export type Params = {
    query_string: string;
};
export type TriggerEvent = {
    channel: string;
    event: string;
};
export type Gazetteer = {
    minChars: number;
    serviceId: string;
    searchAddress: boolean;
    searchStreets: boolean;
    searchHouseNumbers: boolean;
    searchDistricts: boolean;
    searchParcels: boolean;
    searchStreetKey: boolean;
};
export type PortalconfigTree = {
    highlightedFeatures: HighlightedFeatures;
    showScaleTooltip: boolean;
};
export type HighlightedFeatures = {
    active: boolean;
};
export type Themenconfig = {
    Hintergrundkarten: Hintergrundkarten;
    Fachdaten: Fachdaten;
    Xplandaten?: Xplandaten;
};
export type Xplandaten = {
    Ordner: MapLayer[];
};
export type Fachdaten = {
    Ordner: MapLayer[];
};
export type MapLayer = {
    Titel: string;
    Layer: MapConfigLayer[];
    Ordinal?: number;
};
export type MapConfigLayer = {
    id: string;
};
export type Hintergrundkarten = {
    Layer: MapLayer[];
};
