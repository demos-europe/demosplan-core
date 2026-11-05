/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import DpOrganisationFormFields from '@DpJs/components/user/DpOrganisationList/DpOrganisationFormFields'
import { mount } from '@vue/test-utils'

const organisation = (attributes = {}) => ({
  id: 'orga-1',
  type: 'Orga',
  attributes: {
    addressExtension: '',
    ccEmail2: '',
    city: '',
    competence: '',
    copy: 0,
    copySpec: '',
    email2: '',
    houseNumber: '',
    name: 'Display name',
    phone: '',
    postalcode: '',
    registrationStatuses: [],
    showlist: false,
    showname: false,
    street: '',
    submissionType: 'standard',
    technicalName: 'Official name',
    twoFactorRequired: false,
    ...attributes,
  },
  relationships: {
    customers: { data: [] },
    departments: { data: [] },
  },
})

const mountFields = ({ id = 'orga-1', writableFields = ['name'], attributes = {} } = {}) => mount(DpOrganisationFormFields, {
  props: {
    availableOrgaTypes: [],
    organisation: { ...organisation(attributes), id },
    organisationId: id,
  },
  global: {
    provide: {
      proceduresDirectLinkPrefix: '',
      projectName: 'project',
      showNewStatementNotification: false,
      subdomain: 'subdomain',
      submissionTypeDefault: 'standard',
      submissionTypeShort: 'short',
      writableFields,
    },
    stubs: {
      AddonWrapper: true,
      DpDetails: true,
      DpEditor: true,
    },
  },
})

const denyPermission = permission => {
  globalThis.hasPermission.mockImplementation(requested => requested !== permission)
}

describe('DpOrganisationFormFields', () => {
  afterEach(() => {
    globalThis.hasPermission.mockImplementation(() => true)
  })

  it('labels the editable name field as display name', () => {
    const wrapper = mountFields({ writableFields: ['name'] })

    expect(wrapper.find('label[for="orga-1:orgaName"]').text()).toContain('name.displayed')
    expect(wrapper.find('[data-cy="orgaFormField:orgaName"]').element.value).toBe('Display name')
  })

  it('keeps the former label of the name field without the permission to see the official name', () => {
    denyPermission('field_organisation_technical_name')

    const wrapper = mountFields({ writableFields: ['name'] })

    expect(wrapper.find('label[for="orga-1:orgaName"]').text()).toContain('name.legal')
    expect(wrapper.find('label[for="orga-1:orgaName"]').text()).not.toContain('name.displayed')
  })

  it('shows the technical name as read-only text, even when the name is editable', () => {
    const wrapper = mountFields({ writableFields: ['name'] })

    const technicalName = wrapper.find('[data-cy="orgaFormField:technicalName"]')

    expect(technicalName.exists()).toBe(true)
    expect(technicalName.text()).toBe('Official name')
    expect(technicalName.element.tagName).toBe('P')
    expect(wrapper.text()).toContain('name.legal')
  })

  it.each([
    ['empty', ''],
    ['null', null],
    ['missing', undefined],
  ])('shows a dash while the technical name is %s', (label, technicalName) => {
    const wrapper = mountFields({ attributes: { technicalName } })

    expect(wrapper.find('[data-cy="orgaFormField:technicalName"]').text()).toBe('–')
  })

  it('hides the technical name without the permission to see the official name', () => {
    denyPermission('field_organisation_technical_name')

    const wrapper = mountFields()

    expect(wrapper.find('[data-cy="orgaFormField:technicalName"]').exists()).toBe(false)
  })

  it('hides the technical name in the form that creates an organisation', () => {
    const wrapper = mountFields({ id: '' })

    expect(wrapper.find('[data-cy="orgaFormField:technicalName"]').exists()).toBe(false)
  })
})
