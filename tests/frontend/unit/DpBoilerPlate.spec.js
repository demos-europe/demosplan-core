/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DpBoilerPlate from '@DpJs/components/statement/DpBoilerPlate'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('DpBoilerPlate', () => {
  const boilerPlates = [
    {
      id: 'verified-boilerplate',
      title: 'Verified boilerplate',
      text: '<p>Some text</p>',
      verified: true,
    },
    {
      id: 'unverified-boilerplate',
      title: 'Unverified boilerplate',
      text: '<p>Other text</p>',
      verified: false,
    },
  ]

  const mountComponent = () => {
    return shallowMountWithGlobalMocks(DpBoilerPlate, {
      props: {
        boilerPlates,
      },
    })
  }

  describe('showVerifiedBadge', () => {
    it('returns true for a verified boilerplate when the permission is granted', () => {
      const wrapper = mountComponent()

      expect(wrapper.vm.showVerifiedBadge(boilerPlates[0])).toBe(true)
    })

    it('returns false for an unverified boilerplate', () => {
      const wrapper = mountComponent()

      expect(wrapper.vm.showVerifiedBadge(boilerPlates[1])).toBe(false)
    })

    it('returns false if the verified attribute is missing', () => {
      const wrapper = mountComponent()

      expect(wrapper.vm.showVerifiedBadge({ id: 'no-flag', title: 'No flag' })).toBe(false)
    })

    it('returns false for a verified boilerplate when the permission is missing', () => {
      const originalHasPermission = globalThis.hasPermission

      globalThis.hasPermission = jest.fn(() => false)

      try {
        const wrapper = mountComponent()

        expect(wrapper.vm.showVerifiedBadge(boilerPlates[0])).toBe(false)
        expect(globalThis.hasPermission).toHaveBeenCalledWith('feature_boilerplate_verified_marker')
      } finally {
        globalThis.hasPermission = originalHasPermission
      }
    })
  })

  describe('addToTextArea', () => {
    it('sets the preview value and emits the boilerplate text', () => {
      const wrapper = mountComponent()

      wrapper.vm.addToTextArea(boilerPlates[0])

      expect(wrapper.vm.previewValue).toBe('<p>Some text</p>')
      expect(wrapper.emitted('boilerplateText:added')).toEqual([['<p>Some text</p>']])
    })
  })

  describe('resetBoilerPlateMultiSelect', () => {
    it('clears the selection and the preview', () => {
      const wrapper = mountComponent()

      wrapper.vm.addToTextArea(boilerPlates[0])
      wrapper.vm.resetBoilerPlateMultiSelect()

      expect(wrapper.vm.selectedBoilerPlate).toBe('')
      expect(wrapper.vm.previewValue).toBe('')
    })
  })
})
