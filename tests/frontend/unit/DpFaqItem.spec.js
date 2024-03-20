import { createLocalVue, flushPromises } from '@vue/test-utils'
import DpFaqItem from '../../../client/js/components/faq/DpFaqItem.vue'
import shallowMountWithGlobalMocks from '../../../client/js/VueConfigLocal'
import Vuex from 'vuex'

const localVue = createLocalVue()
localVue.use(Vuex)

describe('DpFaqItem', () => {
  let store
  let wrapper
  let faqCategory, faq
  const mockDeleteFaqItem = jest.fn()

  beforeEach(() => {
    faqCategory = {
      namespaced: true,
      state: {
        items: {
          faqItemParentId: {}
        }
      }
    }

    faq = {
      namespaced: true,
      state: {
        items: {
          1: {
            attributes: {
              enabled: true
            }
          }
        }
      }
    }

    store = new Vuex.Store({
      modules: {
        faq: faq,
        faqCategory: faqCategory
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
  })
  it('button triggers delete', async () => {
    wrapper = shallowMountWithGlobalMocks(DpFaqItem, {
      store,
      localVue,
      methods: {
        deleteFaqItem: mockDeleteFaqItem
      },
      propsData: {
        faqItem: {
          id: 1,
          attributes: {
            title: 'hello',
            id: 1
          }
        },
        parentId: '1',
        availableGroupOptions: ['a', 'b'],
        transformedCategoriesData: ['a', 'b']
      }
    })
    console.log(wrapper.props())
    await wrapper.vm.$nextTick() // Ensure the component has finished rendering.

    const deleteButton = wrapper.find('[data-cy="deleteFaqItem"]')
    await deleteButton.trigger('click')

    expect(mockDeleteFaqItem).toHaveBeenCalledTimes(1)
  })
})
