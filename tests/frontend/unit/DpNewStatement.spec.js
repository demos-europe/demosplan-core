import { createLocalVue, shallowMount } from '@vue/test-utils'
// eslint-disable-next-line import/extensions
import DpNewStatement from '../../../client/js/components/assessmenttable/DpNewStatement.vue'
import Vuex from 'vuex'

const localVue = createLocalVue()
localVue.use(Vuex)
global.Vue = localVue

// eslint-disable-next-line no-unused-vars
let actions, store
beforeAll(() => {
  actions = { applyBaseData: jest.fn(), dispatch: jest.fn() }
  store = new Vuex.Store({ actions })
})

describe('addLocationPrompt method', () => {
  it('put counties in sortSelected', () => {
    const wrapper = shallowMount(DpNewStatement)
    const data = { counties: ['a', 'b', 'c'] }
    wrapper.vm.addLocationPrompt(data)
    // eslint-disable-next-line jest/no-conditional-expect
    expect(DpNewStatement.methods.sortSelected('counties')).toHaveBeenCalled()
  })
})
