/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import NewTagForm from '@DpJs/components/procedure/admin/InstitutionTagManagement/NewTagForm'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('NewTagForm', () => {
  let wrapper

  beforeEach(() => {
    wrapper = shallowMountWithGlobalMocks(NewTagForm, {
      propsData: {
        tagCategories: [
          {
            id: 1,
            name: 'Category 1',
            children: [{ name: 'Existing Tag' }]
          }
        ]
      }
    })
  })

  it('returns false when tag name is not unique within the same category', () => {
    const result = wrapper.vm.isTagNameUnique('Existing Tag', 1)
    expect(result).toBe(false)
  })

  it('returns true when tag name is unique within the same category', () => {
    const result = wrapper.vm.isTagNameUnique('New Tag', 1)
    expect(result).toBe(true)
  })

  it('returns true when tag name is not unique but in a different category', () => {
    const result = wrapper.vm.isTagNameUnique('Existing Tag', 2)
    expect(result).toBe(true)
  })

  it('resets the form and emits close event when resetNewTagForm is called', () => {
    wrapper.setData({
      newTag: { name: 'Test Tag', category: 1 }
    })
    wrapper.vm.resetNewTagForm()
    expect(wrapper.vm.newTag).toEqual({})
    expect(wrapper.emitted('newTagForm:close')).toBeTruthy()
  })

  it('emits close event when handleCloseForm is called', () => {
    wrapper.vm.handleCloseForm()
    expect(wrapper.emitted('newTagForm:close')).toBeTruthy()
  })
})
