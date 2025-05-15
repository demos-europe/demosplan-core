import { LayerConfig, Xplandaten } from '../types';
/**
 * This function takes the given xplan-wms service and splices it into the layer-
 * configuration and into the portal-configuration.
 * The given url will be deconstructed into its attributes and base-url.
 * The seperated parts will form new layer-configuration objects and will be added
 * to the needed configs.
 * To avoid errors and give a better user experience, the wms-service will be probed
 * for availability before adding to the config.
 * Tests have shown, that the xplan services might not be available at runtime.
 */
export declare function spliceXplanWMS(xplanWms: any): {
    layers: LayerConfig;
    config: Xplandaten;
} | undefined;
