/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import SegmentsExportModal from '@DpJs/components/procedure/SegmentsList/SegmentsExportModal'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('SegmentsExportModal', () => {
  let wrapper
  let toggleMock

  const findOpenButton = () => wrapper.findComponent('[data-cy="exportModal:open"]')
  const findRadio = key => wrapper.findComponent(`#${key}`)
  const findInlineNotification = () => wrapper.findComponent({ name: 'DpInlineNotification' })
  const findButtonRow = () => wrapper.findComponent({ name: 'DpButtonRow' })
  const findAppliedFiltersBlock = () => wrapper.find('[data-cy="exportModal:appliedFilters"]')

  beforeEach(() => {
    toggleMock = vi.fn()

    wrapper = shallowMountWithGlobalMocks(SegmentsExportModal, {
      props: {
        appliedFilters: [],
        isExportDisabled: false,
        searchTerm: '',
        segmentCount: 0,
      },
      global: {
        stubs: {
          'dp-modal': {
            template: '<div><slot name="header" /><slot /><slot name="footer" /></div>',
            methods: {
              toggle: toggleMock,
            },
          },
        },
      },
    })
  })

  describe('isExportDisabled', () => {
    it('enables the open button by default', () => {
      expect(findOpenButton().props('disabled')).toBe(false)
    })

    it('disables the open button when isExportDisabled is true', async () => {
      await wrapper.setProps({ isExportDisabled: true })

      expect(findOpenButton().props('disabled')).toBe(true)
    })
  })

  it('defaults to the xlsx export type', () => {
    expect(findRadio('xlsx_normal').props('checked')).toBe(true)
    expect(findRadio('csv_normal').props('checked')).toBe(false)
    expect(findInlineNotification().props('message')).toBe('export.xlsx.hint')
  })

  it('switches the active export type when a radio button is changed', async () => {
    findRadio('csv_normal').vm.$emit('change')
    await nextTick()

    expect(findRadio('csv_normal').props('checked')).toBe(true)
    expect(findRadio('xlsx_normal').props('checked')).toBe(false)
    expect(findInlineNotification().props('message')).toBe('export.csv.hint')
  })

  it('resets the export type to xlsx when the open button is clicked again', async () => {
    findRadio('csv_normal').vm.$emit('change')
    await nextTick()

    findOpenButton().vm.$emit('click')
    await nextTick()

    expect(findRadio('xlsx_normal').props('checked')).toBe(true)
  })

  it('emits "open" and toggles the modal when the open button is clicked', () => {
    findOpenButton().vm.$emit('click')

    expect(wrapper.emitted('open')).toHaveLength(1)
    expect(toggleMock).toHaveBeenCalledTimes(1)
  })

  it('hides the applied-filters summary when there are no filters and no search term', () => {
    expect(findAppliedFiltersBlock().exists()).toBe(false)
  })

  it('shows applied filters and the search term when present', async () => {
    await wrapper.setProps({
      appliedFilters: [{ label: 'Schlagworte', values: ['Positiv', 'Zustimmung'] }],
      searchTerm: 'Windpark',
    })

    const block = findAppliedFiltersBlock()

    expect(block.exists()).toBe(true)
    expect(block.text()).toContain('Schlagworte')
    expect(block.text()).toContain('Positiv, Zustimmung')
    expect(block.text()).toContain('Windpark')
  })

  it('shows the applied-filters summary for a search term alone', async () => {
    await wrapper.setProps({ searchTerm: 'Windpark' })

    expect(findAppliedFiltersBlock().exists()).toBe(true)
  })

  it('reflects segmentCount in the confirm button text', async () => {
    await wrapper.setProps({ segmentCount: 3 })

    expect(globalThis.Translator.trans).toHaveBeenCalledWith('export.segments.count', { count: 3 })
    expect(findButtonRow().props('primaryText')).toBe('export.segments.count')
  })

  it('emits "export" with the active type and closes the modal on confirm', () => {
    findRadio('csv_normal').vm.$emit('change')
    findButtonRow().vm.$emit('primary-action')

    expect(wrapper.emitted('export')).toEqual([[{ type: 'csv_normal' }]])
    expect(toggleMock).toHaveBeenCalledTimes(1)
  })

  it('closes the modal without emitting "export" when aborted', () => {
    findButtonRow().vm.$emit('secondary-action')

    expect(wrapper.emitted('export')).toBeUndefined()
    expect(toggleMock).toHaveBeenCalledTimes(1)
  })
})
