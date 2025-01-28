<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <!-- Whenever there is an update to the assessment table, the hash must not be sent to the server -->
  <form
    id="start"
    name="bpform"
    :action="Routing.generate('dplan_assessmenttable_view_table', { procedureId: procedureId, filterHash: initFilterHash })"
    method="post"
    :data-statement-admin-container="procedureId"
    v-cloak>
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
      value="">
    <input
      type="hidden"
      name="r_headStatement"
      value="">
    <input
      type="hidden"
      name="r_clusterName"
      value="">

    <!-- These are set by the export modal, do not remove -->
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
      name="r_view_mode"
      :value="viewMode">
    <input
      type="hidden"
      name="searchFields">
    <input
      type="hidden"
      name="currentTableSort">

    <!-- Top pager -->
    <dp-pager
      v-if="pagination.hasOwnProperty('current_page') && hasPermission('feature_assessmenttable_use_pager')"
      :class="{ 'invisible': isLoading }"
      class="u-pt-0_5 text-right u-1-of-1"
      :current-page="pagination.current_page"
      :key="`pager1_${pagination.current_page}_${pagination.count}`"
      :limits="pagination.limits"
      :label-texts="{
            multipleItems: Translator.trans('pager.amount.multiple.items'),
            multipleLabel: Translator.trans('pager.amount.multiple.label',
              {results: pagination.total, items: Translator.trans('pager.amount.multiple.items')}),
            multipleOf: Translator.trans('pager.amount.multiple.of')
          }"
      :total-pages="pagination.total_pages"
      :total-items="pagination.total"
      :per-page="pagination.count"
      @page-change="handlePageChange"
      @size-change="handleSizeChange" />

    <!-- Export modal -->
    <export-modal
      v-if="hasPermission('feature_assessmenttable_export')"
      ref="exportModal"
      :current-table-sort="sort.value || ''"
      :has-selected-elements="selectedElementsLength > 0"
      :options="assessmentExportOptions"
      :procedure-id="procedureId"
      view="assessment_table"
      :view-mode="viewMode"
      @submit="resetStatementSelection" />

    <consolidate-modal
      v-if="hasPermission('feature_statement_cluster') && consolidateModal.show"
      :procedure-id="procedureId"
      @scrollto="scrollAndAnimate" />

    <copy-statement-modal
      v-if="hasPermission('feature_statement_move_to_procedure') && copyStatementModal.show"
      :accessible-procedures="accessibleProcedures"
      :inaccessible-procedures="inaccessibleProcedures"
      :procedure-id="procedureId" />

    <dp-move-statement-modal
      v-if="hasPermission('feature_statement_move_to_procedure')"
      :accessible-procedures="accessibleProcedures"
      :inaccessible-procedures="inaccessibleProcedures"
      :procedure-id="procedureId" />

    <assign-entity-modal
      v-if="hasPermission('feature_statement_assignment') && assignEntityModal.show"
      :procedure-id="procedureId"
      :current-user-id="currentUserId"
      :authorised-users="authorisedUsers" />

    <dp-map-modal
      ref="mapModal"
      :procedure-id="procedureId" />

    <!-- filters + sorting -->
    <assessment-table-filter
      :has-changed-statements="hasChangedStatements"
      :assessment-export-options="assessmentExportOptions"
      :sorting-options="sortingOptionsForDropdown"
      :view-mode="viewMode"
      ref="filter"
      @handle-sort-change="option => handleSortChange(option)" />

    <!-- Version History Slidebar -->
    <dp-slidebar>
      <dp-version-history :procedure-id="procedureId" />
    </dp-slidebar>

    <!-- If there are statements, display statement list -->
    <dp-loading
      v-if="isLoading"
      class="u-mt u-ml" />

    <ul
      v-if="false === isLoading && hasStatements"
      class="o-list o-list--card u-mb"
      data-cy="statementList">
      <!-- The hidden checkboxes are needed for actions that require "real form data", otherwise
      the selected state of at items could also be handled by vuex store. -->
      <li
        v-for="element in selectedElements"
        :key="`selectedElement:${element.id}`">
        <input
          class="sr-only"
          name="item_check[]"
          type="checkbox"
          :id="element.id + ':item_check[]'"
          :checked="true"
          :data-extid="element.extid"
          :value="element.id">
        <div :data-assigned="element.editable" />
      </li>

      <li
        v-for="element in selectedFragments"
        :key="`selectedFragment:${element.id}`">
        <input
          class="sr-only"
          name="item_check[]"
          type="checkbox"
          :key="`selectedFragmentInput:${element.id}`"
          :id="element.id + ':item_check[]'"
          :checked="true"
          :value="element.id">
        <div :data-assigned="element.assignee.id === currentUserId" />
      </li>

      <assessment-table-group-list
        v-if="viewMode === 'view_mode_tag' || viewMode === 'view_mode_elements'"
        :csrf-token="csrfToken"
        :form-definitions="formDefinitions" />
      <!-- Loop statements in default viewMode -->
      <template
        v-else
        v-for="(statement, key, index) in statements">
        <dp-assessment-table-card
          :csrf-token="csrfToken"
          :data-cy="`statementCard:index:${index}`"
          :ref="'itemdisplay_' + statement.id"
          :key="`statement:${statement.id}`"
          class="o-list__item"
          :init-statement="{}"
          :statement-procedure-id="statement.procedureId"
          :statement-id="statement.id"
          :is-selected="getSelectionStateById(statement.id)"
          @statement:updated="hasChangedStatements = true"
          @statement:addToSelection="addToSelectionAction"
          @statement:removeFromSelection="removeFromSelectionAction" />
      </template>
    </ul>

    <!-- If there are no statements: -->
    <div v-if="!isLoading && !hasStatements">
      <template v-if="searchTerm !== '' || filterSet.activeFilters.length">
        <!-- empty state message with link to list of original statements -->
        <p
          v-if="filterSet.userWarning"
          class="flash flash-warning"
          v-cleanhtml="Translator.trans(filterSet.userWarning)" />

        <p
          v-else
          class="flash flash-info">
          <i
            class="fa fa-info-circle"
            aria-hidden="true" />
          {{ Translator.trans('explanation.considerationtable.empty.filtered') }}
          <br>
          <a :href="Routing.generate('dplan_assessmenttable_view_original_table', { procedureId: procedureId, filterHash: initFilterHash })">
            {{ Translator.trans('check.view.original') }}
          </a>
        </p>
      </template>
      <template v-else>
        <p class="flash flash-info">
          <i
            class="fa fa-info-circle"
            aria-hidden="true" />
          {{ Translator.trans('statements.none') }}
        </p>
      </template>
    </div>

    <!-- bottom pager -->
    <dp-pager
      v-if="pagination.hasOwnProperty('current_page') && hasPermission('feature_assessmenttable_use_pager')"
      :key="`pager2_${pagination.current_page}_${pagination.count}`"
      :class="{ 'invisible': isLoading }"
      class="u-pb-0_5 text-right"
      :current-page="pagination.current_page"
      :total-pages="pagination.total_pages"
      :total-items="pagination.total"
      :per-page="pagination.count"
      :limits="pagination.limits"
      @page-change="handlePageChange"
      @size-change="handleSizeChange" />
  </form>
