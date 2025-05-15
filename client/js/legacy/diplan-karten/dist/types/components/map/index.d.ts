import { App } from 'vue';
import { default as MapFrame } from './MapFrame.ce.vue';
declare const MapPlugin: {
    install: (app: App) => void;
};
export { MapFrame, MapPlugin };
