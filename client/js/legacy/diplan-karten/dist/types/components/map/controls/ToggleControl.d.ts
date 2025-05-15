import { default as ButtonControl } from './ButtonControl';
import { MaybeElementRef } from '@vueuse/core';
type Options = ConstructorParameters<typeof ButtonControl>[number] & {
    /**
     * The button class names of ToggleControls which will be toggled off.
     * If no classes are given, no ToggleControl will be toggled off.
     */
    toggleClasses?: string[];
    /** the element to toggle */
    toggleElement?: MaybeElementRef<HTMLElement | null>;
};
export default class ToggleControl extends ButtonControl {
    toggleTarget_?: MaybeElementRef<HTMLElement | null>;
    toggleHandler_?: () => void;
    removeOutsideHandler_?: () => void;
    isActive_: boolean;
    toggleClasses_: string[];
    constructor(options: Options);
    /**
     * @param {MouseEvent} event The event to handle
     * @private
     */
    private handleToggleClick_;
    /** toggle the visability of an target element if given */
    private toggleElement;
    /** adds an outside click handler to the target element */
    private toggleClickOutside;
    /** disable all toggle control buttons with the toggle classes */
    private switchOffButtons;
}
export {};
