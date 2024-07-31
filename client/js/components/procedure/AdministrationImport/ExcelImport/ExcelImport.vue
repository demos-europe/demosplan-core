<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="space-stack-s">
    <div>
      <template v-if="availableEntities.length > 1">
        <dp-radio
          v-for="(entity, index) in availableEntities"
          :key="`entity_type_${entity.key}`"
          :id="entity.key"
          :checked="entity.key === active"
          :data-cy="`entity_type_${index}`"
          @change="active = entity.key"
          :label="{
            text: radioLabel(entity)
          }"
          :value="entity.key" />
      </template>
      <p
        v-else
        class="weight--bold"
        v-html="radioLabel(availableEntities[0])" />
    </div>

    <form
      :action="Routing.generate(activeEntity.uploadPath, { procedureId: procedureId })"
      class="space-stack-s"
      method="post"
      enctype="multipart/form-data">
      <input
        name="_token"
        type="hidden"
        :value="csrfToken">

      <dp-upload-files
        allowed-file-types="xls"
        :basic-auth="dplan.settings.basicAuth"
        data-cy="uploadExcelFile"
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
          data-cy="statementImport"
          class="btn btn--primary">
          {{ Translator.trans('import.verb') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import { DpRadio, DpUploadFiles } from '@demos-europe/demosplan-ui'

export default {
  name: 'ExcelImport',

  inject: ['procedureId'],

  components: {
    DpRadio,
    DpUploadFiles
  },

  props: {
    csrfToken: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      active: '',
      fileIds: []
    }
  },

  computed: {
    availableEntities () {
      return [
        {
          exampleFile: '/files/statement_import_template.xlsx',
          label: 'statements.import',
          key: 'statements',
          permission: 'feature_statements_import_excel',
          uploadPath: 'DemosPlan_statement_import'
        },
        {
          exampleFile: '/files/segment_import_template.xlsx',
          label: 'segments.import',
          key: 'segments',
          permission: 'feature_segments_import_excel',
          uploadPath: 'dplan_segments_process_import'
        }
      ].filter(component => hasPermission(component.permission))
    },

    activeEntity () {
      return this.availableEntities.find(entity => entity.key === this.active)
    }
  },

  methods: {
    radioLabel (entity) {
      return `${Translator.trans(entity.label)} (<a download href="${Translator.trans(entity.exampleFile)}">${Translator.trans('example.file')}</a>)`
    },

    removeFileIds (file) {
      const fileIdx = this.fileIds.findIndex(el => el === file.hash)
      this.fileIds.splice(fileIdx, 1)
    },

    setFileIds (file) {
      this.fileIds.push(file.hash)
    }
  },

  created () {
    this.active = this.availableEntities[0].key
  }
}
</script>
