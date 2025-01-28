<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <form
    id="start"
    ref="bpform"
    name="bpform"
    :action="Routing.generate('dplan_assessmenttable_view_original_table', { procedureId: procedureId, '_fragment': 'start', 'filterHash': filterHash })"
    method="post"
    data-sticky-context
    :data-assessment-original-statements="procedureId">
    <input
      type="hidden"
      name="r_ident"
      value="">
    <input
      type="hidden"
      name="r_text"
      value="">
    <input
      type="hidden"
      name="r_action"
      :value="action">
    <input
      type="hidden"
      name="r_export_format"
      value="">
    <input
      type="hidden"
      name="r_export_choice"
      value="">
    <input
      type="hidden"
      name="searchFields"
      value="">
    <!-- The hidden Input is required for the form post needed to update the items per page -->
    <input
      type="hidden"
      name="r_limit"
      :value="pageSize">
    <input
      name="_token"
      type="hidden"
      :value="csrfToken">

    <dp-pager
      v-if="pagination.hasOwnProperty('current_page')"
      :key="`pager1_${pagination.current_page}_${pagination.count}`"
      :class="{ 'invisible': isLoading }"
      class="u-pt-0_5 text-right u-1-of-1"
      :current-page="pagination.current_page"
      :label-texts="{
            multipleItems: Translator.trans('pager.amount.multiple.items'),
            multipleLabel: Translator.trans('pager.amount.multiple.label',
              {results: pagination.total, items: Translator.trans('pager.amount.multiple.items')}),
            multipleOf: Translator.trans('pager.amount.multiple.of')
          }"
      :limits="pagination.limits"
      :total-pages="pagination.total_pages"
      :total-items="pagination.total"
      :per-page="pagination.count"
      @page-change="handlePageChange"
      @size-change="handleSizeChange"/>

    <export-modal
      v-if="hasPermission('feature_assessmenttable_export')"
      :has-selected-elements="Object.keys(selectedElements).length > 0"
      :procedure-id="procedureId"
      :options="exportOptions"
      view="original_statements" />

    <dp-map-modal
      ref="mapModal"
      :procedure-id="procedureId" />

    <slot
      name="filter"
      v-bind="{ procedureId, allItemsOnPageSelected, copyStatements }" />

    <!-- If there are statements, display statement list -->
    <dp-loading
      v-if="isLoading"
      class="u-mt u-ml" />

    <table
      :aria-label="Translator.trans('statements.original')"
      v-else-if="Object.keys(statements).length"
      class="c-at-orig">
      <colgroup>
        <col class="w-[10%]">
        <col class="w-[10%] text-left">
        <col
          span="3"
          class="w-1/4">
        <col class="w-[5%]">
      </colgroup>
      <thead class="c-at-orig__header">
        <tr>
          <th
            scope="col"
            v-tooltip="Translator.trans('statement.id')">
            {{ Translator.trans('id') }}
          </th>
          <th
            scope="col"
            v-tooltip="Translator.trans('statement.date.submitted')">
            {{ Translator.trans('date') }}
          </th>
          <th scope="col">
            {{ Translator.trans('submitter.invitable_institution') }}
          </th>
          <th scope="col">
            {{ Translator.trans('document') }}
          </th>
          <th scope="col">
            {{ Translator.trans('procedure.public.phase') }}
          </th>
          <th />
        </tr>
      </thead>
      <tbody>
        <original-statements-table-item
          v-for="(statement, idx) in statements"
          :current-table-view="currentTableView"
          :is-selected="getSelectionStateById(statement.id)"
          :key="idx"
          :procedure-id="procedureId"
          :statement-id="statement.id"
          @add-to-selection="addToSelectionAction"
          @remove-from-selection="removeFromSelectionAction" />
      </tbody>
    </table>

    <dp-inline-notification
      v-else
      :message="Translator.trans('explanation.noentries')"
      type="info" />
  </form>
</template>

<script>
import { DpLoading, DpPager } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import changeUrlforPager from '../assessmentTable/utils/changeUrlforPager'
import ExportModal from '@DpJs/components/statement/assessmentTable/ExportModal'
import OriginalStatementsTableItem from './OriginalStatementsTableItem'

