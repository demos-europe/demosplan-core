<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    data-dp-validate="customerSettings"
    ref="customerSettings">
    <dp-loading v-if="isLoading" />

    <template v-else>
      <!-- Logo and Color Variables -->
      <customer-settings-section
        v-if="hasPermission('feature_platform_logo_edit') || hasPermission('feature_customer_branding_edit')"
        data-cy="customerSettings:customerBrandingLabel"
        is-open
        :title="Translator.trans('customer.branding.label')">
        <customer-settings-branding
          :branding-id="customerBrandingId"
          @saveBrandingUpdate="fetchCustomerData" />
      </customer-settings-section>

      <!-- Map -->
      <customer-settings-section
        v-if="hasPermission('feature_platform_public_index_map_settings')"
        data-cy="customerSettings:mapMainPageSettings"
        is-open
        :title="Translator.trans('map.mainpage.settings')">
        <customer-settings-map
          :current-customer-id="currentCustomerId"
          :init-layer="initLayer"
          :init-layer-url="initLayerUrl"
          :init-map-attribution="mapAttribution"
          :map-extent="mapExtent" />
      </customer-settings-section>

      <!-- Imprint -->
      <customer-settings-section
        v-if="hasPermission('feature_imprint_text_customized_view')"
        data-cy="customerSettings:imprint"
        :title="Translator.trans('imprint')">
        <dp-label
          for="r_imprint"
          :text="Translator.trans('customer.imprint.explanation', { url: imprintUrl })" />
        <dp-editor
          id="r_imprint"
          data-cy="customerSettings:imprintTextEditor"
          v-model="customer.imprint"
          hidden-input="r_imprint"
          :toolbar-items="{
            fullscreenButton: true,
            headings: [2,3,4],
            linkButton: true
          }" />
        <dp-button-row
          class="u-mt"
          data-cy="customerSettings:imprintTextEditor"
          primary
          secondary
          :busy="isBusy"
          :secondary-text="Translator.trans('reset')"
          @secondary-action="resetProperty('imprint')"
          @primary-action="saveSettings('imprint')" />
      </customer-settings-section>

      <!-- Data Protection -->
      <customer-settings-section
        v-if="hasPermission('feature_data_protection_text_customized_view')"
        data-cy="customerSettings:dataProtectionNotes"
        :title="Translator.trans('data.protection.notes')">
        <dp-label
          for="r_dataProtection"
          :text="Translator.trans('customer.data.protection.explanation')" />
        <dp-editor
          id="r_dataProtection"
          data-cy="customerSettings:dataProtection"
          v-model="customer.dataProtection"
          hidden-input="r_dataProtection"
          :toolbar-items="{
            fullscreenButton: true,
            headings: [2,3,4],
            linkButton: true
          }" />
        <dp-button-row
          class="u-mt"
          data-cy="customerSettings:dataProtectionNotes"
          primary
          secondary
          :busy="isBusy"
          :secondary-text="Translator.trans('reset')"
          @secondary-action="resetProperty('dataProtection')"
          @primary-action="saveSettings('dataProtection')" />
      </customer-settings-section>

      <!-- Terms of use -->
      <customer-settings-section
        v-if="hasPermission('feature_customer_terms_of_use_edit')"
        data-cy="customerSettings:termsOfUse"
        :title="Translator.trans('terms.of.use.notes')">
        <dp-label
          for="r_termsOfUse"
          :text="Translator.trans('customer.terms.of.use.explanation')" />
        <dp-editor
          id="r_termsOfUse"
          v-model="customer.termsOfUse"
          hidden-input="r_termsOfUse"
          :toolbar-items="{
            fullscreenButton: true,
            headings: [2,3,4],
            linkButton: true
          }" />
        <dp-button-row
          class="u-mt"
          data-cy="customerSettings:termsOfUse"
          primary
          secondary
          :busy="isBusy"
          :secondary-text="Translator.trans('reset')"
          @secondary-action="resetProperty('termsOfUse')"
          @primary-action="saveSettings('termsOfUse')" />
      </customer-settings-section>

      <!-- Xplanning -->
      <customer-settings-section
        v-if="hasPermission('feature_customer_xplanning_edit')"
        data-cy="customerSettings:xplanning"
        :title="Translator.trans('xplanning.notes')">
        <dp-label
          for="r_xplanning"
          :text="Translator.trans('customer.xplanning.explanation')" />
        <dp-editor
          id="r_xplanning"
          v-model="customer.xplanning"
          hidden-input="r_xplanning"
          :toolbar-items="{
            fullscreenButton: true,
            headings: [2,3,4],
            linkButton: true
          }" />
        <dp-button-row
          class="u-mt"
          data-cy="customerSettings:xplanning"
          primary
          secondary
          :busy="isBusy"
          :secondary-text="Translator.trans('reset')"
          @secondary-action="resetProperty('xplanning')"
          @primary-action="saveSettings('xplanning')" />
      </customer-settings-section>

      <!-- Sign language video page -->
      <customer-settings-section
        v-if="hasPermission('field_sign_language_overview_video_edit')"
        data-cy="customerSettings:overviewVideo"
        :title="Translator.trans('signLanguage.explanation')">
        <p v-text="Translator.trans('customer.signLanguage.explanation.hint')" />
        <dp-label
          :text="Translator.trans('customer.signLanguage.explanation.label')"
          for="signLanguageOverviewDescription" />
        <dp-editor
          id="signLanguageOverviewDescription"
          hidden-input="r_signLanguageOverviewDescription"
          v-model="customer.signLanguageOverviewDescription"
          :toolbar-items="{
            linkButton: true,
            headings: [2, 3, 4]
          }" />
        <h3
          class="u-mt"
          v-text="Translator.trans('video')" />
        <customer-settings-sign-language-video
          v-if="!isLoadingSignLanguageOverviewVideo"
          :current-customer-id="this.currentCustomerId"
          :sign-language-overview-video="signLanguageOverviewVideo"
          :sign-language-overview-description="customer.signLanguageOverviewDescription"
          @created="fetchCustomerData"
          @deleted="fetchCustomerData" />
        <dp-loading v-else />
      </customer-settings-section>

      <!-- Accessibility explanation -->
      <customer-settings-section
        v-if="hasPermission('field_customer_accessibility_explanation_edit')"
        data-cy="customerSettings:customerAccessibilityExplanation"
        :title="Translator.trans('accessibility.explanation')">
        <dp-label
          for="r_accessibilityExplanation"
          :text="Translator.trans('customer.accessibility.explanation.label')" />
        <dp-editor
          id="r_accessibilityExplanation"
          v-model="customer.accessibilityExplanation"
          hidden-input="r_accessibilityExplanation"
          :toolbar-items="{
            fullscreenButton: true,
            headings: [2,3,4],
            linkButton: true
          }" />
        <dp-button-row
          class="u-mt"
          data-cy="customerSettings:accessibilityExplanation"
          primary
          secondary
          :busy="isBusy"
          :secondary-text="Translator.trans('reset')"
          @secondary-action="resetProperty('accessibilityExplanation')"
          @primary-action="saveSettings('accessibilityExplanation')" />
      </customer-settings-section>

      <customer-settings-section
        v-if="hasPermission('field_simple_language_overview_description_edit')"
        data-cy="customerSettings:overviewDescription"
        :title="Translator.trans('language.simple')">
        <dp-label
          for="r_simpleLanguage"
          :text="Translator.trans('customer.simpleLanguage.label')" />
        <dp-editor
          id="r_simpleLanguage"
          v-model="customer.overviewDescriptionInSimpleLanguage"
          :basic-auth="dplan.settings.basicAuth"
          hidden-input="r_simpleLanguage"
          :routes="{
            getFileByHash: (hash) => Routing.generate('core_file', { hash: hash })
          }"
          :toolbar-items="{
            fullscreenButton: true,
            headings: [2,3,4],
            imageButton: true,
            linkButton: true
          }"
          :tus-endpoint="dplan.paths.tusEndpoint" />
        <dp-button-row
          class="u-mt"
          data-cy="customerSettings:overviewDescription"
          primary
          secondary
          :busy="isBusy"
          :secondary-text="Translator.trans('reset')"
          @secondary-action="resetProperty('overviewDescriptionInSimpleLanguage')"
          @primary-action="saveSettings('overviewDescriptionInSimpleLanguage')" />
      </customer-settings-section>

      <customer-settings-section
        v-if="hasPermission('feature_customer_support_contact_administration')"
        data-cy="customerSettings:supportContactAdministration"
        :title="Translator.trans('support')">
        <customer-settings-support />
      </customer-settings-section>

      <customer-settings-section
        v-if="hasPermission('feature_customer_login_support_contact_administration')"
        data-cy="customerSettings:supportLogin"
        :title="Translator.trans('support.login')">
        <customer-settings-login-support />
      </customer-settings-section>
    </template>
  </div>
