import { MapProfile } from '../types';
import { default as ButtonControl } from './ButtonControl';
import { default as ToggleControl } from './ToggleControl';
/** the states of the map  */
export declare enum ActionMode {
    NEUTRAL = "NEUTRAL",
    SELECTED = "SELECTED",
    DRAWING = "DRAWING",
    EDITING = "EDITING"
}
/** actions which have a button to press */
export declare enum Action {
    DRAW = "action-shape",
    LINE = "action-line",
    LASSO = "action-lasso",
    CIRCLE = "action-circle",
    MERGE = "action-merge",
    CUT = "action-cut",
    MODIFY = "action-modify",
    UNDO = "action-undo",
    DELETE = "action-delete",
    POINTS = "action-points",
    CADASTRAL = "action-cadastral",
    NEIGHBOURS = "action-neighbours",
    SUPERIORAREAS = "action-superiorareas",
    SELECT = "action-select",
    LAYERS = "action-layers",
    FULLSCREEN = "action-fullscreen",
    INFO = "action-info"
}
/** Map of avaible actions for a map mode */
export declare const AvailableActions: Record<ActionMode, Action[]>;
/** The modes in which activating an action leads to  */
export declare const ActionModes: Record<Action, ActionMode>;
export declare enum ButtonStatus {
    TOGGLE = "toggle",
    ACTION = "action"
}
export type MapControl = {
    label: string;
    className: Action;
    profiles: MapProfile[];
} & ((ConstructorParameters<typeof ButtonControl>[number] & {
    type: ButtonStatus.ACTION;
}) | (ConstructorParameters<typeof ToggleControl>[number] & {
    type: ButtonStatus.TOGGLE;
}));
export declare const controls: MapControl[];
