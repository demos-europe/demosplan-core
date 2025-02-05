<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-button
      data-cy="exportModal:open"
      :text="Translator.trans('export.verb')"
      variant="subtle"
      @click.prevent="openModal" />

    <dp-modal
      ref="exportModalInner"
      content-classes="w-11/12 sm:w-10/12 md:w-8/12 lg:w-6/12 xl:w-5/12 h-[500px]"
      content-body-classes="flex flex-col h-[95%]">
      <h2 class="mb-5">
        {{ exportModalTitle }}
      </h2>

      <fieldset v-if="!isSingleStatementExport">
        <legend
          class="o-form__label text-base"
          v-text="Translator.trans('export.type')" />
        <div class="flex flex-row mb-5 mt-1 gap-3">
          <div v-for="(typeDetail, typeKey) in exportTypes" :key="typeKey">
              <dp-radio
                v-for="(subTypeDetail, subTypeKey) in typeDetail"
                :key="subTypeKey"
                :id="`${typeKey}-${subTypeKey}`"
                class="max-w-[70%]"
                :data-cy="`exportType:${typeKey}-${subTypeKey}`"
                :label="{
                    hint: active === `${typeKey}-${subTypeKey}` ? subTypeDetail.hint : '',
                    text: Translator.trans(subTypeDetail.label)
                }"
                :value="`${typeKey}-${subTypeKey}`"
                :checked="active === `${typeKey}-${subTypeKey}`"
                @change="active = `${typeKey}-${subTypeKey}`" />
            </div>
        </div>
      </fieldset>

      <fieldset v-if="['docx-normal', 'docx-censored', 'zip-normal', 'zip-censored', 'xlsx-normal'].includes(this.active)">
        <legend
          id="docxColumnTitles"
          class="o-form__label text-base float-left mr-1"
          v-text="Translator.trans('docx.export.column.title')" />
        <dp-contextual-help
          aria-labelledby="docxColumnTitles"
          :text="Translator.trans('docx.export.column.title.hint')" />
        <div class="grid grid-cols-5 gap-3 mt-1 mb-5">
          <dp-input
            v-for="(column, key) in docxColumns"
            :id="key"
            :key="key"
            v-model="column.title"
            :data-cy="column.dataCy"
            :placeholder="Translator.trans(column.placeholder)"
            type="text"
            :width="column.width" />
        </div>
        <fieldset v-if="this.active === 'zip' || isSingleStatementExport">
          <legend
            id="docxFileName"
            class="o-form__label text-base float-left mr-1"
            v-text="Translator.trans('docx.export.file_name')" />
          <dp-contextual-help
            aria-labelledby="docxFileName"
            :text="Translator.trans('docx.export.file_name.hint')" />
          <dp-input
            id="fileName"
            v-model="fileName"
            class="mt-1"
            :placeholder="Translator.trans('docx.export.file_name.placeholder')"
            type="text" />
          <div class="font-size-small mt-2">
            <span
              class="weight--bold"
              v-text="Translator.trans('docx.export.example_file_name')" />
            <span v-text="exampleFileName" />
          </div>
        </fieldset>
      </fieldset>

      <dp-button-row
        class="text-right mt-auto"
        data-cy="statementExport"
        primary
        secondary
        :primary-text="Translator.trans('export.statements')"
        :secondary-text="Translator.trans('abort')"
        @primary-action="handleExport"
        @secondary-action="closeModal" />
    </dp-modal>
  </div>
</template>

<script>
import {
  DpButton,
  DpButtonRow,
  DpContextualHelp,
  DpInput,
  DpModal,
  DpRadio,
  sessionStorageMixin
} from '@demos-europe/demosplan-ui'