</template>

<script>
import { CleanHtml, DpLoading, DpPager, handleResponseMessages, Stickier } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import AssessmentTableFilter from '@DpJs/components/statement/assessmentTable/AssessmentTableFilter'
import changeUrlforPager from './utils/changeUrlforPager'
import DpAssessmentTableCard from '@DpJs/components/statement/assessmentTable/DpAssessmentTableCard'
import ExportModal from '@DpJs/components/statement/assessmentTable/ExportModal'
import { scrollTo } from 'vue-scrollto'

/*
 * @refs T12284 check if the statements are in sync with the view (ES is just near Realtime )
 * let checkProcessingStatus
 */

export default {
  name: 'DpTable',

  components: {
    AssessmentTableGroupList: () => import(/* webpackChunkName: "assessment-table-group-list" */ './TocView/AssessmentTableGroupList'),
    AssessmentTableFilter,
    AssignEntityModal: () => import(/* webpackChunkName: "assign-entity-modal" */ '@DpJs/components/statement/assessmentTable/AssignEntityModal'),
    ConsolidateModal: () => import(/* webpackChunkName: "consolidate-modal" */ '@DpJs/components/statement/assessmentTable/ConsolidateModal'),
    CopyStatementModal: () => import(/* webpackChunkName: "copy-statement-modal" */ '@DpJs/components/statement/assessmentTable/CopyStatementModal'),
    ExportModal,
    DpLoading,
    DpMapModal: () => import(/* webpackChunkName: "dp-map-modal" */ '@DpJs/components/statement/assessmentTable/DpMapModal'),
    DpMoveStatementModal: () => import(/* webpackChunkName: "dp-move-statement-modal" */ '@DpJs/components/statement/assessmentTable/DpMoveStatementModal'),
    DpPager,
    DpSlidebar: async () => {
      const { DpSlidebar } = await import('@demos-europe/demosplan-ui')
      return DpSlidebar
    },
    DpAssessmentTableCard,
    DpVersionHistory: () => import(/* webpackChunkName: "dp-version-history" */ '@DpJs/components/statement/statement/DpVersionHistory')
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    accessibleProcedureIds: {
      required: false,
      type: Array,
      default: () => []
    },

    appliedFilters: {
      required: false,
      type: Array,
      default: () => ([])
    },

    //  Export options that define which formats / fields to display
    assessmentExportOptions: {
      required: true,
      type: Object
    },

    authorisedUsers: {
      required: false,
      type: Array,
      default: () => ([])
    },

    csrfToken: {
      type: String,
      required: true
    },

    currentUserId: {
      required: false,
      type: String,
      default: ''
    },

    currentUserName: {
      required: false,
      type: String,
      default: ''
    },

    exactSearch: {
      type: Boolean,
      default: false
    },

    filterSet: {
      type: Object,
      default: () => ({})
    },

    formDefinitions: {
      type: Object,
      required: false,
      default: () => ({})
    },

    initFilterHash: {
      required: true,
      type: String
    },

    initPagination: {
      required: false,
      type: [Object, undefined],
      default: undefined
    },

    initSort: {
      required: false,
      type: String,
      default: ''
    },

    procedureId: {
      required: true,
      type: String
    },

    procedureStatementPriorityArea: {
      required: false,
      type: Boolean,
      default: false
    },

    publicParticipationPublicationEnabled: {
      required: false,
      type: Boolean,
      default: false
    },

    searchFields: {
      type: Array,
      default: () => []
    },

    searchTerm: {
      type: String,
      default: ''
    },

    sortingOptions: {
      required: false,
      type: Array,
      default: () => []
    },

    statementFormDefinitions: {
      required: true,
      type: Object
    },

    /**
     * Mode of 'Gliederung' (ordered by tags, elements, etc.)
     */
    viewMode: {
      required: false,
      type: String,
      default: ''
    }
  },

  data () {
    return {
      filterHash: this.initFilterHash,
      hasChangedStatements: false,
      processingData: false,
      processingDataNotConfirmed: false
    }
  },

  computed: {
    ...mapState('AssessmentTable', [
      'assessmentBase',
      'assessmentBaseLoaded',
      'currentTableView',
      'sort'
    ]),

    ...mapGetters('AssessmentTable', [
      'assignEntityModal',
      'consolidateModal',
      'copyStatementModal',
      'isLoading'
    ]),

    ...mapState('Statement', [
      'selectedElements',
      'pagination'
    ]),

    ...mapGetters('Statement', [
      'getSelectionStateById',
      'selectedElementsFromOtherPages',
      'selectedElementsLength',
      'statements',
      'statementsInOrder'
    ]),

    ...mapGetters('Fragment', [
      'selectedFragments'
    ]),

    accessibleProcedures () {
      return this.assessmentBase.accessibleProcedures
    },

    hasStatements () {
      return this.statements && Object.keys(this.statements).length > 0
    },

    inaccessibleProcedures () {
      return this.assessmentBase.inaccessibleProcedures
    },

    sortingOptionsForDropdown () {
      const dropdownOptions = []
      const ascPrefix = Translator.trans('ascending').charAt(0).toUpperCase() + Translator.trans('ascending').substring(1)
      const descPrefix = Translator.trans('descending').charAt(0).toUpperCase() + Translator.trans('descending').substring(1)

      this.sortingOptions.forEach(option => {
        const hasSortingDirection = option.value !== 'forPoliticians'
        dropdownOptions.push({
          value: option.value,
          label: hasSortingDirection ? ascPrefix + ' nach ' + Translator.trans(option.translation) : Translator.trans(option.translation)
        })
        if (hasSortingDirection) {
          dropdownOptions.push({
            value: '-' + option.value,
            label: descPrefix + ' nach ' + Translator.trans(option.translation)
          })
        }
      })
      return dropdownOptions || []
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
      'updateStatementAction'
    ]),

    ...mapActions('Statement', {
      resetStatementSelection: 'resetSelection',
      setProcedureIdForStatement: 'setProcedureIdAction',
      setSelectedStatements: 'setSelectedElementsAction'
    }),

    ...mapActions('Fragment', {
      resetFragmentSelection: 'resetSelection',
      setProcedureIdForFragment: 'setProcedureIdAction',
      setSelectedFragments: 'setSelectedFragmentsAction'
    }),

    ...mapMutations('AssessmentTable', [
      'setAssessmentBaseProperty',
      'setModalProperty',
      'setProperty'
    ]),

    ...mapMutations('Statement', [
      'updatePagination'
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
      const tmpPager = Object.assign(this.pagination, { count: newSize })
      this.updatePagination(tmpPager)
      this.changeUrl(tmpPager)
      this.setProperty({
        prop: 'isLoading',
        val: true
      })
      this.triggerApiCallForStatements()
    },

    handleSortChange (newVal) {
      this.setProperty({
        prop: 'sort',
        val: newVal
      })
      this.triggerApiCallForStatements()
    },

    renderMessagesFromStorage () {
      const messagesToRender = window.sessionStorage.getItem('messagesToRender')
      if (messagesToRender) {
        handleResponseMessages(JSON.parse(messagesToRender))
        window.sessionStorage.removeItem('messagesToRender')
      }
    },

    scrollAndAnimate (id) {
      scrollTo(id, {
        offset: -120,
        onDone: () => {
          if (id !== '') {
            if (id.charAt(0) === '#') {
              id = id.substr(1)
            }
            // Add border to the statement, to which we scroll (and remove the border after first click)
            document.getElementById(id).classList.add('c-at-item__focus-border')
            const removeBorder = () => {
              document.getElementById(id).classList.remove('c-at-item__focus-border')
              document.removeEventListener('click', removeBorder)
            }
            document.addEventListener('click', removeBorder)

            /*
             * If 'save and return' button has been clicked we want to highlight the statement from hash
             * to make it work we need an element with id just like window.hash and inside nested element to be highlighted (with data-add-animation)
             */
            if (window.sessionStorage.saveAndReturn) {
              const editedStatement = document.getElementById(id)
              const firstChildDiv = editedStatement.querySelector('[data-add-animation]')

              firstChildDiv.classList.add('animation--bg-highlight-grey--light-1')
              window.sessionStorage.removeItem('saveAndReturn')
            }
          }
        }
      })
    },

    setInitialData () {
      const sortVal = this.sortingOptionsForDropdown.find(opt => opt.value === this.initSort) || {}
      this.setProperty({
        prop: 'sort',
        val: sortVal
      })

      this.setProperty({
        prop: 'searchTerm',
        val: this.searchTerm
      })

      this.setProperty({
        prop: 'statementFormDefinitions',
        val: this.statementFormDefinitions
      })

      this.setAssessmentBaseProperty({
        prop: 'exactSearch',
        val: this.exactSearch
      })

      this.setProperty({
        prop: 'filterSet',
        val: this.filterSet
      })

      // Set current user id in store to be able to check editable state of selected elements later on
      this.setProperty({
        prop: 'currentUserId',
        val: this.currentUserId
      })

      this.setProperty({
        prop: 'viewMode',
        val: this.viewMode
      })

      this.setProperty({
        prop: 'publicParticipationPublicationEnabled',
        val: this.publicParticipationPublicationEnabled
      })

      this.setProperty({
        prop: 'procedureStatementPriorityArea',
        val: this.procedureStatementPriorityArea
      })

      this.setProperty({ prop: 'accessibleProcedureIds', val: this.accessibleProcedureIds })

      const hasOnlyFragmentFilters = this.appliedFilters.length ? typeof (this.appliedFilters.find(filter => filter.type !== 'fragment')) === 'undefined' : false
      if (hasOnlyFragmentFilters) {
        this.setProperty({ prop: 'currentTableView', val: 'fragments' })
      }
    },

    setSelectedElementsMethod (response) {
      // Set the initial state for checked statements
      this.setSelectedStatements(this.procedureId)
      // If statements are checked it's better to reset fragments selection just to make sure, that no statements and fragments are checked at the same time
      if (this.selectedElements && this.selectedElementsLength > 0 && hasPermission('area_statements_fragment')) {
        this.resetFragmentSelection()
      }
      if (hasPermission('area_statements_fragment')) {
        this.$store.commit('Fragment/setInitFragments', response.meta.fragmentAssignments)
        this.setSelectedFragments(response.meta.fragmentAssignments)
          .then(() => {
            // And we do the same with statements (making sure not to have statements and fragments checked)
            if (this.selectedFragments && Object.keys(this.selectedFragments).length > 0) {
              this.resetStatementSelection()
            }
          })
      }
    },

    triggerApiCallForStatements () {
      this.setProperty({
        prop: 'isLoading',
        val: true
      })
      // Trigger the get-action for all the required statements
      this.getStatementAction({
        filterHash: this.filterHash,
        hasPriorityArea: this.procedureStatementPriorityArea,
        procedureId: this.procedureId,
        pagination: this.pagination,
        sort: this.sort.value
      })
        .then((response) => {
          this.updateFilterHash(response.meta.filterHash)
          this.setSelectedElementsMethod(response)
          this.setProperty({
            prop: 'isLoading',
            val: false
          })
        })
        .catch(() => {
          this.setProperty({
            prop: 'isLoading',
            val: false
          })
        })

      if (window.location.hash) {
        const hash = window.location.hash.includes('?') ? window.location.hash.substr(0, window.location.hash.indexOf('?')) : window.location.hash
        this.waitForElement(hash)
          .then(() => {
            this.scrollAndAnimate(hash)
          })
      }
      this.renderMessagesFromStorage()
    },

    updateFilterHash (hash) {
      if (hash.length === 12) {
        this.filterHash = hash
        const url = window.location.href.split('?')
        url[0] = url[0].substring(0, url[0].length - 12) + hash
        window.history.pushState({
          html: url.join('?'),
          pageTitle: document.title
        }, document.title, url.join('?'))
      }
    },

    waitForElement (selector) {
      return new Promise(resolve => {
        const element = document.querySelector(selector)

        if (element) {
          resolve(element)
        }

        new MutationObserver((mutationRecords, observer) => {
          // Query for elements matching the specified selector
          Array.from(document.querySelectorAll(selector)).forEach(element => {
            resolve(element)
            // Once we have resolved we don't need the observer anymore.
            observer.disconnect()
          })
        })
          .observe(document.documentElement, {
            childList: true,
            subtree: true
          })
      })
    }
  },

  mounted () {
    // After we pass the pagination-data here we handle it through the JSON-API and the dp-sliding-pagination-component
    this.updatePagination(this.initPagination)
    this.changeUrl(this.initPagination)

    this.setInitialData()

    // Do stuff to fill the stores
    this.applyBaseData(this.procedureId)
      .then(() => {
        // Set the procedureId in stores (statement and fragment)
        this.setProcedureIdForStatement(this.procedureId)
          .then(() => {
            this.setAssessmentBaseProperty({
              prop: 'initFilterHash',
              val: this.initFilterHash
            })

            this.setAssessmentBaseProperty({
              prop: 'searchFields',
              val: this.searchFields
            })

            this.setAssessmentBaseProperty({
              prop: 'appliedFilters',
              val: this.appliedFilters
            })

            if (hasPermission('area_statements_fragment')) {
              this.setProcedureIdForFragment(this.procedureId)
            }

            this.triggerApiCallForStatements()
          })
          .then(() => {
            /*
             * Initialize fixed header after all data has been processed (a.k.a. pager has been rendered)
             * to ensure dom manipulation of Stickier is executed last.
             */
            this.stickyHeader = new Stickier(this.$refs.filter.$refs.header, this.$el, 0)

            this.$root.$emit('assessment-table-loaded')
          })
      })

    this.$root.$on('update-assessment-table', () => {
      this.triggerApiCallForStatements()
    })

    this.$root.$on('update-pagination-assessment-table', () => {
      this.updatePagination(this.initPagination)
    })
  },

  beforeDestroy () {
    this.stickyHeader.destroy()
  }
}
</script>
