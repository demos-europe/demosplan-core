<template>
  <div>
    <form
      :action="Routing.generate('DemosPlan_statement_administration_tags_csv_import', { procedureId: this.procedureId })"
      method="POST"
      name="tag_edit">

      <!-- csrf token -->
      <input
        name="_token"
        type="hidden"
        :value="dplan.csrfToken">

      <fieldset class="flow-root pb-1">
        <dp-label
          :text="Translator.trans('tags.import')"
          for="uploadTags"
          :hint="Translator.trans('tags.import.hint')"
          :tooltip="Translator.trans('tags.import.help')" />
        <a download :href="availableEntity.exampleFile" target="_blank">{{ Translator.trans('example.file') }}</a>
        <dp-upload
          id="uploadTags"
          name="r_importCsv"
          allowed-file-types="csv"
          :basic-auth="dplan.settings.basicAuth"
          :tus-endpoint="dplan.paths.tusEndpoint"
          :translations="{ dropHereOr: Translator.trans('form.button.upload.csv', { browse: '{browse}', maxUploadSize: '10GB' }) }"
          :max-number-of-files="1"
          @upload-success="importCSVs" />
        <input
          type="hidden"
          name="r_importCsv"
          :value="this.uploadedCSV" />
        <input
          type="hidden"
          name="uploadedFiles"
          :value="this.uploadedFiles" />
        <dp-button
          class="float-right mt-1"
          data-cy="listTags:tagsImport"
          name="r_import"
          :text="Translator.trans('tags.import')"
          type="submit" />
      </fieldset>
    </form>
  </div>
</template>

<script>
import { DpButton, DpLabel, DpUpload } from '@demos-europe/demosplan-ui'
export default {
  name: 'TagsImportForm',

  components: {
    DpButton,
    DpLabel,
    DpUpload
  },

  props: {
    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      uploadedCSV: null,
      uploadedFiles: ''
    }
  },

  computed: {
    availableEntity () {
      return {
        exampleFile: '/files/Beispieldatei_Schlagwortimport.csv'
      }
    }
  },

  methods: {
    importCSVs (file) {
      this.uploadedCSV = Object.values(file).join()
      this.uploadedFiles = file.hash
    }
  }
}
</script>
