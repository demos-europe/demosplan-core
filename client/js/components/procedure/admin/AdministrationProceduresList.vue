<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="space-stack-m">
    <div class="flex justify-between">
      <p
        v-text="Translator.trans('text.procedures.list')"
      />

      <div
        v-if="hasPermission('feature_admin_new_procedure')"
        class="text-right"
      >
        <dp-button
          data-cy="createNewProcedure"
          data-extern-dataport="newProcedure"
          :href="Routing.generate('DemosPlan_procedure_new')"
          :text="Translator.trans('procedure.create')"
        />
        <p
          v-if="hasPermission('feature_show_free_disk_space') && freeDiskSpace.length"
          class="u-mt-0_5"
          v-text="freeDiskSpace"
        />
      </div>
    </div>

    <div class="flex items-center w-full">
      <dp-search-field
        class="w-full"
        input-width="u-1-of-2"
        @search="searchTerm => searchAdministrationProceduresList(searchTerm)"
        @reset="resetAdministrationProceduresList"
      />

      <form
        v-if="hasRowActions"
        ref="procedureForm"
        class="flex gap-2 ml-auto"
        name="procedureForm"
      >
        <dp-button
          v-if="hasPermission('feature_admin_delete_procedure')"
          data-cy="deleteProcedure"
          :disabled="!isProcedureSelected"
          icon="delete"
          name="deleteProcedure"
          :text="Translator.trans('delete')"
          type="submit"
          variant="subtle"
          @click="deleteProcedures"
        />

        <dp-button
          v-if="hasPermission('feature_admin_export_procedure')"
          data-cy="ExportProcedure"
          :disabled="!isProcedureSelected"
          icon="export"
          name="exportProcedure"
          :text="Translator.trans('print.and.export')"
          type="submit"
          variant="subtle"
          @click="exportProcedures"
        />

        <!-- Hidden inputs needed for export and delete functionalities -->
        <input
          v-for="procedureId in formProcedureIds"
          :key="procedureId"
          name="procedure_selected[]"
          type="hidden"
          :value="procedureId"
        >
      </form>
    </div>

    <div class="flex items-center justify-between">
      <div
        v-if="hasPermission('feature_procedure_read_only_toggle')"
        class="flex items-center gap-1"
      >
        <dp-toggle
          :aria-label="Translator.trans('procedure.archive.filter.active')"
          data-cy="showOnlyActiveProcedures"
          :model-value="showOnlyActiveProcedures"
          @update:model-value="showOnlyActiveProcedures = $event"
        />
        <span
          aria-hidden="true"
          v-text="Translator.trans('procedure.archive.filter.active')"
        />
      </div>

      <dp-select
        class="w-11 ml-auto"
        data-cy="selectedSort"
        :options="options"
        :selected="selectedSort"
        :show-placeholder="false"
        @select="applySort"
      />
    </div>

    <dp-loading
      v-if="isLoading"
      class="u-mt-2"
    />

    <dp-data-table
      v-else
      data-cy="administrationProceduresListTable"
      :has-flyout="hasRowActions"
      :header-fields="headerFields"
      is-selectable
      :items="visibleItems"
      :search-string="searchString"
      track-by="id"
      @items-selected="setSelectedItems"
    >
      <template
        v-if="showInternalPhases"
        v-slot:header-internalPhase
      >
        <span v-text="Translator.trans('procedure.public.phase')" />
        <div v-text="Translator.trans('institution')" />
      </template>
      <template
        v-if="showStatementCount"
        v-slot:header-count
      >
        {{ Translator.trans('quantity') }}
        <dp-icon
          v-tooltip="Translator.trans('procedures.statements.count')"
          icon="info"
          size="small"
        />
      </template>

      <template
        v-if="showInternalPhases"
        v-slot:header-externalPhase
      >
        <div />
        <div v-text="Translator.trans('public')" />
      </template>

      <template v-slot:name="{ creationDate, externalName, id, name, readOnly }">
        <div class="flex items-center justify-between gap-2">
          <a
            data-cy="procedurePath"
            :data-cy-procedure-id="id"
            :href="getProcedureLink(id, readOnly)"
          >
            <strong v-text="name" />
          </a>
          <dp-badge
            v-if="readOnly"
            class="shrink-0"
            color="default"
            data-cy="procedureArchivedBadge"
            icon="archive"
            size="small"
            :text="Translator.trans('procedure.archived')"
          />
        </div>
        <div v-if="externalName !== name">
          <strong v-text="`(${Translator.trans('public.participation.name')}: ${externalName})`" />
        </div>
        <div>
          <strong v-text="`${Translator.trans('from.date')} ${creationDate}`" />
        </div>
      </template>

      <template v-slot:flyout="{ id, name, readOnly }">
        <dp-flyout data-cy="procedureActionsMenu">
          <button
            v-if="hasPermission('feature_procedure_read_only_toggle')"
            class="block btn--blank o-link--default leading-[2] whitespace-nowrap"
            data-cy="procedureArchiveToggle"
            type="button"
            @click="openArchiveModal(id, name, readOnly)"
          >
            {{ readOnly ? Translator.trans('procedure.archive.undo') : Translator.trans('procedure.archive') }}
          </button>
          <button
            v-if="hasPermission('feature_admin_export_procedure')"
            :class="{ 'is-disabled': readOnly }"
            :disabled="readOnly"
            class="block btn--blank o-link--default leading-[2] whitespace-nowrap"
            data-cy="procedureExport"
            type="button"
            @click="exportProcedure(id)"
          >
            {{ Translator.trans('export.verb') }}
          </button>
          <button
            v-if="hasPermission('feature_admin_delete_procedure')"
            :class="{ 'is-disabled': readOnly }"
            :disabled="readOnly"
            class="block btn--blank o-link--default leading-[2] whitespace-nowrap"
            data-cy="procedureDelete"
            type="button"
            @click="deleteProcedure(id)"
          >
            {{ Translator.trans('delete') }}
          </button>
        </dp-flyout>
      </template>

      <template
        v-if="showStatementCount"
        v-slot:count="{ statementsCount, originalStatementsCount }"
      >
        <div
          v-tooltip="statementsTooltipCount(statementsCount, originalStatementsCount)"
          class="text-center"
          v-text="statementsCount"
        />
      </template>

      <template v-slot:internalPhase="{ internalPhase, internalStartDate, internalEndDate }">
        <div
          class="float-left u-m-0"
        >
          <span v-text="internalPhase" />
          <div v-text="internalStartDate + ' - ' + internalEndDate" />
        </div>
      </template>

      <template v-slot:externalPhase="{ externalPhase, externalStartDate, externalEndDate }">
        <span v-text="externalPhase" />
        <div v-text="externalStartDate + ' - ' + externalEndDate" />
      </template>
    </dp-data-table>

    <dp-modal
      ref="archiveModal"
      content-classes="w-1/3"
      data-cy="procedureArchiveModal"
    >
      <template v-slot:header>
        <h3 class="mb-0">
          {{ Translator.trans(archiveModal.readOnly ? 'procedure.archive.undo.confirm.title' : 'procedure.archive.confirm.title') }}
        </h3>
      </template>
      <p class="mb-0">
        {{ Translator.trans(archiveModal.readOnly ? 'procedure.archive.undo.confirm.text' : 'procedure.archive.confirm.text', { procedureName: archiveModal.procedureName }) }}
      </p>
      <template v-slot:footer>
        <dp-button-row
          :primary-text="Translator.trans(archiveModal.readOnly ? 'procedure.archive.undo' : 'procedure.archive')"
          data-cy="procedureArchiveModal"
          primary
          secondary
          @primary-action="toggleReadOnly"
          @secondary-action="$refs.archiveModal.toggle()"
        />
      </template>
    </dp-modal>
  </div>
