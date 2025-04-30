/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DpPublicStatement from '@DpJs/components/statement/publicStatementLists/DpPublicStatement'
import { generateMenuItems } from '@DpJs/components/statement/publicStatementLists/menuItems'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('DpPublicStatement', () => {
  it('should mount', () => {
    const wrapper = shallowMountWithGlobalMocks(DpPublicStatement, {
      props: {
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
        procedureId: '45752f51-f68a-11e5-b083-005056ae0004',
        rejectedReason: '',
        submittedDate: null,
        text: '',
        user: 'buerger new'
      }
    })

    expect(wrapper).toBeDefined()
  })
})
