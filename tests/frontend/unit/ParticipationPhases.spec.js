import ParticipationPhases from '@DpJs/components/procedure/basicSettings/ParticipationPhases'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('ParticipationPhases', () => {
  let wrapper

  beforeEach(() => {
    wrapper = shallowMountWithGlobalMocks(ParticipationPhases, {
      propsData: {
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
    })
  })

  it('should set the `inParticipation` state correctly depending on whether the selected Phase is a participation phase or not', async () => {
    expect(wrapper.vm.isInParticipation).toBe(true)

    await wrapper.setData({
      selectedPhase: 'fourth'
    })

    expect(wrapper.vm.isInParticipation).toBe(false)

    await wrapper.setData({
      selectedPhase: 'noneAtall'
    })

    expect(wrapper.vm.isInParticipation).toBe(false)
  })

  it('should set the Permission-Message-Text depending on the selected participation phase permissionset', async () => {
    expect(wrapper.vm.permissionMessageText).toEqual('Some Message permissionset.read')

    await wrapper.setData({
      selectedPhase: 'fifth'
    })

    expect(wrapper.vm.permissionMessageText).toEqual('Some Message permissionset.hidden')

    await wrapper.setData({
      selectedPhase: 'fourth'
    })

    expect(wrapper.vm.permissionMessageText).toEqual('Some Message permissionset.write')
  })

  it('should emit the correct value when a new phase is selected', async () => {
    const newPhase = 'newPhase'
    const dpSelect = wrapper.findComponent({ name: 'DpSelect' })
    dpSelect.vm.$emit('select', newPhase)
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('phase:select')[0]).toEqual([newPhase])
  })
})
