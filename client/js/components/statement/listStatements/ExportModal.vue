<template>
  <div>
    <button
      type="button"
      @click.prevent="openModal"
      class="btn--blank o-link--default inline-block u-mb-0 u-p-0 u-mt-0_125"
      data-cy="openExportModal">
      test
    </button>

    <dp-modal
      ref="exportModalInner"
      class="layout"
      content-classes="w-10/12 md:w-9/12 lg:w-5/12"
      @modal:toggled="isOpen => resetUnsavedOptions(isOpen)">
      <dp-loading v-if="isLoading" />

      <template v-else>
        <h4>Stellungnahmen exportieren</h4>
        <section>
          <h5>Dateiformat</h5>
          <div class="grid grid-cols-3 gap-3">
            <dp-radio
              v-for="(entity, key) in availableExportTypes"
              :key="`entity_type_${key}`"
              :id="key"
              class=""
              :checked="key === active"
              @change="active = key"
              :label="{
                text: Translator.trans(entity.label)
              }"
              :value="key" />
          </div>
        </section>

        <section>
          <h5 class="inline-block">
            Spaltennamen in DOXC_Exporten
          </h5>
          <dp-contextual-help text="some text" />
          <div class="grid grid-cols-5 gap-3">
            <dp-input
              v-for="(column, key) in columnNamesTest"
              :key="key"
              :id="key"
              class=""
              :data-cy="column.defaultValue"
              type="text"
              v-model="column.name"
              :width="column.width"
              :placeholder="Translator.trans(column.placeholder)" />
          </div>
        </section>

        <dp-button-row
          class="u-mt-0_75"
          primary
          secondary
          primary-text="Stellungnahmen exportieren"
          :secondary-text="Translator.trans('abort')"
          @primary-action="handleExport"
          @secondary-action="handleAbort" />
      </template>
    </dp-modal>
  </div>
</template>

<script>
import { DpButtonRow, DpContextualHelp, DpInput, DpModal, DpRadio } from '@demos-europe/demosplan-ui'

export default {
  name: 'ExportModal',

  components: {
    DpModal,
    DpRadio,
    DpContextualHelp,
    DpInput,
    DpButtonRow
  },

  data () {
    return {
      availableExportTypes: {
        docx: {
          label: 'export.statements.docx',
          uploadPath: 'dplan_statement_segments_export',
          dataCy: 'statementsExport:export.docx'
        },
        zip: {
          label: 'export.statements.zip',
          uploadPath: 'dplan_statement_segments_export_packaged',
          dataCy: 'statementsExport:export.zip'
        },
        xlsx: {
          label: 'dplan_statement_xls_export',
          uploadPath: 'dplan_statement_xls_export',
          dataCy: 'statementsExport:export.xlsx'
        }
      },
      isLoading: false,
      active: '',
      columns: {
        c_left: {
          width: 'col-span-1',
          dataCy: 'exportModal:colLeft:input',
          placeholder: 'Abschnitts-ID',
          name: null
        },
        c_middle: {
          width: 'col-span-2',
          dataCy: 'exportModal:colMiddle:input',
          placeholder: 'Einwendung / Stellungnahme',
          name: null
        },
        c_right: {
          width: 'col-span-2',
          dataCy: 'exportModal:colRight:input',
          placeholder: 'Erwiderung',
          name: null
        }
      }
    }
  },

  methods: {
    handleAbort () {
      this.$emit('abort')
    },

    handleExport () {
      this.$emit('export', { uploadPath: this.availableExportTypes[this.active].uploadPath, columnNames: this.columns })
    },

    openModal () {
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
  }
}
</script>
<style scoped>

</style>
