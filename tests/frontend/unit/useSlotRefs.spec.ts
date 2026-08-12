/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { useSlotRefs } from '@DpJs/composables/useSlotRefs'

describe('useSlotRefs', () => {
  it('registers a child under the name it was set up with', () => {
    const { setRef, slotRefs } = useSlotRefs()
    const child = { toggleModal: jest.fn() }

    setRef('statementModal')(child)

    expect(slotRefs.statementModal).toBe(child)
  })

  it('returns the same setter for a name, so re-rendering the slot does not re-register the ref', () => {
    const { setRef } = useSlotRefs()

    expect(setRef('statementModal')).toBe(setRef('statementModal'))
  })

  it('keeps refs of different names apart', () => {
    const { setRef, slotRefs } = useSlotRefs()
    const modal = {}
    const layerList = {}

    setRef('confirmModal')(modal)
    setRef('layerList')(layerList)

    expect(slotRefs.confirmModal).toBe(modal)
    expect(slotRefs.layerList).toBe(layerList)
  })

  it('clears the ref when the child unmounts', () => {
    const { setRef, slotRefs } = useSlotRefs()

    setRef('confirmModal')({})
    setRef('confirmModal')(null)

    expect(slotRefs.confirmModal).toBeNull()
  })

  it('does not share state between instances', () => {
    const first = useSlotRefs()
    const second = useSlotRefs()

    first.setRef('statementModal')({})

    expect(second.slotRefs.statementModal).toBeUndefined()
  })
})
