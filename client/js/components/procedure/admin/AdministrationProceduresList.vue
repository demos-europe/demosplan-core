<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="space-stack-s">
    <div class="flex justify-between">
      <p
        v-text="Translator.trans('text.procedures.list')" />

      <div
        v-if="hasPermission('feature_admin_new_procedure')"
        class="text-right">
        <dp-button
          data-cy="createNewProcedure"
          data-extern-dataport="newProcedure"
          :href="Routing.generate('DemosPlan_procedure_new')"
          :text="Translator.trans('procedure.create')" />
        <p
          v-if="hasPermission('feature_show_free_disk_space') && freeDiskSpace.length"
          class="u-mt-0_5"
          v-text="freeDiskSpace" />
      </div>
    </div>

    <div class="flex w-full">
      <dp-search-field
        input-width="u-1-of-2"
        @search="searchTerm => searchAdministrationProceduresList(searchTerm)"
        @reset="resetAdministrationProceduresList" />

      <dp-select
        class="w-11 ml-auto"
        data-cy="selectedSort"
        :options="options"
        :selected="selectedSort"
        :show-placeholder="false"
        @select="applySort" />
    </div>

    <form
      v-if="hasPermission('feature_admin_delete_procedure') || hasPermission('feature_admin_export_procedure')"
      name="procedureForm"
      ref="procedureForm">
      <dp-button
        v-if="hasPermission('feature_admin_delete_procedure')"
        data-cy="deleteProcedure"
        icon="delete"
        name="deleteProcedure"
        :text="Translator.trans('delete')"
        type="submit"
        variant="subtle"
        @click="deleteProcedures" />

      <dp-button
        v-if="hasPermission('feature_admin_export_procedure')"
        data-cy="ExportProcedure"
        icon="download"
        name="exportProcedure"
        :text="Translator.trans('print.and.export')"
        type="submit"
        variant="subtle"
        @click="exportProcedures" />

      <!-- Hidden inputs needed for export and delete functionalities -->
      <input
        v-for="selectedItem in this.selectedItems"
        :key="selectedItem"
        name="procedure_selected[]"
        type="hidden"
        :value="selectedItem">
    </form>

    <dp-loading
      v-if="isLoading"
      class="u-mt-2" />

    <dp-data-table
      v-else
      data-cy="administrationProceduresListTable"
      :header-fields="headerFields"
      is-selectable
      :items="items"
      @items-selected="setSelectedItems"
      :search-string="searchString"
      track-by="id">
      <template
        v-if="showInternalPhases"
        v-slot:header-internalPhase>
        <span v-text="Translator.trans('procedure.public.phase')" />
        <div v-text="Translator.trans('institution')" />
      </template>
      <template
        v-if="showStatementCount"
        v-slot:header-count>
        {{ Translator.trans('quantity') }}
        <dp-icon
          v-tooltip="Translator.trans('procedures.statements.count')"
          icon="info"
          size="small" />
      </template>

      <template
        v-if="showInternalPhases"
        v-slot:header-externalPhase>
        <div />
        <div v-text="Translator.trans('public')" />
      </template>

      <template v-slot:name="{ creationDate, externalName, id, name }">
        <a
          data-cy="procedurePath"
          :data-cy-procedure-id="id"
          :href="Routing.generate('DemosPlan_procedure_dashboard', { procedure: id })">
          <strong v-text="name" />
        </a>
        <div v-if="externalName !== name">
          <strong v-text="`(${Translator.trans('public.participation.name')}: ${externalName})`" />
        </div>
        <div>
          <strong v-text="`${Translator.trans('from.date')} ${creationDate}`" />
        </div>
      </template>

      <template
        v-if="showStatementCount"
        v-slot:count="{ statementsCount, originalStatementsCount }">
        <div
          v-tooltip="statementsTooltipCount(statementsCount, originalStatementsCount)"
          v-text="statementsCount"
          class="text-center" />
      </template>

      <template v-slot:internalPhase="{ internalPhase, internalStartDate, internalEndDate }">
        <div
          class="float-left u-m-0">
          <span v-text="internalPhase" />
          <div v-text="internalStartDate + ' - ' + internalEndDate" />
        </div>
      </template>

      <template v-slot:externalPhase="{ externalPhase, externalStartDate, externalEndDate }">
        <span v-text="externalPhase" />
        <div v-text="externalStartDate + ' - ' + externalEndDate" />
      </template>
    </dp-data-table>
  </div>
