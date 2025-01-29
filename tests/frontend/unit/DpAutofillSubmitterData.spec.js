/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

// Component to test
import SubmitterComponent from '@DpJs/components/statement/statement/DpAutofillSubmitterData'

// Test data which is defined in Twig
import Submitters from './__mocks__/DpAutofillSubmitterData.json'

describe('Submitter', () => {
  it.skip('renders correct markup with permission..SubmitterInvited but permission..SubmitterCitizens = false', () => {
    global.features = {
      featureInstitutionParticipation: true,
      featureStatementCreateAutofillSubmitterInvited: true
    }

    const instance = shallowMountWithGlobalMocks(
      SubmitterComponent,
      {
        propsData: {
          procedureId: 'procedureId',
          request: {},
          submitters: Submitters,
          formDefinitions: {}
        },
        stubs: {
          'dp-multiselect': true
        }
      }
    )

    expect(instance.html()).toMatchSnapshot()
  })

  it.skip('renders correct markup with featureInstitutionParticipation = false', () => {
    global.features = {
      featureInstitutionParticipation: false
    }

    const instance = shallowMountWithGlobalMocks(
      SubmitterComponent,
      {
        propsData: {
          procedureId: 'procedureId',
          request: {},
          formDefinitions: {},
          submitters: Submitters
        }
      }
    )

    expect(instance.html()).toMatchSnapshot()
  })

  it.skip('renders correct markup with all permissions true', () => {
    global.features = {
      featureInstitutionParticipation: true,
      featureStatementCreateAutofillSubmitterInstitutions: true,
      featureStatementCreateAutofillSubmitterCitizens: true
    }

    const instance = shallowMountWithGlobalMocks(
      SubmitterComponent,
      {
        propsData: {
          procedureId: 'procedureId',
          request: {},
          formDefinitions: {},
          submitters: Submitters
        }
      }
    )

    expect(instance.html()).toMatchSnapshot()
  })
})
