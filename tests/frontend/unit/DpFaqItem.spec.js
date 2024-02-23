import { createLocalVue, mount, shallowMount } from '@vue/test-utils'
// eslint-disable-next-line import/extensions,sort-imports
import DpFaqItem from '../../../client/js/components/faq/DpFaqItem.vue'
import Vuex from 'vuex'

const localVue = createLocalVue()
localVue.use(Vuex)
global.Vue = localVue

/*// eslint-disable-next-line no-unused-vars
let store
beforeAll(() => {
  store = new Vuex.Store({
    state: {
      faqCategories: {
        items: {
          1: 'a',
          2: 'b'
        }// Define the state structure for faqCategory module]
      }
    },
    getters: {
      getFaqCategory: state => (id) => {
        return state.faqCategories.items[id]
      }
    }
  })
})*/

describe('DpFaqItem', () => {
  let store, store2
  let wrapper
  let faqCategoryModule, faqItemModule

  beforeEach(() => {
    faqCategoryModule = {
      namespaced: true,
      state: {
        items: [
          { 1: 'a' },
          { 2: 'b' }
        ]
      }
    }
    faqItemModule = {
      namespaced: true,
      state: {
        items: [
          { 1: 'a' },
          { 2: 'b' }
        ]
      }
    }
    store = new Vuex.Store({
      modules: {
        faqCategories: faqCategoryModule,
        faqItems: faqItemModule
      }
    })
    wrapper = mount(DpFaqItem, {
      localVue,
      store,
      computed: {
        currentParentItem () {
          return this.faqCategories?.state?.items[this.parentId]
        },
        itemEnabled: {
          get () {
            return this.faqItems?.state?.items[this.faqItem.id]?.attributes?.enabled
          }
        },
        faqCategories: () => faqCategoryModule.state.items
      },
      propsData: {
        faqItem: {
          attributes: {
            title: 'hello',
            id: 1
          }
        },
        availableGroupOptions: ['a', 'b'],
        parentId: '1',
        transformedCategoriesData: ['a', 'b']
      }
    })
  })
  it('button triggers delete', async () => {
    await localVue.nextTick()
    expect(wrapper.vm.currentParentItem).toBe('a')
    /* Wrapper.find('button').trigger('click') */
    console.log(wrapper.props())

    // eslint-disable-next-line jest/no-conditional-expect
    /* expect(wrapper.vm.xyz).toHaveBeenCalled() */
  })
})
