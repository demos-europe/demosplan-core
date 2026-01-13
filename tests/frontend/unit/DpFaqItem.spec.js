import { beforeEach, describe, expect, it, jest } from '@jest/globals'
import { flushPromises, shallowMount } from '@vue/test-utils'
import DpFaqItem from '../../../client/js/components/faq/DpFaqItem'
import Vuex from 'vuex'

describe('DpFaqItem', () => {
  let store
  let wrapper
  let faqCategory, faq

  const mockDeleteFaq = jest.fn(async (_context, payload) => payload)
  const mockUpdateCategory = jest.fn()

  beforeEach(() => {
    faqCategory = {
      namespaced: true,
      state: {
        items: {
          '1': {
            id: '1',
            relationships: {
              faq: {
                data: [{ id: '1', type: 'Faq' }],
              },
            },
          },
        },
      },
      mutations: {
        setItem: mockUpdateCategory,
      },
    }

    faq = {
      namespaced: true,
      state: {
        items: {
          '1': {
            id: '1',
            type: 'Faq',
            attributes: {
              enabled: true,
              title: 'Was ist eine Hansestadt?',
            },
          },
        },
      },
      actions: {
        delete: mockDeleteFaq,
      },
    }

    store = new Vuex.Store({
      modules: {
        Faq: faq,
        FaqCategory: faqCategory,
      },
    })

    global.dpconfirm = jest.fn(() => true)
    global.dplan = {
      notify: { notify: jest.fn(), error: jest.fn() },
    }
    global.Translator = { trans: jest.fn(key => key) }
    global.Routing = { generate: jest.fn((route, params) => `/faq/${params.faqID}`) }
  })

  it('button triggers delete faq item', async () => {
    wrapper = shallowMount(DpFaqItem, {
      global: {
        plugins: [store],
        mocks: {
          dpconfirm: global.dpconfirm,
          dplan: global.dplan,
          Translator: global.Translator,
          Routing: global.Routing,
        },
      },
      props: {
        faqItem: {
          id: '1',
          type: 'Faq',
          attributes: { title: 'Was ist eine Hansestadt?', enabled: true },
        },
        parentId: '1',
        availableGroupOptions: [
          { id: 'fpVisible', title: 'Fachplaner' },
          { id: 'publicVisible', title: 'Öffentlichkeit' },
        ],
      },
    })

    const deleteButton = wrapper.find('[data-cy="deleteFaqItem"]')
    await deleteButton.trigger('click')
    await flushPromises()

    expect(global.dpconfirm).toHaveBeenCalledTimes(1)
    expect(mockUpdateCategory).toHaveBeenCalledTimes(1)
    expect(mockDeleteFaq).toHaveBeenCalledTimes(1)
    expect(global.dplan.notify.notify).toHaveBeenCalledWith('confirm', 'confirm.faq.deleted')
  })
})
