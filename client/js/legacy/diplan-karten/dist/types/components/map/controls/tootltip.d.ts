import { Control } from 'ol/control';
import { OTooltip } from '@oruga-ui/oruga-next';
type TooltipProps = InstanceType<typeof OTooltip>["$props"];
export type TooltipControlOptions<T extends typeof Control> = ConstructorParameters<T>[number] & {
    tooltip?: TooltipProps | TooltipProps[];
};
/** @source abstraction of https://medium.com/@thevirtuoid/extending-multiple-classes-in-javascript-2f4752574e65  */
export declare function tooltip(C: typeof Control): typeof C;
export {};
