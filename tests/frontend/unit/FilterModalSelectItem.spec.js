/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createStore } from 'vuex'
import Filter from '@DpJs/store/statement/Filter'
import FilterModalSelectItem from '@DpJs/components/statement/assessmentTable/FilterModalSelectItem'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('FilterModalSelectItem', () => {
  let wrapper
  let store

  beforeEach(() => {
    store = createStore({
      modules: {
        Filter
      }
    })

    wrapper = shallowMountWithGlobalMocks(FilterModalSelectItem, {
      propsData: {
        appliedFilterOptions: [],
        filterGroup: {},
        filterItem: {
          id: 'testId',
          attributes: {
            label: 'Test Label',
            name: 'Test Name'
          }
        },
        hidden: false
      },
      global: {
        plugins: [store]
      }
    })
  })

  it('should update selected options when selectFilterOption is called', () => {
    const option = { label: 'Option 1', value: '1' }
    wrapper.vm.selectFilterOption(option)
    expect(wrapper.vm.selected).toContain(option)
  })

  it('should toggle sorting type when toggleSorting is called', () => {
    wrapper.vm.sortingType = 'count'
    wrapper.vm.toggleSorting('filterId')
    expect(wrapper.vm.sortingType).toBe('alphabetic')
  })
})
