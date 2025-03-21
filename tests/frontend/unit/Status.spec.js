/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import Status from '@DpJs/components/statement/fragment/Status'

describe('Status', () => {
  let wrapper

  beforeEach(() => {
    wrapper = shallowMountWithGlobalMocks(Status, {
      props: {
        status: '',
        fragmentId: 'testId',
        archivedOrgaName: 'OrgaName',
        archivedDepartmentName: 'DepartmentName',
        voteAdvicePending: '',
        badge: false,
        tooltip: true,
        transNone: 'fragment.voteAdvice.status.none',
        transDone: 'fragment.voteAdvice.status.done'
      }
    })
  })

  it('should display question icon when status is empty', () => {
    expect(wrapper.find('.fa-question').exists()).toBe(true)
  })

  it('should display check icon when status is not empty', async () => {
    await wrapper.setProps({ status: 'done' })
    expect(wrapper.find('.fa-check').exists()).toBe(true)
  })

  it('should display hourglass icon when voteAdvicePending is not empty', async () => {
    await wrapper.setProps({ voteAdvicePending: 'pending' })
    expect(wrapper.find('.fa-hourglass-half').exists()).toBe(true)
  })

  it('should display tooltip when tooltip prop is true', () => {
    expect(wrapper.find('v-popover-stub').exists()).toBe(true)
  })

  it('should not display tooltip when tooltip prop is false', async () => {
    await wrapper.setProps({ tooltip: false })
    expect(wrapper.find('v-popover-stub').exists()).toBe(false)
  })
})
