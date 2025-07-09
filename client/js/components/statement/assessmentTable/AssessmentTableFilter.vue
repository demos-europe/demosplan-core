<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <!-- show active filters -->
    <fieldset
      v-if="hasActiveFilters || searchTerm !== ''"
      class="u-mt-0_5 u-pb-0 border--bottom">
      <legend
        class="sr-only"
        v-text="Translator.trans('filter.searchterm.active')" />

      <div
        v-if="searchTerm !== ''"
        @click="setProperty({ prop: 'showSearchModal', val: true })">
        <div class="layout__item u-1-of-6 u-pl-0">
          <label
            id="searchTermLabel"
            class="u-mv-0_25">
            {{ Translator.trans('searchterm') }}:
          </label>
        </div><!--
     --><div
          class="layout__item u-4-of-6 u-mt-0_25 cursor-pointer"
          aria-labelledby="searchTermLabel">
          {{ searchTerm }}
        </div>
      </div>

      <div
        v-if="hasActiveFilters"
        @click="setProperty({ prop: 'showFilterModal', val: true })">
        <div class="layout__item u-1-of-6 u-pl-0">
          <label
            class="u-mv-0_25"
            data-cy="filterActive">
            {{ Translator.trans('filter.active') }}:
          </label>
        </div><!--
     --><div
          class="layout__item u-4-of-6 u-mt-0_25 cursor-pointer"
          :aria-label="Translator.trans('aria.maximize_filters')">
          {{ filterSet.activeFilters.join(', ') }}
        </div>
      </div>
      <dp-inline-notification
        v-if="hasActiveFilters && hasChangedStatements"
        class="mt-3 mb-2"
        :message="Translator.trans('filter.settings_not_current')"
        type="warning" />
    </fieldset>

    <div
      class="c-at__controls o-sticky o-sticky--border space-stack-xs u-pt-0_5 u-pb-0_25"
      ref="header">
      <search-and-sorting
        :search-term="searchTerm"
        @exportModal:toggle="tab => $emit('exportModal:toggle', tab)" />

      <div class="flex items-center space-inline-m">
        <!-- mark all -->
        <label
          class="o-link--default u-mb-0"
          :class="{'color--grey': areFragmentsSelected}">
          <input
            class="u-mr-0"
            type="checkbox"
            v-model="allItemsOnPageSelected"
            data-cy="ToggleAllCheckboxes"
            :disabled="areFragmentsSelected"
            :title="areFragmentsSelected ? Translator.trans('unselect.entity.first', {entity: Translator.trans('statements')}) : null">
          {{ Translator.trans('visible.entries') }}
        </label>

        <!-- Export Actions -->
        <template v-if="hasPermission('feature_assessmenttable_export')">
          <button
            v-if="exportModalOptions.exportOptions === 1"
            :disabled="selectedElementsLength > 0 || hasPermission('feature_statements_fragment_add') && Object.keys(selectedFragments).length > 0"
            class="c-actionmenu__trigger"
            type="button"
            aria-haspopup="true"
            aria-expanded="false"
            @click.prevent="$emit('exportModal:toggle', exportModalOptions.tab)">
            <i
              class="fa fa-share-square u-mr-0_125"
              aria-hidden="true" />
            {{ Translator.trans(exportModalOptions.buttonLabelSingle) }}
          </button>
          <template v-else>
            <div
              class="c-actionmenu"
              data-actionmenu>
              <button
                :disabled="selectedElementsLength > 0 || hasPermission('feature_statements_fragment_add') && Object.keys(selectedFragments).length > 0"
                class="c-actionmenu__trigger"
                data-cy="exportModal:open"
                aria-haspopup="true"
                aria-expanded="false"
                type="button">
                <i
                  class="fa fa-share-square u-mr-0_125"
                  aria-hidden="true" />
                {{ Translator.trans('export') }}
              </button>

              <div
                class="c-actionmenu__menu"
                role="menu"
                v-show="false === (selectedElementsLength > 0 || hasPermission('feature_statements_fragment_add') && Object.keys(selectedFragments).length > 0)">
                <button
                  v-for="option in Object.values(filteredAssessmentExportOptions)"
                  :key="Object.keys(option)[0]"
                  class="c-actionmenu__menuitem"
                  :data-cy="`statementsExport:${Object.values(option)[0].buttonLabel}`"
                  data-actionmenu-menuitem
                  role="menuitem"
                  tabindex="-1"
                  @click.prevent="$emit('exportModal:toggle', Object.keys(option)[0])">
                  {{ Translator.trans(Object.values(option)[0].buttonLabel) }}
                </button>
              </div>
            </div>
          </template>
        </template>

        <!-- View modes (Grouped lists) -->
        <div
          v-if="hasPermission('feature_assessmenttable_structural_view_mode')"
          class="c-actionmenu"
          data-actionmenu>
          <button
            class="c-actionmenu__trigger"
            type="button"
            aria-haspopup="true"
            aria-expanded="false">
            <i
              class="fa fa-list-ul u-mr-0_125"
              aria-hidden="true" />
            {{ Translator.trans('assessmenttable.view.mode') }}
          </button>
          <div
            class="c-actionmenu__menu"
            role="menu">
            <button
              v-for="(mode, idx) in viewModes"
              :key="`${mode.type}:${idx}`"
              class="c-actionmenu__menuitem"
              :class="{ 'is-active': viewMode === mode.type }"
              role="menuitem"
              data-actionmenu-menuitem
              tabindex="-1"
              @click.prevent="toggleViewMode(mode.type)">
              {{ Translator.trans(mode.label) }}
            </button>
          </div>
        </div>

        <!-- List modes -->
        <div
          class="c-actionmenu"
          data-actionmenu>
          <button
            class="c-actionmenu__trigger"
            data-cy="actionMenuTrigger"
            type="button"
            aria-haspopup="true"
            aria-expanded="false">
            <i
              class="fa fa-th-large u-mr-0_125"
              aria-hidden="true" />
            {{ Translator.trans('display') }}
          </button>
          <div
            class="c-actionmenu__menu"
            role="menu">
            <button
              class="c-actionmenu__menuitem"
              data-cy="displayListExpanded"
              role="menuitem"
              tabindex="-1"
              data-actionmenu-menuitem
              :data-actionmenu-current="currentTableView === 'statement' ? true : null"
              :class="{'pointer-events-none': !assessmentBaseLoaded }"
              @click.prevent="setProperty({ prop: 'currentTableView', val: 'statement' })">
              {{ Translator.trans(hasPermission('area_statements_fragment') ? 'statements' : 'display.list.expanded') }}
            </button>

            <template v-if="hasPermission('area_statements_fragment')">
              <button
                class="c-actionmenu__menuitem"
                data-cy="displayFragments"
                role="menuitem"
                tabindex="-1"
                data-actionmenu-menuitem
                :data-actionmenu-current="currentTableView === 'fragments' ? true : null"
                :class="{'pointer-events-none': !assessmentBaseLoaded }"
                @click.prevent="setProperty({ prop: 'currentTableView', val: 'fragments' })">
                {{ Translator.trans('fragments') }}
              </button>
              <button
                class="c-actionmenu__menuitem"
                data-cy="displayList"
                role="menuitem"
                tabindex="-1"
                data-actionmenu-menuitem
                :data-actionmenu-current="currentTableView === 'collapsed' ? true : null"
                :class="{'pointer-events-none': !assessmentBaseLoaded }"
                @click.prevent="setProperty({ prop: 'currentTableView', val: 'collapsed' })">
                {{ Translator.trans('display.list') }}
              </button>
            </template>
            <template v-else>
              <button
                class="c-actionmenu__menuitem"
                data-cy="displayListCollapsed"
                role="menuitem"
                tabindex="-1"
                data-actionmenu-menuitem
                :data-actionmenu-current="currentTableView === 'collapsed' ? true : null"
                :class="{'pointer-events-none': !assessmentBaseLoaded }"
                @click.prevent="setProperty({ prop: 'currentTableView', val: 'collapsed' })">
                {{ Translator.trans('display.list.collapsed') }}
              </button>
            </template>
          </div>
        </div>

        <!-- Sorting -->
        <div class="ml-auto">
          <div
            v-if="searchTerm.length === 0 && !viewModeActivated"
            class="c-actionmenu"
            data-actionmenu>
            <button
              class="c-actionmenu__trigger"
              data-cy="assessmentTableFilter:sorting"
              aria-haspopup="true"
              aria-expanded="false">
              <i
                class="fa fa-sort u-mr-0_25"
                aria-hidden="true" />
              {{ sort.label }}
            </button>
            <div
              class="c-actionmenu__menu"
              role="menu">
              <button
                v-for="(option, i) in sortingOptions"
                :key="`${option.value}:${i}`"
                class="c-actionmenu__menuitem"
                :class="{'is-active': option.value === sort.value}"
                :data-cy="`assessmentTableFilter:${option.label}`"
                data-actionmenu-menuitem
                role="menuitem"
                tabindex="-1"
                @click.prevent="$emit('handle-sort-change', option)">
                {{ option.label }}
              </button>
            </div>
          </div>
          <!-- With activated view modes, results are sorted accordingly. If a search term is given, results are sorted
               by relevance. Both cases do not need additional sorting, so a hint is displayed then. -->
          <template v-else>
            <i
              class="fa fa-sort u-mr-0_25"
              aria-hidden="true" />
            <span>
              {{ sortLabel }}
            </span>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { DpInlineNotification } from '@demos-europe/demosplan-ui'
