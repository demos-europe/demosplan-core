<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-loading v-if="isLoading" />

    <template v-else>
      <dp-data-table
        class="width-100p"
        v-if="uploadedStatementFiles.length"
        :header-fields="headerFields"
        :items="uploadedStatementFiles"
        track-by="id">
        <template v-slot:header-uploadedDate="headerData">
          <div class="text--center">
            {{ headerData.label }}
          </div>
        </template>
        <template v-slot:header-status="headerData">
          <div class="text--center">
            {{ headerData.label }}
          </div>
        </template>
        <template v-slot:header-nextStep="headerData">
          <div class="text--center">
            {{ headerData.label }}
          </div>
        </template>
        <template v-slot:fileName="{ fileName }">
          <div class="o-hellip__wrapper">
            <div
              v-text="fileName"
              class="o-hellip--nowrap" />
          </div>
        </template>
        <template v-slot:uploadedDate="rowData">
          <div class="text--center">
            {{ rowData.uploadedDate }}
          </div>
        </template>
        <template v-slot:status="rowData">
          <div
            v-tooltip="Translator.trans(rowData.status.transkey)"
            class="text--center">
            <i
              v-if="rowData.status.name === 'pending' || rowData.status.name === 'reviewed'"
              class="fa fa-hourglass-half"
              aria-hidden="true" />
            <i
              v-if="rowData.status.name === 'ready_to_review' || rowData.status.name === 'ready_to_convert'"
              class="fa fa-check-circle color--grey"
              aria-hidden="true" />
            <i
              v-if="rowData.status.name === 'converted'"
              class="fa fa-check color-message-success-fill"
              aria-hidden="true" />
            <i
              v-if="rowData.status.name === 'boxes_review' || rowData.status.name === 'text_review'"
              class="fa fa-user color--grey"
              aria-hidden="true" />
          </div>
        </template>
        <template v-slot:nextStep="rowData">
          <div class="text--center">
            <a
              :href="rowData.nextStep.link"
              v-if="rowData.nextStep.link">{{ rowData.nextStep.text }}</a>
            <span v-else>-</span>
          </div>
        </template>
      </dp-data-table>
      <div v-else-if="!uploadedStatementFiles.length">
        {{ Translator.trans('files.empty') }}
      </div>
    </template>
  </div>
</template>

<script>
import {
  dpApi,
  DpDataTable,
  DpLoading,
  formatDate,
  hasOwnProp
} from '@demos-europe/demosplan-ui'

export default {
  name: 'StatementPdfImportList',

  inject: ['procedureId'],

  components: {
    DpDataTable,
    DpLoading
  },

  data () {
    return {
      headerFields: [
        { field: 'fileName', label: Translator.trans('file') },
        { field: 'uploadedDate', label: Translator.trans('date.uploaded') },
        { field: 'status', label: Translator.trans('status') },
        { field: 'nextStep', label: Translator.trans('actions') }
      ],
      isInitialFetch: true,
      isLoading: false,
      isTimerRunning: false,
      uploadedStatementFiles: []
    }
  },

  methods: {
    addUploadedStatementFiles (data) {
      this.uploadedStatementFiles = [...this.uploadedStatementFiles, ...this.prepareTableData(data)]
      this.uploadedStatementFiles = this.uploadedStatementFiles
        .sort((a, b) => this.createDateFromString(a.uploadedDate) > this.createDateFromString(b.uploadedDate) ? 1 : -1)
    },

    createDateFromString (string) {
      const [date, time] = string.split(' ')
      const [day, month, year] = date.split('.')
      const transformedDate = `${year}-${month}-${day}T${time}`
      return new Date(transformedDate)
    },

    fetchAnnotatedStatementPdf () {
      if (this.isInitialFetch) {
        this.isLoading = true
        this.isInitialFetch = false
      }
      const url = Routing.generate('api_resource_list', { resourceType: 'AnnotatedStatementPdf' })
      const params = {
        include: 'file',
        'fields[AnnotatedStatementPdf]': 'id,status,file'
      }
      dpApi.get(url, params)
        .then(response => {
          if (hasOwnProp(response.data, 'data')) {
            // Prepare date for DpDataTable
            this.setUploadedStatementFiles(response)

            if (this.isLoading) {
              this.isLoading = false
            }

            if (this.uploadedStatementFiles.filter(file => ['boxes_review', 'pending'].includes(file.status.name)).length !== 0) {
              this.startTimer()
            }
          }
        })
        .catch(e => {
          console.log(e)

          this.isLoading = false
        })
    },

    /**
     * Convert response data to format required by DpDataTable
     */
    prepareTableData (responseData) {
      return responseData.data
        .filter(annotatedStatementPdf => {
          return annotatedStatementPdf.relationships.file && annotatedStatementPdf.relationships.file.data
        })
        .map(annotatedStatementPdf => {
          const fileId = annotatedStatementPdf.relationships.file.data.id
          const file = responseData.included.find(el => el.type === 'File' && el.id === fileId)

          const statusMapping = {
            boxes_review: 'import.edited.by.user',
            text_review: 'import.edited.by.user',
            pending: 'import.process.running',
            ready_to_review: 'import.process.ready.for.review',
            reviewed: 'import.process.generating.text',
            ready_to_convert: 'import.process.ready.for.approval',
            converted: 'import.process.finished'
          }

          const nextStep = { link: '', text: '' }

          if (annotatedStatementPdf.attributes.status === 'ready_to_review') {
            nextStep.link = Routing.generate('dplan_annotated_statement_pdf_review', {
              procedureId: this.procedureId,
              documentId: annotatedStatementPdf.id
            })
            nextStep.text = Translator.trans('recheck')
          } else if (annotatedStatementPdf.attributes.status === 'ready_to_convert') {
            nextStep.link = Routing.generate('dplan_convert_annotated_pdf_to_statement', {
              procedureId: this.procedureId,
              documentId: annotatedStatementPdf.id
            })
            nextStep.text = Translator.trans('import.confirm')
          }

          return {
            uploadedDate: formatDate(file.attributes.created, 'DD.MM.YYYY HH:mm'),
            fileName: file.attributes.filename,
            status: {
              transkey: statusMapping[annotatedStatementPdf.attributes.status],
              name: annotatedStatementPdf.attributes.status
            },
            nextStep: nextStep
          }
        })
    },

    setUploadedStatementFiles (response) {
      this.uploadedStatementFiles = this.prepareTableData(response.data)
      this.uploadedStatementFiles = this.uploadedStatementFiles
        .sort((a, b) => this.createDateFromString(a.uploadedDate) > this.createDateFromString(b.uploadedDate) ? 1 : -1)
    },

    startTimer () {
      window.setTimeout(this.fetchAnnotatedStatementPdf, 5000)
      this.isTimerRunning = true
    }
  },

  mounted () {
    this.fetchAnnotatedStatementPdf()
  }
}
</script>
