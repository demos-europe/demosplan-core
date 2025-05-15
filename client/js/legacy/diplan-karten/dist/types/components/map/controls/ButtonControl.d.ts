import { Control } from 'ol/control.js';
type Options = ConstructorParameters<typeof Control>[number] & {
    /** className to added */
    className: string;
    /** the icon to display */
    icon: string;
    /** on click handler */
    onClick?: () => void;
    /** @default true */
    visible?: boolean;
};
/** Simple Button Control with an icon label and click handler */
export default class ButtonControl extends Control {
    button_: HTMLElement;
    className_: string;
    handler_?: () => void;
    constructor(options: Options);
    private handleClick_;
}
export {};
