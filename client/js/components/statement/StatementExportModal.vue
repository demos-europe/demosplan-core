<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <button
      type="button"
      @click.prevent="openModal"
      class="btn--blank o-link--default inline-block"
      data-cy="exportModal:open">
      {{ Translator.trans('export.verb') }}
    </button>

    <dp-modal
      ref="exportModalInner"
      content-classes="w-11/12 sm:w-10/12 md:w-8/12 lg:w-6/12 xl:w-5/12">
      <h2 class="mb-5">
        {{ exportModalTitle }}
      </h2>

      <section v-if="!isSingleStatementExport">
        <h3 class="text-lg">
          {{ Translator.trans('export.type') }}
        </h3>
        <div class="flex flex-row mb-5 mt-1 gap-3">
          <dp-radio
            v-for="(exportType, key) in exportTypes"
            :key="key"
            :id="key"
            :label="{
              text: Translator.trans(exportType.label)
            }"
            :value="key"
            :checked="active === key"
            @change="active = key" />
        </div>
      </section>

      <section v-if="['docx', 'zip'].includes(this.active)">
        <h3
          id="docxColumnTitles"
          class="inline-block text-lg mr-1">
          {{ Translator.trans('docx.export.column.title') }}
        </h3>
        <dp-contextual-help
          aria-labelledby="docxColumnTitles"
          :text="Translator.trans('docx.export.column.title.hint')" />
        <div class="grid grid-cols-5 gap-3 mt-1 mb-5">
          <dp-input
            v-for="(column, key) in docxColumns"
            :key="key"
            :id="key"
            type="text"
            :data-cy="column.dataCy"
            :placeholder="Translator.trans(column.placeholder)"
            :width="column.width"
            v-model="column.title" />
        </div>
      </section>

        <dp-button-row
          class="text-right"
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
    DpButtonRow,
    DpContextualHelp,
    DpInput,
    DpModal,
    DpRadio
  },

  mixins: [sessionStorageMixin],

  data () {
    return {
      active: 'docx',
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
          label: 'export.docx',
          exportPath: 'dplan_statement_segments_export',
          dataCy: 'exportModal:export:docx'
        },
        zip: {
          label: 'export.zip',
          exportPath: 'dplan_statement_segments_export_packaged',
          dataCy: 'exportModal:export:zip'
        },
        xlsx: {
          label: 'export.xlsx',
          exportPath: 'dplan_statement_xls_export',
          dataCy: 'exportModal:export:xlsx'
        }
      },
      singleStatementExportPath: 'dplan_segments_export' /** used in the statements detail page */
    }
  },

  props: {
    isSingleStatementExport: {
      required: false,
      type: Boolean,
      default: false
    }
  },

  computed: {
    exportModalTitle () {
      return this.isSingleStatementExport ? Translator.trans('statement.export.do') : Translator.trans('export.statements')
    }
  },

  methods: {
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
        route: this.isSingleStatementExport ? this.singleStatementExportPath : this.exportTypes[this.active].exportPath,
        docxHeaders: ['docx', 'zip'].includes(this.active) ? columnTitles : null
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
