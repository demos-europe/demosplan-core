<template>
  <div class="u-mb-0_75">
    <dp-label
      :for="elementId"
      :text="Translator.trans('plugin.bimschgantrag.procedurename.extract')" />

    <dp-upload-files
      :id="elementId"
      :allow-multiple-uploads="false"
      allowed-file-types="antragx"
      :chunk-size="0"
      name="r_antragx"
      :max-file-size="100 * 1024 * 1024/* 100 MiB */"
      :max-number-of-files="1"
      needs-hidden-input
      :required="false"
      :translations="{ dropHereOr: Translator.trans('form.button.upload.antragx', { browse: '{browse}', maxUploadSize: '100 MB' } )}" />
  </div>
</template>
<script>
import { DpLabel, DpUploadFiles } from '@demos-europe/demosplan-ui'
import { v4 as uuid } from 'uuid'

export default {
  name: 'BimschgAntrag',

  components: {
    DpLabel,
    DpUploadFiles
  },

  data () {
    return {
      elementId: 'r_antragx' + uuid()
    }
  }
}
</script>
