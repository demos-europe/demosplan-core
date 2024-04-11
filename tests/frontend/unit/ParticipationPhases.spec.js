import { createLocalVue } from '@vue/test-utils'
import ParticipationPhases from '@DpJs/components/procedure/basicSettings/ParticipationPhases'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('ParticipationPhases', () => {
  const componentsPropsData = {
    autoswitchHint: 'Here comes a tooltip Hint',
    fieldName: 'some_name',
    helpText: 'Some Help Text',
    labelText: 'I am a Label',
    participationPhases: ['first', 'second', 'third'],
    permissionMessage: 'Some Message',
    phaseOptions: [{
      label: 'My Label C',
      permissionset: 'read',
      value: 'first'
    }, {
      label: 'My Label A',
      permissionset: 'write',
      value: 'fourth'
    }, {
      label: 'My Label B',
      permissionset: 'hidden',
      value: 'fifth'
    }],
    initSelectedPhase: 'first',
    iterator: {
      label: 'Iterator Label',
      name: 'iterator_input_name',
      tooltip: 'Tooltip text for the iterator',
      value: '1'
    }
  }


  it('should be an object', () => {
    expect(typeof ParticipationPhases).toBe('object')
  })

  it('should be named ParticipationPhases', () => {
    expect(ParticipationPhases.name).toBe('ParticipationPhases')
  })

  it('should set the "inParticipation"-State as expected', async () => {
    const localVue = createLocalVue()

    const wrapper = shallowMountWithGlobalMocks(ParticipationPhases, {
      propsData: componentsPropsData,
      localVue
    })

    expect(wrapper.vm.isInParticipation).toBe(true)

    await wrapper.setData({
      selectedPhase: 'fourth',
    })

    expect(wrapper.vm.isInParticipation).toBe(false)

    await wrapper.setData({
      selectedPhase: 'noneAtall',
    })

    expect(wrapper.vm.isInParticipation).toBe(false)
  })

  it('should set the Permission-Message-Text as expected', async () => {
    const localVue = createLocalVue()

    const wrapper = shallowMountWithGlobalMocks(ParticipationPhases, {
      propsData: componentsPropsData,
      localVue
    })

    expect(wrapper.vm.permissionMessageText).toEqual('Some Message permissionset.read')

    await wrapper.setData({
      selectedPhase: 'fifth',
    })

    expect(wrapper.vm.permissionMessageText).toEqual('Some Message permissionset.hidden')

    await wrapper.setData({
      selectedPhase: 'fourth',
    })

    expect(wrapper.vm.permissionMessageText).toEqual('Some Message permissionset.write')
  })
})
