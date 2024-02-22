import { createLocalVue, shallowMount } from '@vue/test-utils'
// eslint-disable-next-line import/extensions,sort-imports
import DpFaqItem from '../../../client/js/components/faq/DpFaqItem.vue'
import Vuex from 'vuex'

const localVue = createLocalVue()
localVue.use(Vuex)
global.Vue = localVue

// eslint-disable-next-line no-unused-vars
let store
beforeAll(() => {
  store = new Vuex.Store({
    state: {
      faqCategories: {
        items: [
          { itemA: 'a' },
          { itemB: 'b' }
        ] // Define the state structure for faqCategory module]
      }
    },
    getters: {
      getFaqCategory: state => (id) => {
        return state.faqCategories.items[id]
      }
    }
  })
})

describe('DpFaqItem', () => {
  it('button triggers delete', () => {
    console.log(store.state.faqCategories.items)
    const wrapper = shallowMount(DpFaqItem, {
      localVue,
      store,
      computed: {
        currentParentItem: 'a'
      },
      propsData: {
        faqItem: {
          attributes: {
            title: 'hello'
          }
        },
        availableGroupOptions: ['a', 'b'],
        parentId: '1'
      }
    })
    /* Wrapper.find('button').trigger('click') */
    console.log(wrapper.props())

    // eslint-disable-next-line jest/no-conditional-expect
    /* expect(wrapper.vm.xyz).toHaveBeenCalled() */
  })
})
