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
          :id="entity.key"
          :key="`entity_type_${entity.key}`"
          :checked="entity.key === active"
          :data-cy="`entity_type_${index}`"
          :label="{
            text: radioLabel(entity)
          }"
          :value="entity.key"
          @change="setActive(entity.key)"
        />
      </template>
      <p
        v-else
        class="weight--bold"
        v-html="radioLabel(availableEntities[0])"
      />
    </div>

    <form
      :action="formAction"
      class="space-stack-s"
      method="post"
      enctype="multipart/form-data"
    >
      <input
        name="_token"
        type="hidden"
        :value="csrfToken"
      >

      <dp-upload-files
        :allowed-file-types="allowedFileTypes"
        :clear-all-files="clearAllFiles"
        data-cy="uploadExcelFile"
        :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedureId })"
        :max-file-size="100 * 1024 * 1024/* 100 MiB */"
        needs-hidden-input
        :translations="{ dropHereOr: Translator.trans('form.button.upload.file.allowed.formats', { browse: '{browse}', allowedFormats: allowedFileTypes.join(', '), maxUploadSize: '100 MB' }) }"
        :tus-endpoint="dplan.paths.tusEndpoint"
        @file-remove="removeFile"
        @upload-success="addFile"
      />
      <p
        v-if="hasMixedFileTypes"
        class="color-message-severe-text"
        data-cy="mixedFileTypesHint"
      >
        {{ Translator.trans('statements.import.csv.error.mixed.formats') }}
      </p>
      <div class="text-right">
        <button
          :disabled="files.length === 0 || hasMixedFileTypes"
          type="submit"
          data-cy="statementImport"
          class="btn btn--primary"
        >
          {{ Translator.trans('import.verb') }}
        </button>
      </div>
    </form>

    <!-- Import Jobs List -->
    <div class="u-mt-2">
      <h2>{{ Translator.trans('import.jobs.list') }}</h2>
      <segment-import-job-list
        :init-url="importJobsUrl"
        :procedure-id="procedureId"
      />
    </div>
  </div>
</template>

<script>
import { DpRadio, DpUploadFiles, getFileTypes, hasAnyPermissions } from '@demos-europe/demosplan-ui'
import SegmentImportJobList from '../SegmentImportJobList'

export default {
  name: 'ExcelImport',

  inject: ['procedureId'],

  components: {
    DpRadio,
    DpUploadFiles,
    SegmentImportJobList,
  },

  props: {
    csrfToken: {
      type: String,
      required: true,
    },
  },

  data () {
    return {
      active: '',
      clearAllFiles: false,
      files: [],
    }
  },

  computed: {
    availableEntities () {
      return [
        {
          exampleFiles: [
            {
              label: 'import.example.file.excel',
              path: '/files/statement_import_template.xlsx',
              permission: 'feature_statements_import_excel',
            },
            {
              label: 'import.example.file.csv',
              path: '/files/statement_import_template.csv',
              permission: 'feature_statements_import_csv',
            },
          ],
          label: 'statements.import',
          key: 'statements',
          permissions: ['feature_statements_import_excel', 'feature_statements_import_csv'],
          uploadPath: 'DemosPlan_statement_import',
        },
        {
          exampleFiles: [
            {
              label: 'example.file',
              path: '/files/segment_import_template.xlsx',
              permission: 'feature_segments_import_excel',
            },
          ],
          label: 'segments.import',
          key: 'segments',
          permissions: ['feature_segments_import_excel'],
          uploadPath: 'dplan_segments_process_import',
        },
      ].filter(entity => hasAnyPermissions(entity.permissions))
    },

    activeEntity () {
      return this.availableEntities.find(entity => entity.key === this.active)
    },

    /**
     * Statements may additionally be imported as csv, segments may not.
     */
    allowedFileTypes () {
      const csvAllowed = this.active === 'statements' && hasPermission('feature_statements_import_csv')

      return csvAllowed ? [...getFileTypes('xls'), ...getFileTypes('csv')] : getFileTypes('xls')
    },

    /**
     * Csv files are imported as a background job by a route of their own, spreadsheets are not.
     */
    formAction () {
      const isCsvImport = this.active === 'statements' && this.hasOnlyCsvFiles
      const path = isCsvImport ? 'dplan_statement_import_csv' : this.activeEntity.uploadPath

      return Routing.generate(path, { procedureId: this.procedureId })
    },

    hasOnlyCsvFiles () {
      return this.files.length > 0 && this.files.every(file => this.isCsv(file))
    },

    /**
     * Csv and spreadsheet uploads are handled by different routes, so they cannot be submitted together.
     */
    hasMixedFileTypes () {
      return this.files.some(file => this.isCsv(file)) && this.files.some(file => !this.isCsv(file))
    },

    importJobsUrl () {
      return Routing.generate('dplan_import_jobs_api', { procedureId: this.procedureId })
    },
  },

  methods: {
    addFile (file) {
      this.files.push(file)
    },

    /**
     * The file name is set explicitly so the browser does not derive it from the response, which
     * would append the wrong extension if the server does not know the mime type of the file.
     */
    exampleFileLinks (entity) {
      return entity.exampleFiles
        .filter(exampleFile => hasPermission(exampleFile.permission))
        .map(exampleFile => {
          const fileName = exampleFile.path.split('/').pop()

          return `<a download="${fileName}" href="${exampleFile.path}">${Translator.trans(exampleFile.label)}</a>`
        })
        .join(', ')
    },

    isCsv (file) {
      const fileName = (file.name || '').toLowerCase()

      return getFileTypes('csv').some(fileType => fileName.endsWith(fileType))
    },

    radioLabel (entity) {
      return `${Translator.trans(entity.label)} (${this.exampleFileLinks(entity)})`
    },

    removeFile (file) {
      const fileIdx = this.files.findIndex(el => el.hash === file.hash)

      if (fileIdx > -1) {
        this.files.splice(fileIdx, 1)
      }
    },

    /**
     * The entities accept different file types and are submitted to different routes, so already
     * uploaded files must not be carried over when the user switches between them.
     */
    setActive (key) {
      this.active = key
      this.files = []
      this.clearAllFiles = true
      this.$nextTick(() => {
        this.clearAllFiles = false
      })
    },
  },

  created () {
    this.active = this.availableEntities[0].key
  },
}
</script>