</template>

<script>
import {
  dpApi,
  DpBadge,
  DpButton,
  DpButtonRow,
  DpDataTable,
  DpFlyout,
  DpIcon,
  DpLoading,
  DpModal,
  DpSearchField,
  DpSelect,
  DpToggle,
  formatDate,
} from '@demos-europe/demosplan-ui'
import { pollExportJob } from '@DpJs/lib/shared/persistentExportPoll'

export default {
  name: 'AdministrationProceduresList',

  components: {
    DpBadge,
    DpButton,
    DpButtonRow,
    DpDataTable,
    DpFlyout,
    DpIcon,
    DpLoading,
    DpModal,
    DpSearchField,
    DpSelect,
    DpToggle,
  },

  props: {
    freeDiskSpace: {
      type: String,
      default: '',
    },

    showInternalPhases: {
      type: Boolean,
      default: false,
    },

    showStatementCount: {
      type: Boolean,
      default: false,
    },
  },

  data () {
    return {
      archiveModal: {
        procedureId: null,
        procedureName: '',
        readOnly: false,
      },
      items: [],
      isLoading: true,
      options: [
        { value: '-creationDate', label: Translator.trans('sort.date.descending') },
        { value: 'creationDate', label: Translator.trans('sort.date.ascending') },
        { value: '-name', label: Translator.trans('sort.procedurename.desc') },
        { value: 'name', label: Translator.trans('sort.procedurename') },
      ],
      // Set while a single row action submits the form, see submitProcedureForm()
      rowActionProcedureId: null,
      searchInput: '',
      searchString: '',
      selectedItems: [],
      selectedSort: '',
      showOnlyActiveProcedures: true,
    }
  },

  computed: {
    formProcedureIds () {
      return this.rowActionProcedureId ? [this.rowActionProcedureId] : this.selectedItems
    },

    hasRowActions () {
      return this.hasPermission('feature_procedure_read_only_toggle') ||
        this.hasPermission('feature_admin_export_procedure') ||
        this.hasPermission('feature_admin_delete_procedure')
    },

    headerFields () {
      const fields = [
        {
          colClass: this.showInternalPhases ? 'u-1-of-2' : 'u-3-of-4',
          field: 'name',
          isVisible: true,
          label: Translator.trans('name'),
        },
        {
          colClass: 'w-8',
          field: 'count',
          isVisible: this.showStatementCount,
          label: Translator.trans('quantity'),
        },
        {
          colClass: 'w-10',
          field: 'internalPhase',
          isVisible: this.showInternalPhases,
        },
        {
          colClass: this.showInternalPhases ? 'w-10' : 'u-1-of-4',
          field: 'externalPhase',
          isVisible: true,
          label: !this.showInternalPhases && Translator.trans('procedure.public.phase'),
        },
      ]

      return fields.filter(field => field.isVisible)
    },

    isProcedureSelected () {
      return this.selectedItems.length > 0
    },

    visibleItems () {
      return this.showOnlyActiveProcedures ?
        this.items.filter(item => !item.readOnly) :
        this.items
    },
  },

  methods: {
    applySearch () {
      this.fetchAdministrationProceduresList()
    },

    applySort (sortValue) {
      this.items = []
      this.selectedSort = sortValue
      this.fetchAdministrationProceduresList(sortValue)
    },

    deleteProcedure (procedureId) {
      if (dpconfirm(Translator.trans('check.entries.marked.delete'))) {
        this.runRowAction(procedureId, () => {
          this.$refs.procedureForm.method = 'post'
          this.$refs.procedureForm.action = Routing.generate('DemosPlan_procedures_delete')
          this.$refs.procedureForm.submit()
        })
      }
    },

    deleteProcedures (event) {
      if (dpconfirm(Translator.trans('check.entries.marked.delete'))) {
        this.$refs.procedureForm.method = 'post'
        this.$refs.procedureForm.action = Routing.generate('DemosPlan_procedures_delete')
      } else {
        event.preventDefault()
      }
    },

    exportProcedure (procedureId) {
      if (dpconfirm(Translator.trans('check.entries.marked.export'))) {
        this.runRowAction(procedureId, this.startProceduresExport)
      }
    },

    exportProcedures (event) {
      event.preventDefault()

      if (!dpconfirm(Translator.trans('check.entries.marked.export'))) {
        return
      }

      /*
       * A read-only procedure grants none of the export content permissions, so it would only
       * add its name to the archive.
       */
      const readOnlyProcedureNames = this.items
        .filter(item => this.selectedItems.includes(item.id) && item.readOnly)
        .map(item => item.name)

      if (readOnlyProcedureNames.length > 0) {
        dplan.notify.error(Translator.trans('error.procedure.export.read.only', { procedureNames: readOnlyProcedureNames.join(', ') }))

        return
      }

      this.startProceduresExport()
    },

    /**
     * Runs a form-based action for a single procedure. The hidden inputs render from
     * formProcedureIds, so the DOM has to catch up before the form is read.
     */
    async runRowAction (procedureId, action) {
      this.rowActionProcedureId = procedureId
      await this.$nextTick()
      action()
      this.rowActionProcedureId = null
    },

    startProceduresExport () {
      // The export runs as a background job; poll it and download the file once it is ready
      dplan.notify.notify('info', Translator.trans('export.processing'))
      fetch(Routing.generate('DemosPlan_procedures_export_async_start'), {
        method: 'POST',
        body: new FormData(this.$refs.procedureForm),
        credentials: 'same-origin',
      })
        .then(response => {
          if (!response.ok) {
            throw new Error(response.statusText)
          }

          return response.json()
        })
        .then(({ jobId }) => pollExportJob({
          key: `procedures.${jobId}`,
          statusUrl: Routing.generate('DemosPlan_procedures_export_status', { jobId }),
          downloadUrl: Routing.generate('DemosPlan_procedures_export_download', { jobId }),
        }))
        .catch(() => dplan.notify.error(Translator.trans('error.export')))
    },

    fetchAdministrationProceduresList (sort = '-creationDate') {
      this.isLoading = true
      const url = Routing.generate('api_resource_list', { resourceType: 'AdminProcedure' })
      const params = {
        fields: {
          AdminProcedure: [
            'creationDate',
            'name',
            'externalName',
            'externalStartDate',
            'externalEndDate',
            'externalPhaseDefinitionName',
            'internalStartDate',
            'internalEndDate',
            'internalPhaseDefinitionName',
            'originalStatementsCount',
            'readOnly',
            'statementsCount',
          ].join(),
        },
        filter: {
          AdminProcedureFilter: {
            condition: {
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              path: 'name',
              value: this.searchString,
            },
          },
        },

        sort,
      }

      dpApi.get(url, params)
        .then(response => {
          response.data.data.forEach(el => this.items.push({
            creationDate: formatDate(el.attributes.creationDate),
            creationDateRaw: el.attributes.creationDate,
            name: el.attributes.name,
            externalName: el.attributes.externalName,
            externalEndDate: formatDate(el.attributes.externalEndDate),
            externalPhase: el.attributes.externalPhaseDefinitionName,
            externalStartDate: formatDate(el.attributes.externalStartDate),
            id: el.id,
            internalEndDate: formatDate(el.attributes.internalEndDate),
            internalPhase: el.attributes.internalPhaseDefinitionName,
            internalStartDate: formatDate(el.attributes.internalStartDate),
            originalStatementsCount: el.attributes.originalStatementsCount,
            readOnly: el.attributes.readOnly,
            statementsCount: el.attributes.statementsCount,
          }))
        })
        .catch(e => {
          console.error(e)
        })
        .finally(() => {
          this.isLoading = false
        })
    },

    getProcedureLink (procedureId, readOnly) {
      if (readOnly) {
        return Routing.generate('dplan_procedure_statement_list', { procedureId: procedureId })
      }

      return Routing.generate('DemosPlan_procedure_dashboard', { procedure: procedureId })
    },

    openArchiveModal (procedureId, procedureName, readOnly) {
      this.archiveModal = { procedureId, procedureName, readOnly }
      this.$refs.archiveModal.toggle()
    },

    toggleReadOnly () {
      const { procedureId, readOnly } = this.archiveModal
      const url = Routing.generate('dplan_procedure_read_only_toggle', { procedureId: procedureId })

      dpApi.post(url, {}, { readOnly: !readOnly })
        .then(() => {
          const procedure = this.items.find(item => item.id === procedureId)

          if (procedure) {
            procedure.readOnly = !readOnly
          }
        })
        .catch(e => {
          console.error(e)
        })
        .finally(() => {
          this.$refs.archiveModal.toggle()
        })
    },

    resetAdministrationProceduresList () {
      this.items = []
      this.searchInput = ''
      this.searchString = ''
      this.fetchAdministrationProceduresList()
    },

    searchAdministrationProceduresList (searchTerm) {
      this.items = []
      this.searchString = searchTerm
      this.fetchAdministrationProceduresList()
    },

    setSelectedItems (items) {
      this.selectedItems = items
    },

    statementsTooltipCount (statementsCount, originalStatementsCount) {
      const statements = Translator.trans('procedures.statements.count.description', { statements: statementsCount })
      const originalStatements = Translator.trans('procedures.statements.count.original.description', { statements: originalStatementsCount })

      return `${statements.trim()}, ${originalStatements.trim()}`
    },
  },

  created () {
    this.fetchAdministrationProceduresList()
  },
}
</script>
