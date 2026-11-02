/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import InstitutionTagManagement from '@DpJs/components/procedure/admin/InstitutionTagManagement/InstitutionTagManagement'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('InstitutionTagManagement', () => {
  it('lets the institution list reload itself on page 1 so rows and pager stay in sync', () => {
    const getInstitutionsByPage = vi.fn(() => Promise.resolve())
    const wrapper = shallowMountWithGlobalMocks(InstitutionTagManagement, {
      global: {
        renderStubDefaultSlot: true,
        stubs: {
          InstitutionList: {
            template: '<div />',
            methods: { getInstitutionsByPage },
          },
        },
      },
    })

    wrapper.vm.institutionListReset()

    expect(getInstitutionsByPage).toHaveBeenCalledTimes(1)
    expect(getInstitutionsByPage).toHaveBeenCalledWith(1)
  })
})
