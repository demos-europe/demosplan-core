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
        {{ Translator.trans('export.statements') }}
      </h2>

      <section>
        <h3 class="text-lg">
          {{ Translator.trans('file.format') }}
        </h3>
        <div class="flex flex-row mb-5 mt-1">
          <dp-radio
            v-for="(exportType, key) in exportTypes"
            :key="key"
            :id="key"
            class="mr-4"
            :label="{
              text: Translator.trans(exportType.label)
            }"
            :value="key"
            :checked="isActive(key)"
            @change="active = key" />
        </div>
      </section>

      <section v-if="['docx', 'zip'].includes(active)">
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
            :data-cy="column.defaultValue"
            :placeholder="Translator.trans(column.placeholder)"
            :width="column.width"
            v-model="column.title"
            @input="handleInput" />
        </div>
      </section>

      <div class="flex justify-end">
        <dp-button-row
          primary
          secondary
          :primary-text="Translator.trans('export.statements')"
          :secondary-text="Translator.trans('abort')"
          @primary-action="handleExport"
          @secondary-action="handleAbort" />
      </div>
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
  name: 'ExportModal',

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
        left: {
          width: 'col-span-1',
          dataCy: 'exportModal:input:colLeft',
          placeholder: Translator.trans('segments.export.segment.id'),
          title: null
        },
        middle: {
          width: 'col-span-2',
          dataCy: 'exportModal:input:colMiddle',
          placeholder: Translator.trans('segments.export.statement.label'),
          title: null
        },
        right: {
          width: 'col-span-2',
          dataCy: 'exportModal:input:colRight',
          placeholder: Translator.trans('segment.recommendation'),
          title: null
        }
      },
      exportTypes: {
        docx: {
          label: 'export.statements.docx',
          uploadPath: 'dplan_statement_segments_export',
          dataCy: 'exportModal:export:docx'
        },
        zip: {
          label: 'export.statements.zip',
          uploadPath: 'dplan_statement_segments_export_packaged',
          dataCy: 'exportModal:export:zip'
        },
        xlsx: {
          label: 'export.statements.xlsx',
          uploadPath: 'dplan_statement_xls_export',
          dataCy: 'exportModal:export:xlsx'
        }
      }
    }
  },

  methods: {
    applyDefaultPlaceholdersForEmptyNames () {
      Object.keys(this.docxColumns).forEach(key => {
        if (!this.docxColumns[key].title) {
          this.docxColumns[key].title = null
          this.docxColumns[key].placeholder = this.getDefaultPlaceholderByKey(key)
        }
      })
    },

    closeModalAndResetValues () {
      this.$refs.exportModalInner.toggle()
      this.active = 'docx'
      this.resetColumnsUnsavedValues()
    },

    getDefaultPlaceholderByKey (key) {
      const defaultPlaceholders = {
        left: Translator.trans('segments.export.segment.id'),
        middle: Translator.trans('segments.export.statement.label'),
        right: Translator.trans('segment.recommendation')
      }

      return defaultPlaceholders[key]
    },

    handleAbort () {
      this.$emit('abort')
      this.closeModalAndResetValues()
    },

    handleExport () {
      const columnTitles = {}

      Object.keys(this.docxColumns).forEach(key => {
        const columnTitle = this.docxColumns[key].title
        const storageKey = `exportModal:docxCol:${key}`

        if (columnTitle) {
          this.updateSessionStorage(storageKey, columnTitle)
          this.docxColumns[key].placeholder = columnTitle
          columnTitles[key] = columnTitle
        } else {
          this.removeFromSessionStorage(storageKey)
          this.docxColumns[key].placeholder = this.getDefaultPlaceholderByKey(key)
          columnTitles[key] = null /** Setting the value to null will trigger the display of the default column titles */
        }
      })

      this.$emit('export', {
        route: this.exportTypes[this.active].uploadPath,
        docxHeaders: ['docx', 'zip'].includes(this.active) ? columnTitles : null
      })
      this.closeModalAndResetValues()
    },

    handleInput () {
      this.applyDefaultPlaceholdersForEmptyNames()
    },

    isActive (key) {
      return key === this.active
    },

    openModal () {
      this.setInitialValues()
      this.$refs.exportModalInner.toggle()
    },

    resetColumnsUnsavedValues () {
      Object.keys(this.docxColumns).forEach(key => {
        this.setTitleAndPlaceholderByKey(key)
      })
    },

    setInitialValues () {
      Object.keys(this.docxColumns).forEach(key => {
        this.setTitleAndPlaceholderByKey(key)
      })
    },

    setTitleAndPlaceholderByKey (key) {
      const storageKey = `exportModal:docxCol:${key}`
      const storedColumnTitle = this.getItemFromSessionStorage(storageKey)
      this.docxColumns[key].title = storedColumnTitle || null /** Setting the value to null will trigger the display of the default column titles */
      this.docxColumns[key].placeholder = storedColumnTitle || this.getDefaultPlaceholderByKey(key)
    }
  },

  mounted () {
    this.setInitialValues()
  }
}
</script>
