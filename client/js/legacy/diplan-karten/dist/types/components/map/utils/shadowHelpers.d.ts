import { RendererNode } from 'vue';
export declare function getCustomElement(): RendererNode | null;
export declare function getShadowRoot(): ShadowRoot | null;
export declare function querySelectorShadowRoot<T extends Element = Element>(selector: string): T | null;
export declare function querySelectorAllShadowRoot<T extends Element = Element>(selector: string): NodeListOf<T> | null;
