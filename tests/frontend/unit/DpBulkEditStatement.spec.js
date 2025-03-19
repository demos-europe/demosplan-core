/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { createStore } from 'vuex'
import DpBulkEditStatement from '@DpJs/components/statement/assessmentTable/DpBulkEditStatement'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import StatementStore from '@DpJs/store/statement/Statement'


describe('DpBulkEditStatement', () => {
  let store
  let wrapper

  beforeEach(() => {
    store = createStore({
      modules: {
        Statement: StatementStore
      }
    })

    wrapper = shallowMountWithGlobalMocks(DpBulkEditStatement, {
      propsData: {
        authorisedUsers: [],
        currentUserId: '1',
        procedureId: '1'
      },
      global: {
        plugins: [store]
      }
    })
  })

  it('should enable the newAssignee option when checked', async () => {
    wrapper.setData({ options: { newAssignee: { checked: true, value: '' } } })
    await wrapper.vm.$nextTick()
    expect(wrapper.find('#r_new_assignee').element.checked).toBe(true)
  })

  it('should disable the newAssignee option when unchecked', async () => {
    wrapper.setData({ options: { newAssignee: { checked: false, value: '' } } })
    await wrapper.vm.$nextTick()
    expect(wrapper.find('#r_new_assignee').element.checked).toBe(false)
  })

  it('should enable the recommendation option when checked', async () => {
    wrapper.setData({ options: { recommendation: { checked: true, value: '' } } })
    await wrapper.vm.$nextTick()
    expect(wrapper.find('#r_recommendation').element.checked).toBe(true)
  })

  it('should disable the recommendation option when unchecked', async () => {
    wrapper.setData({ options: { recommendation: { checked: false, value: '' } } })
    await wrapper.vm.$nextTick()
    expect(wrapper.find('#r_recommendation').element.checked).toBe(false)
  })
})
