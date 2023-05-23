<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="space-stack-s">
    <h2
      class="font-size-large"
      v-text="Translator.trans('import.statements.fromPdf.label', { maxFiles: maxNumberOfFiles })" />

    <dp-upload-files
      ref="uploader"
      id="statementUpload"
      :get-file-by-hash="(hash) => Routing.generate('core_file', { hash: hash })"
      allowed-file-types="pdf"
      :max-file-size="100000000"
      :max-number-of-files="maxNumberOfFiles"
      @file-remove="removeFileHashes"
      @upload-success="setFileHashes" />

    <div class="text--right">
      <dp-button
        @click="createAnnotatedStatementPdf"
        data-cy="pdfImport"
        :busy="isProcessing"
        :disabled="fileHashes.length === 0"
        :text="Translator.trans('import.verb')" />
    </div>

    <h2
      class="font-size-large"
      v-text="Translator.trans('uploaded.files')" />

    <statement-pdf-import-list ref="statementPdfImportList" />
  </div>
</template>

<script>
import { dpApi, DpButton, DpUploadFiles, getFileIdsByHash, handleResponseMessages } from '@demos-europe/demosplan-ui'
import StatementPdfImportList from './StatementPdfImportList'

export default {
  name: 'StatementPdfImport',

  inject: ['procedureId'],

  components: {
    DpButton,
    DpUploadFiles,
    StatementPdfImportList
  },

  data () {
    return {
      fileHashes: [],
      isProcessing: false,
      maxNumberOfFiles: 100
    }
  },

  methods: {
    async createAnnotatedStatementPdf () {
      if (this.fileHashes.length) {
        const ids = await getFileIdsByHash(this.fileHashes, Routing.generate('api_resource_list', { resourceType: 'File' }))

        this.isProcessing = true
        const uploadPromises = ids.map(id => {
          const resource = JSON.parse(JSON.stringify(this.prepareResourceData(id)))
          const url = Routing.generate('api_resource_create', { resourceType: 'AnnotatedStatementPdf' })
          return dpApi.post(url, {}, resource)
        })

        Promise.allSettled(uploadPromises)
          .then((promises) => {
            const resolvedPromises = promises.filter(promise => promise.status === 'fulfilled')
            const rejectedPromises = promises.filter(promise => promise.status === 'rejected')

            if (rejectedPromises.length > 0) {
              rejectedPromises.forEach(rejected => {
                handleResponseMessages(rejected.reason.response.data.meta)
              })
            }

            if (resolvedPromises.length > 0) {
              dplan.notify.notify('confirm', Translator.trans('info.annotatedstatementpdf.created.multiple', { count: resolvedPromises.length }))
              const ids = resolvedPromises.map(resolved => resolved.value.data.data.id)
              this.fetchUploadedStatements(ids)
            }

            this.$refs.uploader.clearFilesList()
            this.fileHashes = []
          })
          .catch(err => {
            console.error(err)
          })
          .then(() => {
            this.isProcessing = false
          })
      }
    },

    fetchUploadedStatements (ids) {
      const url = Routing.generate('api_resource_list', { procedureId: this.procedureId, resourceType: 'AnnotatedStatementPdf' })
      const params = {
        include: 'file',
        'fields[AnnotatedStatementPdf]': 'id,status,file',
        filter: {
          annotatedStatementPdf: {
            condition: {
              path: 'id',
              value: ids,
              operator: 'IN'
            }
          }
        }
      }

      return dpApi.get(url, params, { serialize: true })
        .then(response => {
          this.$refs.statementPdfImportList.addUploadedStatementFiles(response.data)

          if (!this.$refs.statementPdfImportList.isTimerRunning) {
            this.$refs.statementPdfImportList.startTimer()
          }
        })
    },

    /**
     * Prepare payload for create request
     * @param id
     * @return {{data: {relationships: {file: {data: {id: *, type: string}}, annotatedStatementPdfPages: [], procedure: {data: {id: string, type: string}}}, attributes: {}, type: string}}}
     */
    prepareResourceData (id) {
      return {
        data: {
          attributes: {},
          type: 'AnnotatedStatementPdf',
          relationships: {
            annotatedStatementPdfPages: {
              data: []
            },
            file: {
              data: {
                id: id,
                type: 'File'
              }
            },
            procedure: {
              data: {
                id: this.procedureId,
                type: 'Procedure'
              }
            }
          }
        }
      }
    },

    removeFileHashes (file) {
      const fileIdx = this.fileHashes.findIndex(el => el === file.hash)
      this.fileHashes.splice(fileIdx, 1)
    },

    setFileHashes (file) {
      this.fileHashes.push(file.hash)
    }
  }
}
</script>
