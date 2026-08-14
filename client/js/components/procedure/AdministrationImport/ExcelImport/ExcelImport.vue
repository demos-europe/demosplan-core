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
        @file-remove="unsetUploadedFileName"
        @upload-success="setUploadedFileName"
      />
      <div class="text-right">
        <button
          :disabled="uploadedFileName === ''"
          type="submit"
          data-cy="statementImport"
          class="btn btn--primary"
        >
          {{ Translator.trans('import.verb') }}
        </button>
      </div>
    </form>

    <!-- Import Jobs List -->
    <div
      v-if="hasPermission('area_statement_segmentation')"
      class="u-mt-2"
    >
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
      uploadedFileName: '',
    }
  },

  computed: {
    availableEntities () {
      return [
        {
          label: 'statements.import',
          key: 'statements',
          permissions: ['feature_statements_import_excel', 'feature_statements_import_csv'],
          csvUploadPath: 'dplan_statement_import_csv',
          excelUploadPath: 'DemosPlan_statement_import',
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
        },
        {
          label: 'segments.import',
          key: 'segments',
          permissions: ['feature_segments_import_excel'],
          excelUploadPath: 'dplan_segments_process_import',
          exampleFiles: [
            {
              label: 'import.example.file.excel',
              path: '/files/segment_import_template.xlsx',
              permission: 'feature_segments_import_excel',
            },
          ],
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
      const isCsvImport = this.active === 'statements' && this.isCsv(this.uploadedFileName)
      const path = isCsvImport ? this.activeEntity.csvUploadPath : this.activeEntity.excelUploadPath

      return Routing.generate(path, { procedureId: this.procedureId })
    },

    importJobsUrl () {
      return Routing.generate('dplan_import_jobs_api', { procedureId: this.procedureId })
    },
  },

  methods: {
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

    isCsv (fileName) {
      const lowerCaseFileName = fileName.toLowerCase()

      return getFileTypes('csv').some(fileType => lowerCaseFileName.endsWith(fileType))
    },

    radioLabel (entity) {
      return `${Translator.trans(entity.label)} (${this.exampleFileLinks(entity)})`
    },

    /**
     * The entities accept different file types and are submitted to different routes, so an already
     * uploaded file must not be carried over when the user switches between them.
     */
    setActive (key) {
      this.active = key
      this.uploadedFileName = ''
      this.clearAllFiles = true
      this.$nextTick(() => {
        this.clearAllFiles = false
      })
    },

    /**
     * The uploader holds a single file, so uploading another one replaces it without emitting
     * `file-remove`. Tracking the name of that one file keeps this component in sync with it.
     */
    setUploadedFileName (file) {
      this.uploadedFileName = file.name
    },

    unsetUploadedFileName () {
      this.uploadedFileName = ''
    },
  },

  created () {
    this.active = this.availableEntities[0].key
  },
}
</script>
