<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <form
    :action="Routing.generate('DemosPlan_user_setting_page_post')"
    data-dp-validate="customerSettings"
    ref="customerSettings"
    method="post"
    enctype="multipart/form-data">
    <dp-loading v-if="isLoading" />

    <template v-else>
      <!-- Logo and Color Variables -->
      <customer-settings-section
        v-if="hasPermission('feature_platform_logo_edit') || hasPermission('feature_customer_branding_edit')"
        is-open
        :title="Translator.trans('customer.branding.label')">
        <customer-settings-branding :branding="branding" />
      </customer-settings-section>

      <!-- Map -->
      <customer-settings-section
        v-if="hasPermission('feature_platform_public_index_map_settings')"
        is-open
        :title="Translator.trans('map.mainpage.settings')">
        <customer-settings-map
          :init-layer="initLayer"
          :init-layer-url="initLayerUrl"
          :map-attribution="mapAttribution"
          :map-extent="mapExtent" />
      </customer-settings-section>

      <!-- Imprint -->
      <customer-settings-section
        v-if="hasPermission('feature_imprint_text_customized_view')"
        :title="Translator.trans('imprint')">
        <dp-label
          for="r_imprint"
          :text="Translator.trans('customer.imprint.explanation', { url: imprintUrl })" />
        <dp-editor
          id="r_imprint"
          v-model="customer.imprint"
          hidden-input="r_imprint"
          :toolbar-items="{
            fullscreenButton: true,
            headings: [2,3,4],
            linkButton: true
          }" />
      </customer-settings-section>

      <!-- Data Protection -->
      <customer-settings-section
        v-if="hasPermission('feature_data_protection_text_customized_view')"
        :title="Translator.trans('data.protection.notes')">
        <dp-label
          for="r_dataProtection"
          :text="Translator.trans('customer.data.protection.explanation')" />
        <dp-editor
          id="r_dataProtection"
          v-model="customer.dataProtection"
          hidden-input="r_dataProtection"
          :toolbar-items="{
            fullscreenButton: true,
            headings: [2,3,4],
            linkButton: true
          }" />
      </customer-settings-section>

      <!-- Terms of use -->
      <customer-settings-section
        v-if="hasPermission('feature_customer_terms_of_use_edit')"
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
      </customer-settings-section>

      <!-- Xplanning -->
      <customer-settings-section
        v-if="hasPermission('feature_customer_xplanning_edit')"
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
      </customer-settings-section>

      <!-- Sign language video page -->
      <customer-settings-section
        v-if="hasPermission('field_sign_language_overview_video_edit')"
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
          :sign-language-overview-video="signLanguageOverviewVideo"
          @created="fetchCustomerData"
          @deleted="fetchCustomerData" />
        <dp-loading v-else />
      </customer-settings-section>

      <!-- Accessibility explanation -->
      <customer-settings-section
        v-if="hasPermission('field_customer_accessibility_explanation_edit')"
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
      </customer-settings-section>

      <customer-settings-section
        v-if="hasPermission('field_simple_language_overview_description_edit')"
        :title="Translator.trans('language.simple')">
        <dp-label
          for="r_simpleLanguage"
          :text="Translator.trans('customer.simpleLanguage.label')" />
        <dp-editor
          id="r_simpleLanguage"
          :basic-auth="dplan.settings.basicAuth"
          v-model="customer.overviewDescriptionInSimpleLanguage"
          hidden-input="r_simpleLanguage"
          :toolbar-items="{
            fullscreenButton: true,
            headings: [2,3,4],
            imageButton: true,
            linkButton: true
          }"
          :routes="{
            getFileByHash: (hash) => Routing.generate('core_file', { hash: hash })
          }"
          :tus-endpoint="dplan.paths.tusEndpoint" />
      </customer-settings-section>

      <!-- Button row -->
      <div class="text-right space-inline-s">
        <button
          type="submit"
          class="btn btn--primary"
          v-text="Translator.trans('save')"
          @click.prevent="dpValidateAction('customerSettings', submit, false)" />
        <!-- Reset link to reload the page to its initial values -->
        <a
          class="btn btn--secondary"
          :href="Routing.generate('dplan_user_customer_showSettingsPage')"
          v-text="Translator.trans('reset')" />
      </div>
    </template>
  </form>
