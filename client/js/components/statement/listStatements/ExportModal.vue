<template>
  <div>
    <button
      type="button"
      @click.prevent="openModal"
      class="btn--blank o-link--default inline-block"
      data-cy="openExportModal">
      {{ Translator.trans('export.verb') }}
    </button>

    <dp-modal
      ref="exportModalInner"
      content-classes="w-10/12 md:w-9/12 lg:w-5/12"
      @modal:toggled="isOpen => resetUnsavedOptions(isOpen)">
      <dp-loading v-if="isLoading" />

      <template v-else>
        <h3>{{ Translator.trans('export.statements') }}</h3>
        <section>
          <h4>{{ Translator.trans('file.format') }}</h4>
          <div class="grid grid-cols-3 gap-3">
            <dp-radio
              v-for="(exportType, key) in exportTypes"
              :key="key"
              :id="key"
              :label="{
                text: Translator.trans(exportType.label)
              }"
              :value="key"
              :checked="isActive(key)"
              @change="active = key" />
          </div>
        </section>

        <section v-if="['docx', 'zip'].includes(active)">
          <h4
            id="docxColumnName"
            class="inline-block">
            {{ Translator.trans('export.statements.docx.columnNames') }}
          </h4>
          <dp-contextual-help
            aria-labelledby="docxColumnName"
            :text="Translator.trans('export.statements.docx.columnNames.hint')" />
          <div class="grid grid-cols-5 gap-3">
            <dp-input
              v-for="(column, key) in columns"
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

        <dp-button-row
          class="mt-3"
          primary
          secondary
          :primary-text="Translator.trans('statements.export')"
          :secondary-text="Translator.trans('abort')"
          @primary-action="handleExport"
          @secondary-action="handleAbort" />
      </template>
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
      columns: {
        c_left: {
          width: 'col-span-1',
          dataCy: 'exportModal:colLeft:input',
          placeholder: Translator.trans('segments.export.segment.id'),
          title: null
        },
        c_middle: {
          width: 'col-span-2',
          dataCy: 'exportModal:colMiddle:input',
          placeholder: Translator.trans('segments.export.statement.label'),
          title: null
        },
        c_right: {
          width: 'col-span-2',
          dataCy: 'exportModal:colRight:input',
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
      },
      isLoading: false
    }
  },

  methods: {
    handleInput () {
      this.applyDefaultPlaceholdersForEmptyNames()
    },

    applyDefaultPlaceholdersForEmptyNames () {
      Object.keys(this.columns).forEach(key => {
        if (!this.columns[key].title) {
          this.columns[key].title = null
          this.columns[key].placeholder = this.getDefaultPlaceholderByKey(key)
        }
      })
    },

    isActive (key) {
      return key === this.active
    },

    setInitialValues () {
      Object.keys(this.columns).forEach(key => {
        this.setNameAndPlaceholderByKey(key)
      })
    },

    setNameAndPlaceholderByKey (key) {
      const storedColumnTitle = this.getItemFromSessionStorage(key)

      if (storedColumnTitle) {
        this.columns[key].title = storedColumnTitle
        this.columns[key].placeholder = storedColumnTitle
      } else {
        this.columns[key].title = null // It should have value of null to display the initial names
        this.columns[key].placeholder = this.getDefaultPlaceholderByKey(key)
      }
    },

    getDefaultPlaceholderByKey (key) {
      const defaultPlaceholders = {
        c_left: Translator.trans('segments.export.segment.id'),
        c_middle: Translator.trans('segments.export.statement.label'),
        c_right: Translator.trans('segment.recommendation')
      }

      return defaultPlaceholders[key]
    },

    resetUnsavedValues () {
      Object.keys(this.columns).forEach(key => {
        this.setNameAndPlaceholderByKey(key)
      })
    },

    handleAbort () {
      this.$refs.exportModalInner.toggle()
      this.active = 'docx'
      this.resetUnsavedValues()
      this.$emit('abort')
    },

    handleExport () {
      const columnTitles = {}

      Object.keys(this.columns).forEach(key => {
        if (this.columns[key].title) {
          this.updateSessionStorage(key, this.columns[key].title)
          this.columns[key].placeholder = this.columns[key].title
          columnTitles[key] = this.columns[key].title
        } else {
          this.removeFromSessionStorage(key)
          this.columns[key].placeholder = this.getDefaultPlaceholderByKey(key)
          columnTitles[key] = null
        }
      })
      this.$emit('export', { route: this.exportTypes[this.active].uploadPath, columns: columnTitles })
    },

    openModal () {
      this.setInitialValues()
      this.$refs.exportModalInner.toggle()
    },

    /**
     **
     * Reset only the unsaved selected filter options, but only when closing modal
     */
    resetUnsavedOptions (isOpen) {
      if (!isOpen) {
        this.$emit('close')
      }
    }
  },

  mounted () {
    this.setInitialValues()
  }
}
</script>