export default {
  name: 'OriginalStatementsTable',

  components: {
    DpLoading,
    ExportModal,
    DpInlineNotification: async () => {
      const { DpInlineNotification } = await import('@demos-europe/demosplan-ui')
      return DpInlineNotification
    },
    DpMapModal: () => import(/* webpackChunkName: "dp-map-modal" */ '@DpJs/components/statement/assessmentTable/DpMapModal'),
    DpPager,
    OriginalStatementsTableItem
  },

  props: {
    csrfToken: {
      type: String,
      required: true
    },

    exportOptions: {
      type: Object,
      required: false,
      default: () => ({})
    },

    initFilterHash: {
      type: String,
      required: true
    },

    initPagination: {
      type: Object,
      required: false,
      default: () => ({
        current_page: 1,
        per_page: 25,
        count: 1,
        limits: () => [10, 25, 50]
      })
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      action: '',
      allCheckboxesToggled: false,
      currentTableView: 'expanded',
      filterHash: this.initFilterHash,
      isLoading: true,
      pageSize: this.initPagination.count
    }
  },

  computed: {
    ...mapState('Statement', [
      'statements',
      'selectedElements',
      'pagination'
    ]),

    ...mapGetters('Statement', [
      'getSelectionStateById'
    ]),

    allItemsOnPageSelected () {
      return Object.keys(this.statements).length === 0 ? false : Object.keys(this.statements).every(stn => Object.keys(this.selectedElements).includes(stn))
    }
  },

  methods: {
    ...mapActions('AssessmentTable', [
      'applyBaseData'
    ]),

    ...mapActions('Statement', [
      'addToSelectionAction',
      'getStatementAction',
      'removeFromSelectionAction',
      'resetSelection',
      'setSelectionAction'
    ]),

    ...mapMutations('Statement', [
      'updatePagination',
      'updatePersistStatementSelection'
    ]),

    ...mapMutations('AssessmentTable', [
      'setProperty'
    ]),

    /**
     * Update the Url matching the pager
     * @param pager
     */
    changeUrl (pager) {
      const newUrl = changeUrlforPager(pager)

      window.history.pushState({
        html: newUrl.join('?'),
        pageTitle: document.title
      }, document.title, newUrl.join('?'))
    },

    copyStatements () {
      if (dpconfirm(Translator.trans('check.entries.marked.copy'))) {
        this.action = 'copy'
        this.$nextTick(() => {
          this.$refs.bpform.submit()
        })
      }
    },

    handlePageChange (newPage) {
      const tmpPager = Object.assign(this.pagination, {
        current_page: newPage,
        count: this.pagination.per_page
      })
      this.updatePagination(tmpPager)
      this.changeUrl(tmpPager)
      this.setProperty({
        prop: 'isLoading',
        val: true
      })
      this.triggerApiCallForStatements()
    },

    handleSizeChange (newSize) {
      this.pageSize = newSize
      this.$nextTick(() => {
        this.$refs.bpform.submit()
      })
    },

    toggleAllCheckboxes () {
      const status = this.allCheckboxesToggled
      const statements = JSON.parse(JSON.stringify(this.statements))
      const payload = { status, statements }

      if (status) {
        for (const statementId in statements) {
          statements[statementId] = {
            id: statementId,
            movedToProcedure: (statements[statementId].movedToProcedureId !== ''),
            assignee: statements[statementId].assignee,
            extid: (statements[statementId].parentId && statements[statementId].originalId && statements[statementId].originalId !== statements[statementId].parentId) ? Translator.trans('copyof') + ' ' + statements[statementId].externId : statements[statementId].externId,
            isCluster: statements[statementId].isCluster
          }
        }
        payload.statements = statements
      }

      this.setSelectionAction(payload)
    },

    triggerApiCallForStatements () {
      // Trigger the get-action for all the required statements
      return this.getStatementAction({
        filterHash: this.filterHash,
        procedureId: this.procedureId,
        pagination: this.pagination,
        view_mode: '',
        sort: ''
      })
    },

    updateFilterHash (hash) {
      if (hash.length === 12) {
        this.filterHash = hash
        const url = window.location.href.split('?')
        url[0] = url[0].substring(0, url[0].length - 12) + hash
        window.history.pushState({ html: url.join('?'), pageTitle: document.title }, document.title, url.join('?'))
      }
    }
  },

  mounted () {
    // Disable sessionStorage for statements within this view.
    this.updatePersistStatementSelection(false)

    this.updatePagination(this.initPagination)
    this.changeUrl(this.initPagination)

    this.applyBaseData(this.procedureId)
      .then(() => {
        return this.triggerApiCallForStatements()
      })
      .then(() => {
        this.isLoading = false
      })

    this.$root.$on('toggle-select-all', () => {
      this.allCheckboxesToggled = !this.allCheckboxesToggled
      this.toggleAllCheckboxes()
    })

    this.$root.$on('current-table-view', (currentTableView) => {
      this.currentTableView = currentTableView
    })
  }
}
</script>
