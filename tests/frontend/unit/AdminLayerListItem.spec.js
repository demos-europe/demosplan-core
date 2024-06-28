import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import AdminLayerListItem from '@DpJs/components/map/admin/AdminLayerListItem.vue'

describe('AdminLayerListItem.vue', () => {
  let wrapper

  beforeEach(() => {
    // Mock data for the component props
    const propsData = {
      element: {
        id: 'test-id',
        type: 'test-type'
      },
      sortingType: 'test-sorting',
      layerType: 'test-layer',
      isLoading: false,
      parentOrderPosition: 1,
      index: 0
    }

    // Mock data for the Vuex store
    const store = {
      state: {
        Layers: {
          activeLayerId: 'active-id',
          hoverLayerId: 'hover-id',
          hoverLayerIconIsHovered: false
        }
      },
      getters: {
        'Layers/element': () => ({ id: 'test-id', type: 'test-type', attributes: {} }),
        'Layers/attributeForElement': () => '',
        'Layers/visibilityGroupSize': () => 1,
        'Layers/elementListForLayerSidebar': () => []
      }
    }

    wrapper = shallowMountWithGlobalMocks(AdminLayerListItem, {
      propsData,
      mocks: {
        $store: store
      }
    })
  })

  it('showGroupableIcon returns correct value', () => {
    // Call the method
    const result = wrapper.vm.showGroupableIcon

    // Assert that the result is as expected
    // Replace 'expectedResult' with the value you expect the method to return
    const expectedResult = 'expectedResult'
    expect(result).toBe(expectedResult)
  })
})
