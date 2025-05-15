import { PropType } from 'vue';
declare function __VLS_template(): {
    attrs: Partial<{}>;
    slots: {
        extra?(_: {}): any;
        default?(_: {}): any;
    };
    refs: {};
    rootEl: HTMLDivElement;
};
type __VLS_TemplateResult = ReturnType<typeof __VLS_template>;
declare const __VLS_component: import('vue').DefineComponent<import('vue').ExtractPropTypes<{
    position: {
        type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
        required: true;
    };
    invisible: {
        type: BooleanConstructor;
        default: boolean;
    };
}>, {}, {}, {}, {}, import('vue').ComponentOptionsMixin, import('vue').ComponentOptionsMixin, {}, string, import('vue').PublicProps, Readonly<import('vue').ExtractPropTypes<{
    position: {
        type: PropType<"left" | "top-left" | "top" | "top-right" | "right" | "bottom-left" | "bottom" | "bottom-right">;
        required: true;
    };
    invisible: {
        type: BooleanConstructor;
        default: boolean;
    };
}>> & Readonly<{}>, {
    invisible: boolean;
}, {}, {}, {}, string, import('vue').ComponentProvideOptions, true, {}, HTMLDivElement>;
declare const _default: __VLS_WithTemplateSlots<typeof __VLS_component, __VLS_TemplateResult["slots"]>;
export default _default;
type __VLS_WithTemplateSlots<T, S> = T & {
    new (): {
        $slots: S;
    };
};
