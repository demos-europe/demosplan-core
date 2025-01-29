/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DpPublicLayerListCategory from '@DpJs/components/map/publicdetail/controls/layerlist/DpPublicLayerListCategory'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

const propsData = {
  group: {
    id: 'group-id',
    attributes: {
      parentId: 'xxx-parentID'
    },
    relationships: {
      categories: {
        data: []
      },
      gisLayers: {
        data: []
      }
    }
  },
  layerGroupsAlternateVisibility: true
}

describe('DpPublicLayerListCategory', () => {
  it('should have the correct prop-values', () => {
    const wrapper = shallowMountWithGlobalMocks(DpPublicLayerListCategory, {
      propsData,
      computed: {
        elementListForLayerSidebar: () => [],
        layers: () => []
      }
    })

    expect(wrapper.props().layerGroupsAlternateVisibility).toBe(true)
    expect(typeof wrapper.props().group).toBe('object')
    expect(wrapper.props().group.id).toBe('group-id')
    expect(wrapper.props().group.attributes.parentId).toBe('xxx-parentID')
  })

  it('should compute the contextualHelp-ID correctly', () => {
    const layerfromStoreMock = jest.fn()
    layerfromStoreMock.mockReturnValue([{
      layers: [],
      unfolded: false,
      layerType: 'overlay',
      layerGroupsAlternateVisibility: true
    }, {
      layers: [],
      unfolded: false,
      layerType: 'overlay',
      layerGroupsAlternateVisibility: true
    }])

    const wrapper = shallowMountWithGlobalMocks(DpPublicLayerListCategory, {
      propsData,
      computed: {
        elementListForLayerSidebar: () => layerfromStoreMock
      },
      stubs: {
        'dp-public-layer-list': true
      }
    })

    expect(wrapper.vm.elementListForLayerSidebar()).toEqual([{
      layers: [],
      unfolded: false,
      layerType: 'overlay',
      layerGroupsAlternateVisibility: true
    }, {
      layers: [],
      unfolded: false,
      layerType: 'overlay',
      layerGroupsAlternateVisibility: true
    }])

    expect(wrapper.vm.contextualHelpId).toMatch('contextualHelpgroup-id')
    expect(wrapper.vm.id).toMatch('layergroupgroupid')
  })

  it('should compute the isTopLevelCategory correct', () => {
    const layerfromStoreMock = jest.fn()
    layerfromStoreMock.mockReturnValue([{
      layers: [],
      unfolded: false,
      layerType: 'overlay',
      layerGroupsAlternateVisibility: true
    }, {
      layers: [],
      unfolded: false,
      layerType: 'overlay',
      layerGroupsAlternateVisibility: true
    }])

    const wrapper = shallowMountWithGlobalMocks(DpPublicLayerListCategory, {
      propsData,
      computed: {
        elementListForLayerSidebar: () => jest.fn().mockReturnValue([]),
        rootId: () => 'xxx-rootID'
      },
      stubs: {
        'dp-public-layer-list': true
      }
    })

    expect(wrapper.vm.isTopLevelCategory).toBe(false)
  })

  it('should render an empty layout if there are no layers', () => {
    const wrapper = shallowMountWithGlobalMocks(DpPublicLayerListCategory, {
      propsData,
      computed: {
        elementListForLayerSidebar: () => [],
        layers: () => []
      }
    })

    expect(wrapper.html()).toMatchSnapshot()
  })

  it('should render a list if there are layers', () => {
    const wrapper = shallowMountWithGlobalMocks(DpPublicLayerListCategory, {
      propsData,
      computed: {
        elementListForLayerSidebar: () => [],
        layers: () => [{
          layers: [],
          unfolded: false,
          layerType: 'overlay',
          layerGroupsAlternateVisibility: true
        }, {
          layers: [],
          unfolded: false,
          layerType: 'overlay',
          layerGroupsAlternateVisibility: true
        }]
      },
      stubs: {
        'dp-public-layer-list': true
      }
    })

    expect(wrapper.html()).toMatchSnapshot()
  })

  it('should toggle its visibility when toggle is called', () => {
    const wrapper = shallowMountWithGlobalMocks(DpPublicLayerListCategory, {
      propsData,
      computed: {
        elementListForLayerSidebar: () => [],
        layers: () => []
      },
      stubs: {
        'dp-public-layer-list': true
      }
    })

    wrapper.setData({
      isVisible: true
    })

    expect(wrapper.vm.isVisible).toBe(true)

    wrapper.vm.toggle()
    expect(wrapper.vm.isVisible).toBe(false)
  })

  it('should toggle its visibility when toggleFromSelf is called', () => {
    const wrapper = shallowMountWithGlobalMocks(DpPublicLayerListCategory, {
      propsData,
      computed: {
        elementListForLayerSidebar: () => [],
        layers: () => []
      },
      stubs: {
        'dp-public-layer-list': true
      }
    })

    wrapper.setData({
      isVisible: true
    })

    expect(wrapper.vm.isVisible).toBe(true)

    wrapper.vm.toggleFromSelf()
    expect(wrapper.vm.isVisible).toBe(false)
  })

  it('should toggle its visibility when toggleFromParent is called', () => {
    const wrapper = shallowMountWithGlobalMocks(DpPublicLayerListCategory, {
      propsData,
      computed: {
        elementListForLayerSidebar: () => [],
        layers: () => []
      },
      stubs: {
        'dp-public-layer-list': true
      }
    })

    wrapper.vm.toggleFromParent([], false)
    expect(wrapper.vm.isVisible).toBe(true)
  })

  it('should be parent of a child', () => {
    const elementList = [
      { id: 'aaa', type: 'GisLayer' },
      { id: 'bbb', type: 'GisLayerCategory' },
      { id: 'ccc', type: 'GisLayerCategory' },
      { id: 'ddd', type: 'GisLayerCategory' },
      { id: 'eee', type: 'GisLayer' }
    ]

    const wrapper = shallowMountWithGlobalMocks(DpPublicLayerListCategory, {
      propsData,
      computed: {
        elementListForLayerSidebar: () => { return () => [] },
        layers: () => []
      },
      stubs: {
        'dp-public-layer-list': true
      }
    })

    const component = wrapper.vm
    expect(typeof component.elementListForLayerSidebar).toBe('function')
    expect(component.isParentOf(elementList, 'ccc')).toBe(true)
    expect(component.isParentOf(elementList, 'eee')).toBe(false)
    expect(component.isParentOf(elementList, 'ggg')).toBe(false)
  })
})
