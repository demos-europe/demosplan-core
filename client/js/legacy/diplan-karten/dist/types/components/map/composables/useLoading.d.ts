import { Ref, UnwrapNestedRefs } from 'vue';
export type LoadingOptions<D, I extends boolean = false> = Partial<{
    /**
     * Optional initial data for the data response
     * @default undefined
     */
    default: D;
    /**
     * immediate to the first call
     * @default false
     */
    immediate: I;
}>;
/**
 * Wrapps a promise function into a loading state.
 * Returns response as data prop and catch as error prop.
 * @param f Promise function
 * @param options optional options
 * @param args optional args for immediate request call
 * @returns reactive<{ call: f, isLoading: boolean, data: R | undefined, error: Error | undefined }>
 */
export declare function useLoading<R, A extends unknown[] = []>(f: (...args: A) => Promise<R>): UnwrapNestedRefs<{
    call: (...args: A) => Promise<R>;
    data: Ref<R | undefined>;
    error: Ref<Error | undefined>;
    isLoading: Ref<boolean>;
}>;
export declare function useLoading<R, A extends unknown[] = [], I extends boolean = false, D extends R | undefined = undefined>(f: (...args: A) => Promise<R>, options: LoadingOptions<D, I>): UnwrapNestedRefs<{
    call: (...args: A) => Promise<R>;
    data: Ref<R | D>;
    error: Ref<Error | undefined>;
    isLoading: Ref<boolean>;
}>;
export declare function useLoading<R, A extends unknown[] = []>(f: (...args: A) => Promise<R>, options: LoadingOptions<R, true>, args?: A): UnwrapNestedRefs<{
    call: (...args: A) => Promise<R>;
    data: Ref<R>;
    error: Ref<Error | undefined>;
    isLoading: Ref<boolean>;
}>;
