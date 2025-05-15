import { FormKitNode, FormKitPlugin } from '@formkit/core';
import { FormKitMultiStepNode } from '@formkit/addons';
import { FormKitOptionsItem } from '@formkit/inputs';
export declare function getOptionValue<T>(option: FormKitOptionsItem<T>): T;
export declare function useReadonlyPlugin(): (node: FormKitMultiStepNode) => void;
/**
 * Creates a new loading state plugin.
 *
 * @returns A {@link @formkit/core#FormKitPlugin | FormKitPlugin}
 */
export declare function useLoadingStatePlugin(): FormKitPlugin;
type TraversalQuery = Parameters<FormKitNode["at"]>[0];
/**
 * Returns a plugin which hides options for the current input based on another input value.
 * @param query input name or node traversal query
 * @param formatter
 * @returns FormKitPlugin
 */
export declare function useParentValueFilterPlugin(query: string | TraversalQuery, formatter?: (v: string) => string): FormKitPlugin;
/**
 * Returns a plugin which shows the current input based on another input value.
 * @param query input name or node traversal query
 * @param value value the dependent input need to has for activation
 * @returns FormKitPlugin
 */
export declare function useFilterByPlugin<T>(query: string | TraversalQuery, value: T): (node: FormKitNode) => void;
/**
 * Returns a plugin which disables the current input based on another input value.
 * @param query input name or node traversal query
 * @param value value the dependent input need to has for activation
 * @returns FormKitPlugin
 */
export declare function useDisableByPlugin<T = undefined>(query: string | TraversalQuery, value?: T): (node: FormKitNode) => void;
/**
 * Returns a plugin which sets the required validation based on another input value.
 * @param query input name or node traversal query
 * @param value value the dependent input need to has for activation
 * @returns FormKitPlugin
 */
export declare function useRequiredByPlugin<T>(query: string | TraversalQuery, value: T): (node: FormKitNode) => void;
export declare function getNodeOrThrow(name: string): FormKitNode;
export {};
