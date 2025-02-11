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
          :hint="Translator.trans('explanation.upload.logo.dimensions')"
          :text="Translator.trans('logo.upload.new')"
          for="r_customerLogo" />
        <dp-upload-files
          id="r_customerLogo"
          ref="logoUpload"
          allowed-file-types="img"
          :basic-auth="dplan.settings.basicAuth"
          :get-file-by-hash="hash => Routing.generate('core_file', { hash: hash })"
          :max-file-size="200000"
          :max-number-of-files="1"
          name="r_customerLogo"
          needs-hidden-input
          :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '200 KB' }) }"
          :tus-endpoint="dplan.paths.tusEndpoint"
          @file-remove="unsetFile"
          @upload-success="setFile" />
      </div>
      <div
        v-if="uploadedFileId && uploadedFileId !== ''"
        class="layout__item u-1-of-2">
        <p
          class="weight--bold"
          v-text="Translator.trans('logo.current')" />
        <img
          :alt="Translator.trans('logo.alt.customer')"
          :src="Routing.generate('core_logo', { hash: uploadedFileId })"
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
      v-if="hasPermission('feature_customer_branding_edit')"
      class="layout__item u-1-of-1">
      <dp-text-area
        id="r_styling"
        data-cy="customerSettingsBranding:brandingStylingInput"
        :hint="Translator.trans('branding.styling.hint')"
        :label="Translator.trans('branding.styling.input')"
        name="r_styling"
        reduced-height
        :value="branding.styling"
        @input="branding = { key: 'styling', value: $event }" />
      <dp-details
        data-cy="customerSettingsBranding:brandingStylingDetails"
        :summary="Translator.trans('branding.styling.details')">
        <span
          data-cy="customerSettingsBranding:brandingStylingDetailsDescription"
          v-html="Translator.trans('branding.styling.details.description')" />
      </dp-details>
    </div>
    <dp-button-row
      :busy="isBusy"
      class="layout__item u-1-of-1"
      data-cy="customerSettingsBranding"
      primary
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
    brandingId: {
      required: true,
      type: String
    }
  },

  data () {
    return {
      isBusy: false,
      uploadedFileId: null
    }
  },

  computed: {
    ...mapState('Branding', {
      brandingList: 'items'
    }),

    ...mapState('File', {
      fileList: 'items'
    }),

    branding: {
      get () {
        return this.brandingList[this.brandingId].attributes || { styling: '' }
      },
      set ({ key, value }) {
        this.updateBranding({
          ...this.brandingList[this.brandingId],
          attributes: {
            ...this.brandingList[this.brandingId].attributes,
            [key]: value
          }
        })
      }
    }
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
          ...this.branding
        },
        relationships: {
          logo: {
            data: null
          }
        }
      }
      this.updateBranding(payload)
      this.saveBranding(this.brandingId).then(() => {
        this.unsetFile({ fileId: this.uploadedFileId })
        dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
      })
    },

    setFile (file) {
      this.updateFile({ id: file.fileId, attributes: { hash: file.hash } })
      this.uploadedFileId = file.fileId
    },

    saveBrandingSettings () {
      if (!this.uploadedFileId && !hasPermission('feature_customer_branding_edit')) {
        this.isBusy = false

        return
      }

      this.isBusy = true

      const payload = {
        id: this.brandingId,
        type: 'Branding',
        attributes: {
          ...this.brandingList[this.brandingId].attributes
        }
      }

      if (this.uploadedFileId || this.isLogoDeletable) {
        payload.relationships = {
          logo: {
            data: this.isLogoDeletable ? null : { id: this.uploadedFileId, type: 'File' }
          }
        }
      }

      this.updateBranding(payload)
      this.saveBranding(this.brandingId).then(() => {
        dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        this.isBusy = false
        this.isLogoDeletable = false

        if (payload.relationships?.logo?.data === null) {
          this.unsetFile({ fileId: this.uploadedFileId })
        }
      })
    },

    unsetFile () {
      this.updateFile({ id: null, attributes: { hash: null } })
      this.uploadedFileId = null
    }
  },
  mounted () {
    const file = this.brandingList[this.brandingId].relationships?.logo?.data?.id ?? null
    this.uploadedFileId = file ? this.fileList[file].id : null
  }
}
</script>
