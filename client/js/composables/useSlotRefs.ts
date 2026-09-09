/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import type { ComponentPublicInstance } from 'vue'

type SlotRefTarget = Element | ComponentPublicInstance | null

/**
 * Lets a component reach children that live in its default slot.
 *
 * A `ref` inside slot content registers on the slot owner - for Twig-rendered slots that is the
 * root app instance, not the component providing the slot. So components that reached their
 * markup via `$refs` under `inline-template` have the children hand themselves over instead:
 *
 *   <slot :set-ref="setRef" />                          // component template
 *   <statement-modal :ref="setRef('statementModal')">   // slot content in Twig
 *   this.slotRefs.statementModal                        // component
 *
 * @returns {Object} {
 *   setRef,     // (name) => ref setter to bind via :ref in the slot content
 *   slotRefs,   // registered children, keyed by the name passed to setRef
 * }
 */
export function useSlotRefs () {
  // Deliberately plain objects - component instances must not be made reactive.
  const slotRefs: Record<string, SlotRefTarget> = {}
  const setters: Record<string, (el: SlotRefTarget) => void> = {}

  /**
   * Cached per name, so that re-rendering the slot does not re-register the ref.
   */
  function setRef (name: string) {
    if (!setters[name]) {
      setters[name] = (el: SlotRefTarget) => {
        slotRefs[name] = el
      }
    }

    return setters[name]
  }

  return { setRef, slotRefs }
}
