<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div class="space-stack-s">
    <p v-text="Translator.trans('statement.participation.import')" />

    <form
      :action="Routing.generate('DemosPlan_statement_participation_import', { procedureId: procedureId })"
      class="space-stack-s"
      method="post"
      enctype="multipart/form-data">
      <dp-upload-files
        allowed-file-types="xls"
        :basic-auth="dplan.settings.basicAuth"
        :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedureId })"
        :max-file-size="100 * 1024 * 1024/* 100 MiB */"
        needs-hidden-input
        :translations="{ dropHereOr: Translator.trans('form.button.upload.file.allowed.formats', { browse: '{browse}', allowedFormats: '.xls, .xlsx, .ods', maxUploadSize: '100 MB' }) }"
        :tus-endpoint="dplan.paths.tusEndpoint"
        @file-remove="removeFileIds"
        @upload-success="setFileIds" />
      <div class="text-right">
        <button
          :disabled="fileIds.length === 0"
          type="submit"
          class="btn btn--primary">
          {{ Translator.trans('import.verb') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import { DpUploadFiles } from '@demos-europe/demosplan-ui'

export default {
  name: 'ParticipationImport',

  inject: ['procedureId'],

  components: {
    DpUploadFiles
  },

  data () {
    return {
      active: '',
      fileIds: []
    }
  },

  methods: {
    removeFileIds (file) {
      const fileIdx = this.fileIds.findIndex(el => el === file.hash)
      this.fileIds.splice(fileIdx, 1)
    },

    setFileIds (file) {
      this.fileIds.push(file.hash)
    }
  }
}
</script>
