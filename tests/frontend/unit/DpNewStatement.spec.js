import { beforeEach, describe, expect, it, jest } from '@jest/globals'
import DpNewStatement from '../../../client/js/components/assessmenttable/DpNewStatement.vue'
import { shallowMount } from '@vue/test-utils'
import Vuex from 'vuex'

describe('DpNewStatement', () => {
  let store
  let wrapper
  let mockApplyBaseData

  beforeEach(() => {
    mockApplyBaseData = jest.fn(async () => {})

    const assessmentTable = {
      namespaced: true,
      state: {
        counties: [
          { id: '1', name: 'Berlin' },
          { id: '2', name: 'Hamburg' },
          { id: '3', name: 'Bremen' },
        ],
        municipalities: [
          { id: '1', name: 'München' },
          { id: '2', name: 'Hannover' },
        ],
        priorityAreas: [],
      },
      getters: {
        counties: state => state.counties,
        municipalities: state => state.municipalities,
        priorityAreas: state => state.priorityAreas,
        procedurePhases: () => () => [],
      },
      actions: {
        applyBaseData: mockApplyBaseData,
      },
    }

    store = new Vuex.Store({
      modules: {
        AssessmentTable: assessmentTable,
      },
    })

    wrapper = shallowMount(DpNewStatement, {
      global: {
        plugins: [store],
      },
      props: {
        procedureId: '123',
        currentExternalPhase: 'participation',
        currentInternalPhase: 'evaluation',
      },
    })
  })

  it('adds counties from data and sorts them', () => {
    const sortSelectedSpy = jest.spyOn(wrapper.vm, 'sortSelected')

    const data = { counties: ['2', '1'] }
    wrapper.vm.addLocationPrompt(data)

    expect(wrapper.vm.values.counties).toHaveLength(2)
    expect(wrapper.vm.values.counties[0].name).toBe('Berlin')
    expect(wrapper.vm.values.counties[1].name).toBe('Hamburg')
    expect(wrapper.vm.countiesPromptAdded).toBe(true)
    expect(sortSelectedSpy).toHaveBeenCalledWith('counties')
  })

  it('resets counties when no county IDs are provided', () => {
    const data = { counties: [] }
    wrapper.vm.addLocationPrompt(data)

    expect(wrapper.vm.values.counties).toEqual([])
    expect(wrapper.vm.countiesPromptAdded).toBe(false)
  })
})
