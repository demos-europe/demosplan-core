/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { mount } from '@vue/test-utils'
import OrganisationDataForm from '@DpJs/components/user/orgaDataEntry/OrganisationDataForm'

const organisation = (overrides = {}) => ({
  id: 'orga-1',
  addressId: 'address-1',
  name: 'Display name',
  technicalName: 'Official name',
  street: '',
  addressExtension: '',
  houseNumber: '',
  postalcode: '',
  city: '',
  phone: '',
  currentSlugName: '',
  submissionType: '',
  ...overrides,
})

const mountForm = ({ props = {}, organisationOverrides = {} } = {}) => mount(OrganisationDataForm, {
  props: {
    csrfToken: 'token',
    isOrgaDataEditable: true,
    isOrgaNameEditable: true,
    organisation: organisation(organisationOverrides),
    projectName: 'project',
    user: {},
    ...props,
  },
  global: {
    stubs: {
      EmailNotificationSettings: true,
      OrganisationBrandingSettings: true,
      OrganisationCompetence: true,
      PaperCopyPreferences: true,
    },
  },
})

const denyPermission = permission => {
  globalThis.hasPermission.mockImplementation(requested => requested !== permission)
}

describe('OrganisationDataForm', () => {
  afterEach(() => {
    globalThis.hasPermission.mockImplementation(() => true)
  })

  describe('name', () => {
    it('renders the editable name field as official name and submits it as name', () => {
      const wrapper = mountForm()

      const input = wrapper.find('[data-cy="organisationData:name"]')

      expect(input.element.value).toBe('Display name')
      expect(input.attributes('name')).toBe('orga-1:name')
      expect(wrapper.find('label[for="orga_name"]').text()).toContain('name.legal')
    })

    it('keeps the label of the name field without the permission to see the technical name', () => {
      denyPermission('field_organisation_technical_name')

      const wrapper = mountForm()

      expect(wrapper.find('label[for="orga_name"]').text()).toContain('name.legal')
    })

    it('is disabled without the permission to edit the name', () => {
      const wrapper = mountForm({ props: { isOrgaNameEditable: false } })

      expect(wrapper.find('[data-cy="organisationData:name"]').attributes('disabled')).toBeDefined()
    })
  })

  describe('technical name', () => {
    it('renders the technical name as read-only field that is not submitted', () => {
      const wrapper = mountForm()

      const input = wrapper.find('[data-cy="organisationData:technicalName"]')

      expect(input.exists()).toBe(true)
      expect(input.element.value).toBe('Official name')
      expect(input.attributes('disabled')).toBeDefined()
      expect(input.attributes('name')).toBeUndefined()
      expect(wrapper.find('label[for="orga_technical_name"]').text()).toContain('name.technical')
    })

    it.each([
      ['empty', ''],
      ['null', null],
      ['missing', undefined],
    ])('shows a dash while the technical name is %s', (label, technicalName) => {
      const wrapper = mountForm({ organisationOverrides: { technicalName } })

      expect(wrapper.find('[data-cy="organisationData:technicalName"]').element.value).toBe('–')
    })

    it('is hidden without the permission to see it', () => {
      denyPermission('field_organisation_technical_name')

      const wrapper = mountForm()

      expect(wrapper.find('[data-cy="organisationData:technicalName"]').exists()).toBe(false)
    })
  })
})
