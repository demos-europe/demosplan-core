<template>
  <div>
    <form
      :action="Routing.generate('DemosPlan_statement_administration_tags_edit', { procedure: this.procedureId })"
      method="POST"
      name="tag_edit">

      <!-- csrf token -->
      <input
        name="_token"
        type="hidden"
        :value="dplan.csrfToken">

      <fieldset class="flow-root pb-1 mt-2">
        <dp-contextual-help
          class="float-right"
          :text="Translator.trans('tags.import.help')"
        ></dp-contextual-help>

        <dp-upload
          allowed-file-types="csv"
          :basic-auth="dplan.settings.basicAuth"
          :tus-endpoint="dplan.paths.tusEndpoint"
          :translations="{ dropHereOr: Translator.trans('form.button.upload.csv', { browse: '{browse}', maxUploadSize: '10GB' }) }"
          :max-number-of-files="1"
          @upload-success="importCSVs" />

        <input type="hidden" name="r_importCsv" :value="this.uploadedCSV" />
        <input type="hidden" name="uploadedFiles" :value="this.uploadedFiles.join()" />
        <dp-button
          data-cy="listTags:tagsImport"
          type="submit"
          name="r_import"
          class="float-right mt-1">
          {{ Translator.trans('tags.import') }}
        </dp-button>

      </fieldset>
    </form>
  </div>
</template>

<script>
import { DpButton, DpContextualHelp, DpInput, DpUpload } from '@demos-europe/demosplan-ui'
export default {
  name: 'TagsImportForm',

  components: {
    DpButton,
    DpContextualHelp,
    DpInput,
    DpUpload
  },

  inject: ['topics', 'procedureId'],

  data () {
    return {
      uploadedCSV: null,
      uploadedFiles: []
    }
  },

  methods: {
    importCSVs (file) {
      this.uploadedCSV = Object.values(file).join()
      this.uploadedFiles.push(file.hash)
    }
  }
}
</script>
