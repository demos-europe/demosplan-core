/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { getCurrentInstance, onBeforeUnmount, onMounted } from 'vue'

/**
 * Access to the application-wide event bus from a `<script setup>` component.
 *
 * demosplan runs on @vue/compat in mode 2 and communicates across unrelated components
 * through `$root.$on` / `$root.$emit`, which the Options API exposes via `this`.
 * A `<script setup>` component has no `this`, so the root proxy has to be taken from
 * `getCurrentInstance()`. That call is an escape hatch and should not be spread across
 * components, hence this composable is the single place using it.
 *
 * Listeners registered through `onRootEvent` are removed again before unmount, so
 * components do not have to pair every `$on` with a matching `$off` themselves.
 *
 * Must be called during setup, like any other composable.
 *
 * @returns {Object} {
 *   emitRootEvent,  // (event, ...args) => void — emit on the bus
 *   onRootEvent,    // (event, handler) => void — listen until the component unmounts
 * }
 */
export function useRootEventBus () {
  const instance = getCurrentInstance()

  if (!instance) {
    throw new Error('useRootEventBus() must be called during setup.')
  }

  const root = instance.proxy.$root
  const registered = []

  const emitRootEvent = (event, ...args) => root.$emit(event, ...args)

  const onRootEvent = (event, handler) => {
    registered.push([event, handler])
  }

  /*
   * Registering in onMounted keeps the timing identical to the Options API components
   * that share these events, so listener order across the app does not change.
   */
  onMounted(() => {
    registered.forEach(([event, handler]) => root.$on(event, handler))
  })

  onBeforeUnmount(() => {
    registered.forEach(([event, handler]) => root.$off(event, handler))
  })

  return {
    emitRootEvent,
    onRootEvent,
  }
}
