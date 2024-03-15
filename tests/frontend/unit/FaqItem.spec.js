import DpFaqItem from '@DpJs/components/faq/DpFaqItem.vue'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import Vuex from 'vuex'

describe('DpFaqItem', () => {
  let store

  beforeEach(() => {
    store = new Vuex.Store({
      modules: {
        faqCategory: {
          namespaced: true,
          state: {
            items: {
              'faqItemParentId': {}
            }
          }
        },
        faq: {
          namespaced: true,
          state: {
            items: {
              'item1': {
                attributes: {
                  enabled: true
                }
              }
            }
          }
        }
      }
    })
  })

  it('displays the faq item title', () => {
    const wrapper = shallowMountWithGlobalMocks(DpFaqItem, {
      propsData: {
        availableGroupOptions: [
          {
            title: Translator.trans('role.fp'),
            id: 'fpVisible',
            showFor: 'GLAUTH'
          },
          {
            title: Translator.trans('institution'),
            id: 'invitableInstitutionVisible',
            showFor: 'GPSORG'
          },
          {
            title: Translator.trans('guest.citizen'),
            id: 'publicVisible',
            showFor: 'GGUEST'
          }
        ],
        faqItem: {
          attributes: {
            title: 'faq item title'
          },
          id: 'item1'
        },
        parentId: 'faqItemParentId'
      },
      store
    })

    expect(wrapper.html()).toContain(wrapper.props().faqItem.attributes.title)
  })

  it('displays a multiselect if group options are available', () => {
    const wrapper = shallowMountWithGlobalMocks(DpFaqItem, {
      propsData: {
        availableGroupOptions: [
          {
            title: Translator.trans('role.fp'),
            id: 'fpVisible',
            showFor: 'GLAUTH'
          },
          {
            title: Translator.trans('institution'),
            id: 'invitableInstitutionVisible',
            showFor: 'GPSORG'
          },
          {
            title: Translator.trans('guest.citizen'),
            id: 'publicVisible',
            showFor: 'GGUEST'
          }
        ],
        faqItem: {
          attributes: {
            title: 'faq item title'
          },
          id: 'item1'
        },
        parentId: 'faqItemParentId'
      },
      store
    })

    expect(wrapper.html()).toContain('dp-multiselect-stub')
  })

  // it('redirects to the edit page if the edit button is clicked', () => {
  //   const wrapper = shallowMountWithGlobalMocks(DpFaqItem, {
  //     propsData: {
  //       availableGroupOptions: [
  //         {
  //           title: Translator.trans('role.fp'),
  //           id: 'fpVisible',
  //           showFor: 'GLAUTH'
  //         },
  //         {
  //           title: Translator.trans('institution'),
  //           id: 'invitableInstitutionVisible',
  //           showFor: 'GPSORG'
  //         },
  //         {
  //           title: Translator.trans('guest.citizen'),
  //           id: 'publicVisible',
  //           showFor: 'GGUEST'
  //         }
  //       ],
  //       faqItem: {
  //         attributes: {
  //           title: 'faq item title'
  //         },
  //         id: 'item1'
  //       },
  //       parentId: 'faqItemParentId'
  //     },
  //     store
  //   })
  // })

  it('calls the delete function if the delete button is clicked', async () => {
    const deleteMock = jest.fn()
    const wrapper = shallowMountWithGlobalMocks(DpFaqItem, {
      methods: {
        deleteFaqItem: deleteMock
      },
      propsData: {
        availableGroupOptions: [
          {
            title: Translator.trans('role.fp'),
            id: 'fpVisible',
            showFor: 'GLAUTH'
          },
          {
            title: Translator.trans('institution'),
            id: 'invitableInstitutionVisible',
            showFor: 'GPSORG'
          },
          {
            title: Translator.trans('guest.citizen'),
            id: 'publicVisible',
            showFor: 'GGUEST'
          }
        ],
        faqItem: {
          attributes: {
            title: 'faq item title'
          },
          id: 'item1'
        },
        parentId: 'faqItemParentId'
      },
      store
    })

    const deleteButton = wrapper.find('[data-cy=deleteFaqItem]')
    await deleteButton.trigger('click')

    expect(deleteMock).toHaveBeenCalled()
  })

  it('renders correctly', () => {
    const wrapper = shallowMountWithGlobalMocks(DpFaqItem, {
      propsData: {
        availableGroupOptions: [
          {
            title: Translator.trans('role.fp'),
            id: 'fpVisible',
            showFor: 'GLAUTH'
          },
          {
            title: Translator.trans('institution'),
            id: 'invitableInstitutionVisible',
            showFor: 'GPSORG'
          },
          {
            title: Translator.trans('guest.citizen'),
            id: 'publicVisible',
            showFor: 'GGUEST'
          }
        ],
        faqItem: {
          attributes: {
            title: 'faq item title'
          },
          id: 'item1'
        },
        parentId: 'faqItemParentId'
      },
      store
    })

    expect(wrapper.html()).toMatchSnapshot()
  })
})
