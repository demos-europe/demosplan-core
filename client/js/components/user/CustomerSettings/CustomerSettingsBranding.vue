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
        v-if="uploadedFileId && uploadedFileId !== ''">
        <p
          class="weight--bold"
          v-text="Translator.trans('logo.current')" />
        <img
          :src="Routing.generate('core_logo', { hash: uploadedFileId })"
          :alt="Translator.trans('logo.alt.customer')"
          style="max-width: 300px">
        <dp-checkbox
          id="r_logoDelete"
          class="mb-1"
          v-model="isLogoDeletable"
          :label="{
            bold: true,
            text: Translator.trans('logo.delete')
          }"
          name="r_logoDelete" />
      </div>
    </template>
    <div
      class="layout__item u-1-of-1"
      v-if="hasPermission('feature_customer_branding_edit')">
      <dp-text-area
        :hint="Translator.trans('branding.styling.hint')"
        id="r_cssvars"
        name="r_cssvars"
        :label="Translator.trans('branding.styling.input')"
        reduced-height
        :value="branding.styling"
        @input="branding = { key: 'styling', value: $event }"
      />
      <dp-details :summary="Translator.trans('branding.styling.details')">
        <span v-html="Translator.trans('branding.styling.details.description')" />
      </dp-details>
    </div>
    <dp-button-row
      class="layout__item u-1-of-1"
      primary
      :busy="isBusy"
      @primary-action="saveBrandingSettings" />
  </div>
</template>

<script>
import { DpButtonRow, DpCheckbox, DpDetails, DpLabel, DpTextArea, DpUploadFiles } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'

export default {
  name: 'CustomerSettingsBranding',

  components: {
    DpButtonRow,
    DpCheckbox,
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
      isLogoDeletable: false,
      isBusy: false,
      uploadedFileId: null
    }
  },

  computed: {
    ...mapState('branding', {
      brandingList: 'items'
    }),

    ...mapState('file', {
      fileList: 'item'
    }),


    branding: {
      get () {
        return this.brandingList[this.brandingId].attributes || { styling: '', logoHash: null }
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
    ...mapActions('branding', {
      fetchBranding: 'list',
      saveBranding: 'save'
    }),

    ...mapMutations('branding', {
      updateBranding: 'setItem'
    }),

    ...mapMutations('file', {
      updateFile: 'setItem'
    }),

    setFile (file) {
      this.branding = { key: 'logoHash', value: file.hash }
      this.updateFile({ id: file.fileId, attributes: { hash: file.hash } })
      console.log('file', file)
      this.uploadedFileId = file.fileId

      console.log('file', file, this.uploadedFileId)
    },

    saveBrandingSettings () {
      const payload = {
        id: this.brandingId,
        type: 'Branding',
        attributes: {
          styling: this.brandingList[this.brandingId].attributes.styling
        }
      }

      this.isBusy = true

      if (this.uploadedFileId || this.isLogoDeletable) {
        payload.relationships = {
          logo: {
            data: this.isLogoDeletable ? null : { id: this.uploadedFileId, type: 'file' }
          }
        }
      }

      this.updateBranding(payload)
      console.log('payload', payload)
      console.log('branding', this.brandingList[this.brandingId])
      this.saveBranding(this.brandingId).then(() => {
        dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        this.isBusy = false
        this.isLogoDeletable = false
        this.updateBranding(payload)

        if (payload.relationships?.logo?.data === null) {
          this.unsetFile({ fileId: this.uploadedFileId })
        }
      })
    },

    unsetFile (file) {
      this.updateFile({ id: file.fileId, attributes: { hash: null } })
      this.uploadedFileId = null
    }
  },
  mounted () {
    const file = this.brandingList[this.brandingId].relationships?.logo?.data?.id ?? null
    this.uploadedFileId = file ? this.fileList[file].id : null
  }
}
</script>
