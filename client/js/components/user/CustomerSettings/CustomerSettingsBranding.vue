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
          :get-file-by-hash="hash => Routing.generate('core_file', { hash: hash })"
          :max-file-size="200000"
          :max-number-of-files="1"
          :basic-auth="dplan.settings.basicAuth"
          :upload-post="dplan.paths.uploadPost"
          needs-hidden-input
          name="r_customerLogo"
          :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '200 KB' }) }" />
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
          style="max-width: 300px;">
        <dp-checkbox
          id="r_logoDelete"
          :label="{
            bold: true,
            text: Translator.trans('logo.delete')
          }"
          name="r_logoDelete"
          value-to-send="deleteLogo" />
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
        :value="branding.cssvars" />
      <dp-details :summary="Translator.trans('branding.styling.details')">
        <span v-html="Translator.trans('branding.styling.details.description')" />
      </dp-details>
    </div>
  </div>
</template>

<script>
import { DpCheckbox, DpDetails, DpLabel, DpTextArea, DpUploadFiles } from '@demos-europe/demosplan-ui'

export default {
  name: 'CustomerSettingsBranding',

  components: {
    DpCheckbox,
    DpDetails,
    DpLabel,
    DpTextArea,
    DpUploadFiles
  },

  props: {
    branding: {
      required: true,
      type: Object
    }
  }
}
</script>
