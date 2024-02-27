import { createLocalVue, mount } from '@vue/test-utils'
// eslint-disable-next-line import/extensions,sort-imports
import DpFaqItem from '../../../client/js/components/faq/DpFaqItem.vue'
import Vuex from 'vuex'

const localVue = createLocalVue()
localVue.use(Vuex)

describe('DpFaqItem', () => {
  let store
  let wrapper
  let faqCategoryModule, faqItemModule

  beforeEach(() => {
    faqCategoryModule = {
      namespaced: true,
      state: {
        items: {
          1: 'Category A',
          2: 'Category B'
        }
      }
      // ... getters, actions, mutations, etc.
    }

    faqItemModule = {
      namespaced: true,
      state: {
        items: {
          1: { id: 1, attributes: { title: 'Title 1', enabled: true } },
          2: { id: 2, attributes: { title: 'Title 2', enabled: false } }
        }
      }
      // ... getters, actions, mutations, etc.
    }

    store = new Vuex.Store({
      modules: {
        faq: faqItemModule,
        faqCategory: faqCategoryModule
      }
    })

    // Ensure dplan.notify.notify and dplan.notify.error are mocked if they are not globally available
    global.dplan = {
      notify: {
        notify: jest.fn(),
        error: jest.fn()
      }
    }

    // Mock the `Translator.trans` if it's not globally available
    global.Translator = {
      trans: jest.fn().mockImplementation(key => key) // Return the key for simplicity
    }

    wrapper = mount(DpFaqItem, {
      store,
      propsData: {
        faqItem: {
          id: '1',
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
    /* Wrapper.find('button').trigger('click') */
    console.log(wrapper.props())

    // eslint-disable-next-line jest/no-conditional-expect
    /* expect(wrapper.vm.xyz).toHaveBeenCalled() */
  })
})