</template>

<script>
import {
  dpApi,
  DpButton,
  DpDataTable,
  DpIcon,
  DpLoading,
  DpSearchField,
  DpSelect,
  formatDate
} from '@demos-europe/demosplan-ui'

export default {
  name: 'AdministrationProceduresList',

  components: {
    DpButton,
    DpDataTable,
    DpIcon,
    DpLoading,
    DpSearchField,
    DpSelect
  },

  props: {
    freeDiskSpace: {
      type: String,
      default: ''
    },

    showInternalPhases: {
      type: Boolean,
      default: false
    },

    showStatementCount: {
      type: Boolean,
      default: false
    }
  },

  data () {
    return {
      items: [],
      isLoading: true,
      options: [
        { value: '-creationDate', label: Translator.trans('sort.date.descending') },
        { value: 'creationDate', label: Translator.trans('sort.date.ascending') },
        { value: '-name', label: Translator.trans('sort.procedurename.desc') },
        { value: 'name', label: Translator.trans('sort.procedurename') }
      ],
      searchInput: '',
      searchString: '',
      selectedItems: [],
      selectedSort: ''
    }
  },

  computed: {
    headerFields () {
      const fields = [
        {
          colClass: this.showInternalPhases ? 'u-1-of-2' : 'u-3-of-4',
          field: 'name',
          isVisible: true,
          label: Translator.trans('name')
        },
        {
          colClass: 'w-8',
          field: 'count',
          isVisible: this.showStatementCount,
          label: Translator.trans('quantity')
        },
        {
          colClass: 'w-10',
          field: 'internalPhase',
          isVisible: this.showInternalPhases
        },
        {
          colClass: this.showInternalPhases ? 'w-10' : 'u-1-of-4',
          field: 'externalPhase',
          isVisible: true,
          label: !this.showInternalPhases && Translator.trans('procedure.public.phase')
        }
      ]

      return fields.filter(field => field.isVisible)
    }
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

    deleteProcedures (event) {
      if (dpconfirm(Translator.trans('check.entries.marked.delete'))) {
        this.$refs.procedureForm.method = 'post'
        this.$refs.procedureForm.action = Routing.generate('DemosPlan_procedures_delete')
      } else {
        event.preventDefault()
      }
    },

    exportProcedures (event) {
      if (dpconfirm(Translator.trans('check.entries.marked.export'))) {
        this.$refs.procedureForm.method = 'post'
        this.$refs.procedureForm.action = Routing.generate('DemosPlan_procedures_export')
      } else {
        event.preventDefault()
      }
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
            'externalPhaseTranslationKey',
            'internalStartDate',
            'internalEndDate',
            'internalPhaseIdentifier',
            'internalPhaseTranslationKey',
            'originalStatementsCount',
            'statementsCount'
          ].join()
        },
        filter: {
          AdminProcedureFilter: {
            condition: {
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              path: 'name',
              value: this.searchString
            }
          }
        },
        include: 'procedure',
        sort
      }

      dpApi.get(url, params)
        .then(response => {
          response.data.data.forEach(el => this.items.push({
            creationDate: formatDate(el.attributes.creationDate),
            creationDateRaw: el.attributes.creationDate,
            name: el.attributes.name,
            externalName: el.attributes.externalName,
            externalEndDate: formatDate(el.attributes.externalEndDate),
            externalPhase: el.attributes.externalPhaseTranslationKey,
            externalStartDate: formatDate(el.attributes.externalStartDate),
            id: el.id,
            internalEndDate: formatDate(el.attributes.internalEndDate),
            internalPhase: el.attributes.internalPhaseTranslationKey,
            internalStartDate: formatDate(el.attributes.internalStartDate),
            originalStatementsCount: el.attributes.originalStatementsCount,
            statementsCount: el.attributes.statementsCount
          }))
        })
        .catch(e => {
          console.error(e)
        })
        .finally(() => {
          this.isLoading = false
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
    }
  },

  created () {
    this.fetchAdministrationProceduresList()
  }
}
</script>
