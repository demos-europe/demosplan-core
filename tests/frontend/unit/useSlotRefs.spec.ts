/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import type { ComponentPublicInstance } from 'vue'
import { useSlotRefs } from '@DpJs/composables/useSlotRefs'
import { vi } from 'vitest'

// Test doubles for the component instances registered via :ref in slot content
const asComponent = (stub: object) => stub as unknown as ComponentPublicInstance

describe('useSlotRefs', () => {
  it('registers a child under the name it was set up with', () => {
    const { setRef, slotRefs } = useSlotRefs()
    const child = asComponent({ toggleModal: vi.fn() })

    setRef('statementModal')(child)

    expect(slotRefs.statementModal).toBe(child)
  })

  it('returns the same setter for a name, so re-rendering the slot does not re-register the ref', () => {
    const { setRef } = useSlotRefs()

    expect(setRef('statementModal')).toBe(setRef('statementModal'))
  })

  it('keeps refs of different names apart', () => {
    const { setRef, slotRefs } = useSlotRefs()
    const modal = asComponent({})
    const layerList = asComponent({})

    setRef('confirmModal')(modal)
    setRef('layerList')(layerList)

    expect(slotRefs.confirmModal).toBe(modal)
    expect(slotRefs.layerList).toBe(layerList)
  })

  it('clears the ref when the child unmounts', () => {
    const { setRef, slotRefs } = useSlotRefs()

    setRef('confirmModal')(asComponent({}))
    setRef('confirmModal')(null)

    expect(slotRefs.confirmModal).toBeNull()
  })

  it('does not share state between instances', () => {
    const first = useSlotRefs()
    const second = useSlotRefs()

    first.setRef('statementModal')(asComponent({}))

    expect(second.slotRefs.statementModal).toBeUndefined()
  })
})
