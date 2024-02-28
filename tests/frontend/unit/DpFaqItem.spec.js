import { createLocalVue, mount, shallowMount } from '@vue/test-utils'
// eslint-disable-next-line import/extensions,sort-imports
import DpFaqItem from '../../../client/js/components/faq/DpFaqItem.vue'
import Vuex from 'vuex'

const localVue = createLocalVue()
localVue.use(Vuex)

describe('DpFaqItem', () => {
  let store
  let wrapper
  let faqCategory, faq

  beforeEach(() => {
    faqCategory = jest.fn()

    faq = jest.fn()

    store = new Vuex.Store({
      modules: {
        faqItems: faq,
        faqCategories: faqCategory
      }
    })

    store.dispatch = jest.fn()

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

    wrapper = shallowMount(DpFaqItem, {
      store,
      localVue,
      propsData: {
        faqItem: {
          id: 1,
          attributes: {
            title: 'hello',
            id: 1
          }
        },
        parentId: 1,
        availableGroupOptions: ['a', 'b'],
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