export default {
  name: 'StatementExportModal',

  components: {
    DpButton,
    DpButtonRow,
    DpContextualHelp,
    DpInput,
    DpModal,
    DpRadio
  },

  mixins: [sessionStorageMixin],

  props: {
    isSingleStatementExport: {
      required: false,
      type: Boolean,
      default: false
    }
  },

  data () {
    return {
      active: 'docx-normal',
      docxColumns: {
        col1: {
          width: 'col-span-1',
          dataCy: 'exportModal:input:col1',
          placeholder: Translator.trans('segments.export.segment.id'),
          title: null
        },
        col2: {
          width: 'col-span-2',
          dataCy: 'exportModal:input:col2',
          placeholder: Translator.trans('segments.export.statement.label'),
          title: null
        },
        col3: {
          width: 'col-span-2',
          dataCy: 'exportModal:input:col3',
          placeholder: Translator.trans('segment.recommendation'),
          title: null
        }
      },
      exportTypes: {
        docx: {
          normal: {
            label: 'export.docx',
            hint: '',
            exportPath: 'dplan_statement_segments_export',
            dataCy: 'exportModal:export:docx',
          },
          censored: {
            label: 'export.docx.censored',
            hint: '',
            exportPath: 'dplan_statement_segments_export',
            dataCy: 'exportModal:export:docx',
            censor: true
          },
        },
        zip: {
          normal: {
            label: 'export.zip',
            hint: '',
            exportPath: 'dplan_statement_segments_export_packaged',
            dataCy: 'exportModal:export:zip'
          },
          censored: {
            label: 'export.zip.censored',
            hint: '',
            exportPath: 'dplan_statement_segments_export_packaged',
            dataCy: 'exportModal:export:zip',
            censor: true
          }
        },
        xlsx: {
          normal: {
            label: 'export.xlsx',
            hint: Translator.trans('export.xlsx.hint'),
            exportPath: 'dplan_statement_xls_export',
            dataCy: 'exportModal:export:xlsx'
          }
        }
      },
      fileName: '',
      singleStatementExportPath: 'dplan_segments_export' /** Used in the statements detail page */
    }
  },

  computed: {
    exportModalTitle () {
      return this.isSingleStatementExport ? Translator.trans('statement.export.do') : Translator.trans('export.statements')
    },

    exampleFileName () {
      let exampleFileName = 'm101-jacob-meier-e5089.docx'
      const exampleId = 'm101'
      const exampleName = 'jacob-meier'
      const exampleInternId = 'e5089'

      if (this.fileName) {
        exampleFileName = this.fileName
          .replace(/{ID}/g, exampleId)
          .replace(/{NAME}/g, exampleName)
          .replace(/{EINGANGSNR}/g, exampleInternId)
          .replace(/[_\s]/g, '-')

        // Add example unique id if no placeholder was found
        if (exampleFileName === this.fileName) {
          exampleFileName += '-837474df23'
        }
        exampleFileName += '.docx'
      }

      return exampleFileName
    }
  },

  methods: {
    getActiveTypeDetail() {
      const [typeKey, subTypeKey] = this.active.split('-');

      return this.exportTypes[typeKey][subTypeKey];
    },
    closeModal () {
      this.$refs.exportModalInner.toggle()
    },

    handleExport () {
      const columnTitles = {}

      Object.keys(this.docxColumns).forEach(key => {
        const columnTitle = this.docxColumns[key].title
        const storageKey = `exportModal:docxCol:${key}`

        if (columnTitle) {
          this.updateSessionStorage(storageKey, columnTitle)
          columnTitles[key] = columnTitle
        } else {
          this.removeFromSessionStorage(storageKey)
          columnTitles[key] = null /** Setting the value to null will trigger the display of the default column titles */
        }
      })

      this.$emit('export', {
        route: this.isSingleStatementExport ? this.singleStatementExportPath : this.getActiveTypeDetail().exportPath,
        docxHeaders: ['docx-normal', 'docx-censored', 'zip-normal', 'zip-censored', 'xlsx-normal'].includes(this.active) ? columnTitles : null,
        fileNameTemplate: this.fileName || null,
        censorParameter: this.getActiveTypeDetail().censor || false
      })
      this.closeModal()
    },

    openModal () {
      this.setInitialValues()
      this.$refs.exportModalInner.toggle()
    },

    setInitialValues () {
      this.active = 'docx'

      Object.keys(this.docxColumns).forEach(key => {
        const storageKey = `exportModal:docxCol:${key}`
        const storedColumnTitle = this.getItemFromSessionStorage(storageKey)
        this.docxColumns[key].title = storedColumnTitle || null /** Setting the value to null will display the placeholder titles of the column */
      })
    }
  }
}
</script>