import SearchAndSorting from '@DpJs/components/statement/assessmentTable/TocView/SearchAndSorting'

export default {
  name: 'AssessmentTableFilter',

  components: {
    DpInlineNotification,
    SearchAndSorting
  },

  props: {
    //  Export options that define which formats / fields to display
    assessmentExportOptions: {
      required: true,
      type: Object
    },

    hasChangedStatements: {
      required: false,
      type: Boolean,
      default: false
    },

    sortingOptions: {
      type: Array,
      required: false,
      default: () => []
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

  emits: [
    'exportModal:toggle',
    'handle-sort-change'
  ],

  data () {
    return {
      viewModes: [
        { type: 'view_mode_default', label: 'assessmenttable.view.mode.default' },
        { type: 'view_mode_tag', label: 'assessmenttable.view.mode.tags' },
        { type: 'view_mode_elements', label: 'assessmenttable.view.mode.elements' }
      ]
    }
  },

  computed: {
    ...mapGetters('Fragment', [
      'selectedFragments'
    ]),

    ...mapGetters('Statement', [
      'selectedElementsLength',
      'statements'
    ]),

    ...mapState('AssessmentTable', [
      'assessmentBaseLoaded',
      'currentTableView',
      'filterSet',
      'searchTerm',
      'sort'
    ]),

    ...mapState('Statement', [
      'selectedElements'
    ]),

    allItemsOnPageSelected: {
      get () {
        return Object.keys(this.statements).length === 0 ? false : Object.keys(this.statements).every(stn => Object.keys(this.selectedElements).includes(stn))
      },
      set (status) {
        this.toggleAllCheckboxes(status)
      }
    },

    areFragmentsSelected () {
      return hasPermission('area_statements_fragment') && Object.keys(this.selectedFragments).length > 0
    },

    exportModalOptions () {
      const options = {
        exportOptions: 0,
        tab: '',
        buttonLabelSingle: ''
      }

      Object.entries(this.assessmentExportOptions).forEach(([key, val]) => {
        if (val) {
          options.exportOptions = options.exportOptions + 1
          options.tab = key
          options.buttonLabelSingle = val.buttonLabelSingle
        }
      })

      return options
    },

    filteredAssessmentExportOptions () {
      return Object.entries(this.assessmentExportOptions)
        .filter(([key, val]) => val !== false)
        .map(([key, val]) => ({ [key]: val }))
    },

    sortLabel () {
      if (this.viewMode === 'view_mode_tag') {
        return Translator.trans('sortation.tag')
      }

      if (this.viewMode === 'view_mode_elements') {
        return Translator.trans('sortation.elements')
      }

      if (this.searchTerm.length > 0) {
        return Translator.trans('sortation.relevance')
      }

      return ''
    },

    hasActiveFilters () {
      return Object.keys(this.filterSet).length && this.filterSet.activeFilters.length
    },

    viewModeActivated () {
      return this.viewMode === 'view_mode_tag' || this.viewMode === 'view_mode_elements'
    }
  },

  methods: {
    ...mapActions('Statement', [
      'setSelectionAction'
    ]),

    ...mapMutations('AssessmentTable', [
      'setProperty'
    ]),

    determineExternId (statement) {
      return (statement.parentId && statement.originalId && statement.originalId !== statement.parentId) ? Translator.trans('copyof') + ' ' + statement.externId : statement.externId
    },

    toggleAllCheckboxes (status) {
      const statements = JSON.parse(JSON.stringify(this.statements))
      const payload = { status, statements }

      if (status === true) {
        for (const statementId in statements) {
          const statement = statements[statementId]

          statements[statementId] = {
            id: statementId,
            movedToProcedure: (statement.movedToProcedureId !== ''),
            assignee: statement.assignee,
            extid: this.determineExternId(statement),
            isCluster: statement.isCluster
          }
        }
        payload.statements = statements
      }
      this.setSelectionAction(payload)
    },

    /**
     * Toggle 'Gliederung' view (e.g. ordered by tags or elements)
     * @param {String} mode
     */
    toggleViewMode (mode) {
      const form = document.bpform
      form.r_view_mode.value = mode
      window.submitForm(null, 'viewMode')
    }
  }
}
</script>
