/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createLocalVue } from '@vue/test-utils'
import DpPublicStatement from '@DemosPlanStatementBundle/components/publicStatementLists/DpPublicStatement'
import { generateMenuItems } from '@DemosPlanStatementBundle/components/publicStatementLists/menuItems'
import shallowMountWithGlobalMocks from '@DemosPlanCoreBundle/VueConfigLocal'

describe('DpPublicStatement', () => {
  it('should be an object', () => {
    expect(typeof DpPublicStatement).toBe('object')
  })

  it('should be named DpPublicStatement', () => {
    expect(DpPublicStatement.name).toBe('DpPublicStatement')
  })

  it('should mount', () => {
    const localVue = createLocalVue()
    const wrapper = shallowMountWithGlobalMocks(DpPublicStatement, {
      propsData: {
        attachments: [],
        county: null,
        createdDate: '18.02.2021 15:02',
        department: 'anonym',
        document: 'Fehlanzeige',
        elementId: 'f214b9c0-2e17-11eb-99a9-b026282c0641',
        id: '26c44f26-71f4-11eb-9a89-0242ac16ff03',
        isPublished: false,
        menuItemsGenerator: generateMenuItems,
        number: 1016,
        organisation: 'Bürger',
        paragraph: 'k.A.',
        paragraphId: '',
        phase: 'Frühzeitige Beteiligung Öffentlichkeit',
        polygon: {},
        priorityAreas: null,
        rejectedReason: '',
        submittedDate: null,
        text: '',
        user: 'buerger new'
      },
      localVue
    })
  })
})
