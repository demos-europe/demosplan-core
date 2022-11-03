import DpTreeListCheckbox from '@DpJs/components/core/DpTreeList/DpTreeListCheckbox'

import { shallowMount } from '@vue/test-utils'

describe('should return result aria.deselect_all when props are: checked = true, checkAll = true', () => {
  const wrapper = shallowMount(DpTreeListCheckbox, {
    propsData: {
      checked: true,
      checkAll: true,
      name: 'someName'
    }
  })

  it('returns aria.deselect_all', () => {
    expect(wrapper.vm.label).toEqual('aria.deselect_all')
  })
})

describe('should return result aria.select.all when props are: checked = false, checkAll = true', () => {
  const wrapper = shallowMount(DpTreeListCheckbox, {
    propsData: {
      checked: false,
      checkAll: true,
      name: 'someName'
    }
  })

  it('returns aria.select.all', () => {
    expect(wrapper.vm.label).toEqual('aria.select.all')
  })
})

describe('should return result aria.deselect when props are: checked = true, checkAll = false', () => {
  const wrapper = shallowMount(DpTreeListCheckbox, {
    propsData: {
      checked: true,
      checkAll: false,
      name: 'someName'
    }
  })

  it('returns aria.deselect', () => {
    expect(wrapper.vm.label).toEqual('aria.deselect')
  })
})

describe('should return result aria.select when props are: checked = false, checkAll = false', () => {
  const wrapper = shallowMount(DpTreeListCheckbox, {
    propsData: {
      checked: false,
      checkAll: false,
      name: 'someName'
    }
  })

  it('returns aria.select', () => {
    expect(wrapper.vm.label).toEqual('aria.select')
  })
})

describe('DpTreeListCheckbox', () => {
  it('should be an object', () => {
    expect(typeof DpTreeListCheckbox).toBe('object')
  })

  it('should be named DpTreeListCheckbox', () => {
    expect(DpTreeListCheckbox.name).toBe('DpTreeListCheckbox')
  })
})
