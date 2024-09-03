<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="layout space-stack-m">
    <template v-if="hasPermission('feature_platform_logo_edit')">
      <div class="layout__item u-1-of-1">
        <h3 class="u-mb-0">
          {{ Translator.trans('customer.branding.logo') }}
        </h3>
        <p class="lbl__hint">
          {{ Translator.trans('customer.branding.logo.hint') }}
        </p>
      </div>
      <div class="layout__item u-1-of-2">
        <dp-label
          :text="Translator.trans('logo.upload.new')"
          :hint="Translator.trans('explanation.upload.logo.dimensions')"
          for="r_customerLogo" />
        <dp-upload-files
          ref="logoUpload"
          allowed-file-types="img"
          id="r_customerLogo"
          :basic-auth="dplan.settings.basicAuth"
          :get-file-by-hash="hash => Routing.generate('core_file', { hash: hash })"
          :max-file-size="200000"
          :max-number-of-files="1"
          needs-hidden-input
          name="r_customerLogo"
          :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '200 KB' }) }"
          :tus-endpoint="dplan.paths.tusEndpoint"
          @file-remove="unsetFile"
          @upload-success="setFile" />
      </div><!--
   --><div
        class="layout__item u-1-of-2"
        v-if="branding.logoHash">
        <p
          class="weight--bold"
          v-text="Translator.trans('logo.current')" />
        <img
          :src="Routing.generate('core_logo', { hash: branding.logoHash })"
          :alt="Translator.trans('logo.alt.customer')"
          style="max-width: 300px">
        <dp-button
          class="mt-2"
          data-cy="customerBranding:deleteLogo"
          :text="Translator.trans('logo.delete')"
          variant="outline"
          @click.prevent="deleteLogo" />
      </div>
    </template>
    <div
      class="layout__item u-1-of-1"
      v-if="hasPermission('feature_customer_branding_edit')">
      <dp-text-area
        :hint="Translator.trans('branding.styling.hint')"
        id="r_cssvars"
        name="r_cssvars"
        data-cy="customerSettingsBranding:brandingStylingInput"
        :label="Translator.trans('branding.styling.input')"
        reduced-height
        :value="branding.cssvars" />
      <dp-details
        :summary="Translator.trans('branding.styling.details')"
        data-cy="customerSettingsBranding:brandingStylingDetails">
        <span
          v-html="Translator.trans('branding.styling.details.description')"
          data-cy="customerSettingsBranding:brandingStylingDetailsDescription"/>
      </dp-details>
    </div>
    <dp-button-row
      class="layout__item u-1-of-1"
      data-cy="customerSettingsBranding"
      primary
      :busy="isBusy"
      @primary-action="saveBrandingSettings" />
  </div>
</template>

<script>
import { DpButton, DpButtonRow, DpDetails, DpLabel, DpTextArea, DpUploadFiles } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'

export default {
  name: 'CustomerSettingsBranding',

  components: {
    DpButton,
    DpButtonRow,
    DpDetails,
    DpLabel,
    DpTextArea,
    DpUploadFiles
  },

  props: {
    branding: {
      required: true,
      type: Object
    },

    brandingId: {
      required: true,
      type: String
    }
  },

  data () {
    return {
      isBusy: false,
      uploadedFileId: '',
    }
  },

  computed: {
    ...mapState('Branding', {
      brandingList: 'items'
    })
  },

  methods: {
    ...mapActions('Branding', {
      fetchBranding: 'list',
      saveBranding: 'save'
    }),

    ...mapMutations('Branding', {
      updateBranding: 'setItem'
    }),

    ...mapMutations('File', {
      updateFile: 'setItem'
    }),

    deleteLogo () {
      if (!dpconfirm(Translator.trans('check.item.delete'))) {
        return false
      }

      const payload = {
        id: this.brandingId,
        type: 'Branding',
        attributes: {
          ...this.brandingList[this.brandingId].attributes
        },
        relationships: {
          logo: {
            data: null
          }
        }
      }
      this.updateBranding(payload)
      this.saveBranding(this.brandingId).then(() => {
        dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        this.branding.logoHash = null
      })
    },

    setFile (file) {
      this.updateFile({ id: file.fileId, attributes: { hash: file.hash }})
      this.uploadedFileId = file.fileId
    },

    saveBrandingSettings () {
      if (!this.uploadedFileId) {
        this.isBusy = false
        return
      }

      this.isBusy = true
      const payload = {
        id: this.brandingId,
        type: 'Branding',
        attributes: {
          ...this.brandingList[this.brandingId].attributes
        },
        relationships: {
          logo: {
            data: { id: this.uploadedFileId, type: 'File' }
          }
        }
      }

      this.updateBranding(payload)
      this.saveBranding(this.brandingId).then(() => {
        dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        this.isBusy = false
        this.branding.logoHash = this.brandingList[this.brandingId].relationships?.logo?.data.id
        this.unsetFile()
        this.$refs.logoUpload.clearFilesList()
      })
    },

    unsetFile () {
      this.updateFile({ id: null, attributes: { hash: null }})
      this.uploadedFileId = null
    }
  }
}
</script>
