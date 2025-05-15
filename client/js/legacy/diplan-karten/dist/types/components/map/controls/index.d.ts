import { FullScreen, MousePosition, ScaleLine, Zoom } from 'ol/control';
import { TooltipControlOptions } from './tootltip';
import { default as ButtonControl } from './ButtonControl';
import { default as ToggleControl } from './ToggleControl';
export * from './controls';
declare const FullScreenControl_base: typeof import('ol/control').Control;
export declare class FullScreenControl extends FullScreenControl_base {
    constructor(options: TooltipControlOptions<typeof FullScreen>);
}
declare const ZoomControl_base: typeof import('ol/control').Control;
export declare class ZoomControl extends ZoomControl_base {
    constructor(options: TooltipControlOptions<typeof Zoom>);
}
declare const ScaleControl_base: typeof import('ol/control').Control;
export declare class ScaleControl extends ScaleControl_base {
    constructor(options: TooltipControlOptions<typeof ScaleLine>);
}
declare const MousePositionControl_base: typeof import('ol/control').Control;
export declare class MousePositionControl extends MousePositionControl_base {
    constructor(options: TooltipControlOptions<typeof MousePosition>);
}
declare const ActionButtonControl_base: typeof import('ol/control').Control;
export declare class ActionButtonControl extends ActionButtonControl_base {
    constructor(options: TooltipControlOptions<typeof ButtonControl>);
}
declare const ToggleButtonControl_base: typeof import('ol/control').Control;
export declare class ToggleButtonControl extends ToggleButtonControl_base {
    constructor(options: TooltipControlOptions<typeof ToggleControl>);
}
