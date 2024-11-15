<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    class="convert-annotated-pdf"
    ref="container">
    <div
      class="column column--big"
      ref="leftColumn"
      :style="largeColumnStyle">
      <dp-loading v-if="isLoading" />
      <div
        v-else
        class="convert-annotated-pdf__preview">
        <img
          v-for="(page, idx) in pages"
          :key="`page_${idx}`"
          alt=""
          :src="page.attributes.url">
      </div>
    </div>
    <div
      class="column column--small"
      :style="smallColumnStyle">
      <div
        class="resize-handle"
        @mousedown="startResize">
        <button
          class="resize-handle__button"
          :title="Translator.trans('drag.adjust.width')">
          <i
            class="fa fa-arrows-h"
            aria-hidden="true" />
        </button>
      </div>
      <div class="convert-annotated-pdf__form">
        <dp-simplified-new-statement-form
          ref="annotatedPdfForm"
          :allow-file-upload="false"
          :csrf-token="csrfToken"
          :current-procedure-phase="currentProcedurePhase"
          :document-id="documentId"
          :expand-all="false"
          fields-full-width
          :newest-intern-id="newestInternId"
          :procedure-id="procedureId"
          :submit-type-options="submitTypeOptions"
          :tags="tags"
          :used-intern-ids="usedInternIds"
          :init-values="formValues"
          submit-route-name="dplan_pdf_import_to_statement">
          <div class="flex justify-end mt-2">
            <dp-button
              :text="Translator.trans('statement.save.quickSave')"
              @click="quickSaveText" />
          </div>
        </dp-simplified-new-statement-form>
      </div>
    </div>
    <dp-send-beacon
      :url="Routing.generate('dplan_annotated_statement_pdf_pause_text_review', {
        documentId: documentId, procedureId: procedureId
      })" />
  </div>
</template>

<script>
import { dpApi, DpButton, DpLoading } from '@demos-europe/demosplan-ui'
import DpSendBeacon from './DpSendBeacon'
import DpSimplifiedNewStatementForm from '@DpJs/components/procedure/DpSimplifiedNewStatementForm'

export default {
  name: 'DpConvertAnnotatedPdf',

  components: {
    DpButton,
    DpLoading,
    DpSendBeacon,
    DpSimplifiedNewStatementForm
  },

  props: {
    csrfToken: {
      type: String,
      required: true
    },

    currentProcedurePhase: {
      type: String,
      required: false,
      default: 'analysis'
    },

    documentId: {
      type: String,
      required: true
    },

    newestInternId: {
      type: String,
      required: false,
      default: '-'
    },

    procedureId: {
      type: String,
      required: true
    },

    submitTypeOptions: {
      type: Array,
      required: false,
      default: () => []
    },

    tags: {
      type: Array,
      required: false,
      default: () => []
    },

    usedInternIds: {
      type: Array,
      required: false,
      default: () => []
    },

    initSubmitter: {
      type: Object,
      required: false,
      default: () => ({})
    }
  },

  data () {
    return {
      currentSmallColumnWidth: 33.3,
      cursorStart: 0,
      document: null,
      formValues: {
        authoredDate: '',
        quickSave: '',
        submitter: { ...this.initSubmitter },
        submittedDate: '',
        tags: [],
        text: ''
      },
      isLoading: false,
      largeColumnWidth: 66.6,
      onePixelInPercent: 0,
      pages: [],
      smallColumnWidth: 33.3
    }
  },

  computed: {
    internIdsPattern () {
      return this.usedInternIds.length > 0 ? '^(?!(?:' + this.usedInternIds.join('|') + '+)$)[0-9a-zA-Z-_ ]{1,}$' : '^[0-9a-zA-Z-_ ]{1,}$'
    },

    largeColumnStyle () {
      return `flex-basis: ${this.largeColumnWidth}%;`
    },

    nowDate () {
      const date = new Date()
      let day = date.getDate()
      let month = date.getMonth()
      month = month + 1
      if ((String(day)).length === 1) {
        day = '0' + day
      }
      if ((String(month)).length === 1) {
        month = '0' + month
      }

      return day + '.' + month + '.' + date.getFullYear()
    },

    smallColumnStyle () {
      return `flex-basis: ${this.currentSmallColumnWidth}%;`
    }
  },

  methods: {
    async getInitialData () {
      this.isLoading = true
      const url = Routing.generate('api_resource_list', { resourceType: 'AnnotatedStatementPdf' })
      const params = {
        filter: {
          annotatedStatementPdf: {
            condition: {
              path: 'id',
              value: this.documentId
            }
          }
        },
        procedureId: this.procedureId,
        page: {
          size: 1
        },
        include: 'annotatedStatementPdfPages'
      }
      const documentResponse = await dpApi.get(url, params)
      this.document = documentResponse.data.data.find(el => el.type === 'AnnotatedStatementPdf')
      this.formValues = {
        ...this.formValues,
        quickSave: this.document.attributes.quickSave,
        text: this.document.attributes.quickSave ?? this.document.attributes.text
      }
      this.pages = documentResponse.data.included.filter(el => el.type === 'AnnotatedStatementPdfPage')
      this.isLoading = false
    },

    quickSaveText () {
      const payload = {
        data: {
          type: 'AnnotatedStatementPdf',
          id: this.documentId,
          attributes: { quickSave: this.$refs.annotatedPdfForm._data.values.text }
        }
      }

      dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'AnnotatedStatementPdf', resourceId: this.documentId }), {}, payload)
    },

    sortSelected (property) {
      this.values[property].sort((a, b) => (a.name > b.name) ? 1 : ((b.name > a.name) ? -1 : 0))
    },

    startResize (e) {
      const container = this.$refs.container
      const containerBounding = container.getBoundingClientRect()
      const absoluteWidth = containerBounding.width
      this.onePixelInPercent = 1 / (absoluteWidth / 100)
      this.cursorStart = e.pageX
      const bodyEl = document.getElementsByTagName('body')[0]
      bodyEl.addEventListener('mousemove', this.doResize)
      bodyEl.addEventListener('mouseup', this.stopResize)
    },

    doResize (e) {
      const cursor = e.pageX
      const moved = cursor - this.cursorStart
      if (moved < 0 && this.currentSmallColumnWidth <= 80) {
        this.currentSmallColumnWidth = this.smallColumnWidth + (moved * this.onePixelInPercent) * -1
        this.largeColumnWidth = 100 - this.currentSmallColumnWidth
      } else if (moved > 0 && this.currentSmallColumnWidth >= 25) {
        this.currentSmallColumnWidth = this.smallColumnWidth - (moved * this.onePixelInPercent)
        this.largeColumnWidth = 100 - this.currentSmallColumnWidth
      }
    },

    stopResize () {
      this.smallColumnWidth = this.currentSmallColumnWidth
      const bodyEl = document.getElementsByTagName('body')[0]
      bodyEl.removeEventListener('mousemove', this.doResize)
      bodyEl.removeEventListener('mouseup', this.stopResize)
    }
  },

  mounted () {
    this.getInitialData()
  }
}
</script>
