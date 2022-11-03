/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createLocalVue } from '@vue/test-utils'
import DpClaim from '@DpJs/components/statement/DpClaim'
import shallowMountWithGlobalMocks from '@DemosPlanCoreBundle/VueConfigLocal'

describe('DpClaim', () => {
  it('should be an object', () => {
    expect(typeof DpClaim).toBe('object')
  })

  it('should be named DpClaim', () => {
    expect(DpClaim.name).toBe('DpClaim')
  })

  it('should return the correct icons and text for fragments-claim-states', async () => {
    const localVue = createLocalVue()

    const wrapper = shallowMountWithGlobalMocks(DpClaim, {
      propsData: {
        assignedOrganisation: 'Orga des Assingnee',
        assignedName: 'Aktueller Benutzer',
        assignedId: '',
        currentUserId: '1',
        currentUserName: 'Aktueller Benutzer',
        lastClaimedUserId: '',
        entityType: 'fragment'
      },
      localVue
    })

    // Should show open lock if assignedId is not set - lastClaimed doesn't matter.
    expect(wrapper.vm.status).toEqual({ icon: 'fa-unlock', text: 'statement.fragment.assignment.unassigned' })

    // Should show claimed if assignedId equals UserId (fragments) - lastClaimed doesn't matter.
    await wrapper.setProps({
      currentUserId: '1',
      assignedId: '1'
    })
    expect(wrapper.vm.status).toEqual({ icon: 'fa-user', text: 'statement.fragment.assignment.assigned.self' })

    // Should be locked if userId does not match assigneeId and lastClaimed is not the user (fragments).
    await wrapper.setProps({
      currentUserId: '2',
      assignedId: '1',
      lastClaimedUserId: '3'
    })

    expect(wrapper.vm.status).toEqual({ icon: 'fa-lock', text: 'statement.fragment.assignment.assigned' })

    // Should be claimed but deligated (to fachbehörde) if userId does not match assigneeId but matches lastClaimed (fragments).
    await wrapper.setProps({
      currentUserId: '2',
      assignedId: '1',
      lastClaimedUserId: '2'
    })
    expect(wrapper.vm.status).toEqual({ icon: 'fa-user-o', text: 'statement.fragment.assignment.assigned.self.delegated.locked' })

    // Should be claimed but deligated (to fachbehörde) if userId does not match assigneeId but matches lastClaimed - and there is no claimed-User at the moment (fragments).
    await wrapper.setProps({
      currentUserId: '2',
      assignedId: '',
      lastClaimedUserId: '2'
    })
    expect(wrapper.vm.status).toEqual({ icon: 'fa-user-o', text: 'statement.fragment.assignment.assigned.self.delegated.unlocked' })
  })
})