</template>

<script>
import { dpApi, DpLabel, DpLoading, dpValidateMixin } from '@demos-europe/demosplan-ui'
import CustomerSettingsBranding from './CustomerSettingsBranding'
import CustomerSettingsSection from './CustomerSettingsSection'
import CustomerSettingsSignLanguageVideo from './CustomerSettingsSignLanguageVideo'

export default {
  name: 'CustomerSettings',

  components: {
    CustomerSettingsBranding,
    CustomerSettingsMap: () => import('./CustomerSettingsMap'),
    CustomerSettingsSection,
    CustomerSettingsSignLanguageVideo,
    DpLabel,
    DpLoading,
    DpEditor: async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')
      return DpEditor
    }
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
      branding: {
        logo: null,
        cssvars: ''
      },
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
      }
    }
  },

  computed: {
    isUnsavedSignLanguageVideo () {
      // The signLanguageOverviewVideo.file is available after the video was uploaded and the signLanguageOverviewVideo.id is only available after the video was saved
      return this.signLanguageOverviewVideo.file && !this.signLanguageOverviewVideo.id
    }
  },

  methods: {
    addAttributesToField (field, attributes) {
      this.requestFields[field] = [
        ...(this.requestFields[field] ? this.requestFields[field] : []),
        ...attributes
      ]
    },

    fetchCustomerData () {
      this.isLoadingSignLanguageOverviewVideo = true
      const payload = this.getRequestPayload()
      dpApi.get(Routing.generate('api_resource_list', { resourceType: 'Customer' }), payload, { serialize: true })
        .then((response) => this.handleCustomerResponse(response))
    },

    handleCustomerResponse (response) {
      const customer = response.data.data[0]

      // The request is filtered by currentCustomer, so we assume that exactly one customer is returned
      this.customer = { ...customer.attributes }

      if (hasPermission('feature_platform_logo_edit') || hasPermission('feature_customer_branding_edit')) {
        // Find branding relationship and set cssvars
        const brandingId = customer.relationships.branding?.data?.id
        const branding = response.data.included.find(item => item.id === brandingId)

        if (hasPermission('feature_customer_branding_edit') && typeof branding !== 'undefined') {
          this.branding.cssvars = branding.attributes.cssvars
        }

        // Find logo relationship in branding, set logoHash
        if (hasPermission('feature_platform_logo_edit') && typeof branding !== 'undefined' && branding.relationships.logo.data) {
          const logoId = branding.relationships.logo.data.id
          const logo = response.data.included.find(item => item.id === logoId)
          this.branding.logoHash = logo.attributes.hash
        }
      }

      // Find signLanguageOverviewVideo relationship, set video data
      if (hasPermission('field_sign_language_overview_video_edit')) {
        if (customer.relationships?.signLanguageOverviewVideo?.data) {
          const signLanguageOverviewVideoId = customer.relationships.signLanguageOverviewVideo.data.id
          const signLanguageOverviewVideo = response.data.included.find(item => item.id === signLanguageOverviewVideoId) || null
          const file = response.data.included.find(item => item.id === signLanguageOverviewVideo.relationships?.file.data.id) || null

          if (signLanguageOverviewVideoId && file) {
            this.signLanguageOverviewVideo = { ...signLanguageOverviewVideo.attributes }
            this.signLanguageOverviewVideo.id = signLanguageOverviewVideoId
            this.signLanguageOverviewVideo.file = file.id
            this.signLanguageOverviewVideo.mimetype = file.attributes.mimetype
          }
        } else {
          this.signLanguageOverviewVideo.description = ''
          this.signLanguageOverviewVideo.file = ''
          this.signLanguageOverviewVideo.id = null
          this.signLanguageOverviewVideo.mimetype = ''
          this.signLanguageOverviewVideo.title = ''
        }
      }

      this.isLoading = this.isLoadingSignLanguageOverviewVideo = false
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
        this.addAttributesToField('Branding', ['cssvars'])
        this.addAttributesToField('Customer', ['branding'])
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