</template>

<script>
import { DpButtonRow, DpLabel, DpLoading, dpValidateMixin } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import CustomerSettingsBranding from './CustomerSettingsBranding'
import CustomerSettingsLoginSupport from './CustomerSettingsLoginSupport'
import CustomerSettingsSection from './CustomerSettingsSection'
import CustomerSettingsSignLanguageVideo from './CustomerSettingsSignLanguageVideo'
import CustomerSettingsSupport from './CustomerSettingsSupport'
import { defineAsyncComponent } from 'vue'

export default {
  name: 'CustomerSettings',

  components: {
    DpButtonRow,
    CustomerSettingsBranding,
    CustomerSettingsLoginSupport,
    CustomerSettingsMap: defineAsyncComponent(() => import('./CustomerSettingsMap')),
    CustomerSettingsSection,
    CustomerSettingsSignLanguageVideo,
    CustomerSettingsSupport,
    DpLabel,
    DpLoading,
    DpEditor: defineAsyncComponent(async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')
      return DpEditor
    })
  },

  mixins: [dpValidateMixin],

  props: {
    currentCustomerId: {
      required: true,
      type: String
    },

    imprintUrl: {
      required: false,
      type: String,
      default: ''
    },

    initLayerUrl: {
      required: false,
      type: String,
      default: ''
    },

    initLayer: {
      required: false,
      type: String,
      default: ''
    },

    mapAttribution: {
      required: false,
      type: String,
      default: ''
    },

    mapExtent: {
      required: false,
      type: Array,
      default: () => []
    }
  },

  data () {
    return {
      customer: {
        accessibilityExplanation: '',
        dataProtection: '',
        imprint: '',
        overviewDescriptionInSimpleLanguage: '',
        signLanguageOverviewDescription: '',
        signLanguageOverviewVideo: '',
        termsOfUse: '',
        xplanning: ''
      },
      isLoading: true,
      isLoadingSignLanguageOverviewVideo: true,
      requestFields: {},
      requestIncludes: [],
      signLanguageOverviewVideo: {
        description: '',
        file: '',
        id: null,
        mimetype: '',
        title: ''
      },
      isBusy: false
    }
  },

  computed: {
    ...mapState('Branding', {
      brandingList: 'items'
    }),

    ...mapState('Customer', {
      customerList: 'items'
    }),

    customerBrandingId () {
      return this.customerList[this.currentCustomerId].relationships?.branding?.data?.id ?? ''
    },

    isUnsavedSignLanguageVideo () {
      // The signLanguageOverviewVideo.file is available after the video was uploaded and the signLanguageOverviewVideo.id is only available after the video was saved
      return this.signLanguageOverviewVideo.file && !this.signLanguageOverviewVideo.id
    }
  },

  methods: {
    ...mapActions('Branding', {
      fetchBranding: 'list',
      saveBranding: 'save'
    }),

    ...mapActions('Customer', {
      fetchCustomer: 'list',
      saveCustomer: 'save'
    }),

    ...mapMutations('Branding', {
      updateBranding: 'setItem'
    }),

    ...mapMutations('Customer', {
      updateCustomer: 'setItem'
    }),

    addAttributesToField (field, attributes) {
      this.requestFields[field] = [
        ...(this.requestFields[field] ? this.requestFields[field] : []),
        ...attributes
      ]
    },

    fetchCustomerData () {
      this.isLoadingSignLanguageOverviewVideo = true
      const payload = this.getRequestPayload()

      this.fetchCustomer(payload)
        .then(res => {
          // Update fields
          const currentCustomer = this.customerList[this.currentCustomerId]
          const currentData = currentCustomer.attributes

          this.customer = {
            ...this.customer,
            ...currentData
          }
        })
        .catch(err => {
          console.error(err)
        })
        .finally(() => {
          this.isLoading = this.isLoadingSignLanguageOverviewVideo = false
        })
    },

    getRequestPayload () {
      this.requestIncludes = []
      this.requestFields = {}

      if (hasPermission('feature_platform_logo_edit')) {
        this.requestIncludes.push('branding', 'branding.logo')
        this.addAttributesToField('Branding', ['logo'])
        this.addAttributesToField('Customer', ['branding'])
        this.addAttributesToField('File', ['hash'])
      }

      if (hasPermission('feature_customer_branding_edit')) {
        this.requestIncludes.push('branding')
        this.addAttributesToField('Branding', ['styling'])
        this.addAttributesToField('Customer', ['branding'])
      }

      if (hasPermission('feature_platform_public_index_map_settings')) {
        this.addAttributesToField('Customer', ['baseLayerUrl', 'baseLayerLayers', 'mapAttribution'])
      }

      if (hasPermission('field_sign_language_overview_video_edit')) {
        this.requestIncludes.push('signLanguageOverviewVideo', 'signLanguageOverviewVideo.file')
        this.addAttributesToField('File', ['mimetype'])
        this.addAttributesToField('Customer', ['signLanguageOverviewDescription', 'signLanguageOverviewVideo'])
        this.addAttributesToField('SignLanguageOverviewVideo', ['description', 'file', 'title'])
      }

      if (hasPermission('field_simple_language_overview_description_edit')) {
        this.addAttributesToField('Customer', ['overviewDescriptionInSimpleLanguage'])
      }

      if (hasPermission('field_customer_accessibility_explanation_edit')) {
        this.addAttributesToField('Customer', ['accessibilityExplanation'])
      }

      if (hasPermission('feature_customer_xplanning_edit')) {
        this.addAttributesToField('Customer', ['xplanning'])
      }

      if (hasPermission('feature_customer_terms_of_use_edit')) {
        this.addAttributesToField('Customer', ['termsOfUse'])
      }

      if (hasPermission('feature_data_protection_text_customized_view')) {
        this.addAttributesToField('Customer', ['dataProtection'])
      }

      if (hasPermission('feature_imprint_text_customized_view')) {
        this.addAttributesToField('Customer', ['imprint'])
      }

      if (hasPermission('feature_customer_support_contact_administration')) {
        this.requestIncludes.push('customerContacts')
        this.addAttributesToField('CustomerLoginSupportContact', ['title', 'text', 'phoneNumber', 'eMailAddress', 'visible'])
        this.addAttributesToField('Customer', ['customerContacts'])
      }

      if (hasPermission('feature_customer_login_support_contact_administration')) {
        this.requestIncludes.push('customerLoginSupportContact')
        this.addAttributesToField('CustomerLoginSupportContact', ['title', 'text', 'phoneNumber', 'eMailAddress'])
        this.addAttributesToField('Customer', ['customerLoginSupportContact'])
      }

      // Transform arrays to csv strings ready to be passed into query
      for (const prop in this.requestFields) {
        this.requestFields[prop] = this.requestFields[prop].join(',')
      }

      return {
        filter: {
          isCurrentCustomer: {
            condition: {
              path: 'id',
              value: this.currentCustomerId
            }
          }
        },
        fields: this.requestFields,
        include: this.requestIncludes.join(',')
      }
    },

    resetProperty (property) {
      const currentCustomer = this.customerList[this.currentCustomerId]
      this.customer[property] = currentCustomer.attributes[property]
    },

    saveSettings (property) {
      this.isBusy = true
      const payload = {
        id: this.currentCustomerId,
        type: 'Customer',
        attributes: {
          ...this.customerList[this.currentCustomerId].attributes,
          [property]: this.customer[property]
        }
      }
      this.updateCustomer(payload)
      this.saveCustomer(this.currentCustomerId).then(() => {
        dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        this.isBusy = false
      })
    },

    submit () {
      // Confirm submitting the form if the video was not saved and would be lost
      if (this.isUnsavedSignLanguageVideo && dpconfirm(Translator.trans('check.sign_language_video.saved')) === false) {
        return
      }

      const form = this.$refs.customerSettings
      form.submit()
    }
  },

  mounted () {
    this.fetchCustomerData()
  }
}
</script>
