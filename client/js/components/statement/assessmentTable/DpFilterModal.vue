<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- Displays a set of filter selects, ordered by tabs (also referred to as filterGroups)
       Is currently used in assessment table and original table

       If permission featureProcedureUserFilterSets is active, it also displays a button to save user filterSets (= a
       combination of selected filters) as well as a dropdown to select user filterSets
       The save button opens a new view inside the modal
  -->
</documentation>

<template>
  <div data-filter-modal>
    <!-- Filter button to open modal -->
    <button
      type="button"
      :class="{'color-highlight': noFilterApplied === false }"
      class="btn--blank o-link--default inline-block u-mb-0 u-p-0"
      data-cy="openFilterModal"
      @click.prevent="openModal"
    >
      <i
        class="fa fa-lg fa-filter"
        aria-hidden="true"
      />
      {{ Translator.trans('filter.verb') }}
    </button>

    <!-- Modal content -->
    <dp-modal
      ref="filterModalInner"
      class="layout"
      content-classes="u-1-of-2"
      @modal:toggled="isOpen => resetUnsavedOptions(isOpen)"
    >
      <dp-loading v-if="isLoading" />

      <!-- Show filters -->
      <template v-else-if="!saveFilterSetView">
        <h2 class="u-mb">
          {{ Translator.trans('filter.modalTitle') }}
        </h2>

        <!-- Select with saved filter sets -->
        <div
          v-if="userFilterSetSaveEnabled"
        >
          <dp-multiselect
            id="userFilterSets"
            v-model="selectedUserFilterSet"
            :custom-label="nameFromAttributes"
            data-cy="userFilterSets"
            :options="userFilterSets"
            track-by="id"
          >
            <template v-slot:option="{ props }">
              <a
                class="multiselect__option-extention"
                href="#"
                @click.prevent="deleteSavedFilterSet(props.option.id)"
              >
                <i
                  class="fa fa-trash"
                  aria-hidden="true"
                />
              </a>
              {{ hasOwnProp(props.option, 'attributes') ? props.option.attributes.name : '' }}
            </template>
            <template v-slot:singleLabel="{ props }">
              {{ hasOwnProp(props.option, 'attributes') ? props.option.attributes.name : '' }}
            </template>
          </dp-multiselect>

          <div class="text--right u-mb u-pt-0_5">
            <button
              type="button"
              class="btn btn--primary"
              data-cy="loadUserFilterSet"
              @click.prevent="loadUserFilterSet"
            >
              {{ Translator.trans('filter.saveFilterSet.load') }}
            </button>
          </div>
        </div>

        <!-- Tabs with filters -->
        <dp-tabs
          :active-id="activeTabId"
          tab-size="medium"
          @change="setActiveTabId"
        >
          <dp-tab
            v-for="(filterGroup, index) in filterGroupsToBeDisplayed"
            :id="filterGroup.label"
            :key="index"
            class="u-pt-0_5"
            :is-active="activeTabId === filterGroup.type"
            :label="Translator.trans(filterGroup.label)"
            :suffix="createSelectedFiltersBadge(filterGroup)"
          >
            <dp-filter-modal-select-item
              v-for="filterItem in filterByType(filterGroup.type)"
              :key="filterItem.id"
              :applied-filter-options="appliedFilterOptions.filter(option => option.filterId === filterItem.id)"
              :filter-item="filterItem"
              :filter-group="filterGroup"
              @update-selected="updateSelectedOptions"
              @updating-filters="disabledInteractions = true"
              @updated-filters="disabledInteractions = false"
            />
            <dp-custom-fields-filter
              v-if="filterGroup.type === 'statement' && customFieldDefinitions.length > 0"
              :custom-field-definitions="customFieldDefinitions"
              :field-option-counts="customFieldOptionCounts"
              :value="customFieldFilterValue"
              variant="modal"
              @input="updateCustomFieldFilter"
              @open="refreshCustomFieldCounts"
            />
          </dp-tab>
        </dp-tabs>

        <!-- hidden selects so selected fields can be saved via form submit -->
        <select
          v-for="(option, optionKey) in allSelectedFilterOptionsWithFilterName"
          :id="option.name"
          :key="optionKey"
          :name="option.name"
          multiple
          style="display: none"
        >
          <option
            :key="optionKey"
            :value="option.value"
            selected
          >
            {{ option.label }}
          </option>
        </select>

        <!-- Checkbox to indicate user wants to save the current filter set -->
        <template v-if="userFilterSetSaveEnabled">
          <div class="layout__item u-1-of-3" />
          <label
            for="r_save_filter_set"
            class="layout__item u-2-of-3 u-pt-0_5"
            :class="{'color--grey': noFilterSelected}"
          >
            <input
              id="r_save_filter_set"
              v-model="saveFilterSet"
              type="checkbox"
              name="r_save_filter_set"
              :disabled="noFilterSelected"
              data-cy="saveFilterSet"
            >
            {{ Translator.trans('filter.saveFilterSet.label') }}
          </label>
        </template>

        <!-- Button row -->
        <div class="text-right space-inline-s">
          <button
            type="submit"
            class="btn btn--primary"
            :class="{'pointer-events-none':disabledInteractions}"
            data-cy="submitOrNext"
            @click.prevent="submitOrNext"
            v-text="Translator.trans(saveFilterSet ? 'filter.saveFilterSet.next' : 'filter.apply')"
          />
          <button
            type="button"
            class="btn btn--secondary"
            data-cy="filterReset"
            @click="reset"
            v-text="Translator.trans('filter.reset')"
          />
        </div>
      </template>

      <!-- Show UI to save filter set -->
      <template v-else>
        <h2>{{ Translator.trans('filter.saveFilterSet.title') }}</h2>

        <label
          for="r_save_filter_set_name"
          class="u-pt-0_5"
        >
          {{ Translator.trans('filter.saveFilterSet.label') }}
          <input
            id="r_save_filter_set_name"
            class="layout__item u-mt-0_5"
            type="text"
            name="r_save_filter_set_name"
            data-cy="filterSetName"
            :value="filterSetName"
          >
        </label>

        <div
          v-for="(filterGroup, index) in filterGroupsToBeDisplayed"
          :key="index"
          class="visuallyhidden"
        >
          <dp-filter-modal-select-item
            v-for="filterItem in filterByType(filterGroup.type)"
            :key="filterItem.id"
            :filter-item="filterItem"
            :filter-group="filterGroup"
            hidden
          />
        </div>

        <!-- Button row -->
        <div class="text-right space-inline-s">
          <button
            class="btn btn--primary"
            :class="{'pointer-events-none': disabledInteractions}"
            type="submit"
            data-cy="submitWithSave"
            @click.stop="submitWithSave"
          >
            {{ Translator.trans('filter.saveFilterSet.submit') }}
          </button>
          <button
            type="button"
            class="btn btn--secondary"
            @click="back"
          >
            {{ Translator.trans('filter.saveFilterSet.back') }}
          </button>
        </div>
      </template>
    </dp-modal>
  </div>
</template>

<script>
import { DpLoading, DpModal, DpMultiselect, DpTab, DpTabs, hasOwnProp } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import DpCustomFieldsFilter from '@DpJs/components/shared/DpCustomFieldsFilter'
import DpFilterModalSelectItem from './FilterModalSelectItem'
import { useCustomFields } from '@DpJs/composables/useCustomFields'

export default {
  name: 'DpFilterModal',

  components: {
    DpCustomFieldsFilter,
    DpFilterModalSelectItem,
    DpLoading,
    DpModal,
    DpMultiselect,
    DpTab,
    DpTabs,
  },

  props: {
    appliedFilterOptions: {
      required: false,
      type: Array,
      default: () => ([]),
    },

    filterHash: {
      required: false,
      type: String,
      default: '',
    },

    original: {
      required: false,
      type: Boolean,
      default: false,
    },

    procedureId: {
      required: true,
      type: String,
    },
  },

  emits: [
    'close',
  ],

  data () {
    return {
      activeTabId: null,
      customFieldDefinitions: [],
      customFieldFilterValue: {},
      disabledInteractions: false, // Do not submit form if filters are currently updating
      isLoading: true,
      saveFilterSet: false,
      saveFilterSetView: false,
      selectedOptions: [],
      selectedUserFilterSet: {},
    }
  },

  computed: {
    ...mapState('Filter', {
      filterGroups: 'filterGroups',
      filterList: 'filterList',
      filterOptionsSelected: 'selectedOptions',
    }),

    ...mapGetters('Filter', {
      customFieldOptionCounts: 'customFieldOptionCounts',
      filterByType: 'filterByType',
      getFilterHash: 'userFilterSetFilterHash',
      userFilterSets: 'userFilterSets',
      // All selected filter options
      selectedFilterOptions: 'selectedFilterOptions',
      // Selected options for filterHash
      allSelectedFilterOptionsWithFilterName: 'allSelectedFilterOptionsWithFilterName',
    }),

    /**
     * Returns only filterGroups (i.e. tabs) that have filters and should be displayed
     * fragment tab should only be displayed if the corresponding feature is active
     */
    filterGroupsToBeDisplayed () {
      return this.filterGroups.filter(filterGroup => {
        const groupHasPermission = filterGroup.permission ? hasPermission(filterGroup.permission) : true
        const groupHasFilters = this.filterByType(filterGroup.type).length > 0

        return groupHasPermission && groupHasFilters ? filterGroup : false
      })
    },

    /**
     * Generate initial name for filterSet from labels of selected options
     */
    filterSetName () {
      let selectedFilter
      const selectedFilterLabels = []

      for (const index in this.selectedFilterOptions) {
        if (hasOwnProp(this.selectedFilterOptions, index)) {
          selectedFilter = this.selectedFilterOptions[index]
          selectedFilterLabels.push(selectedFilter.label)
        }
      }

      return selectedFilterLabels.join(', ')
    },

    noFilterApplied () {
      return this.appliedFilterOptions.length === 0
    },

    noFilterSelected () {
      return this.selectedFilterOptions.length === 0
    },

    route () {
      return this.original ? 'dplan_assessmenttable_view_original_table' : 'dplan_assessmenttable_view_table'
    },

    selectedOptionsInStore () {
      return this.selectedFilterOptions
    },

    userFilterSetSaveEnabled () {
      return (this.original === false && hasPermission('feature_procedure_user_filter_sets'))
    },
  },

  watch: {
    noFilterSelected: {
      handler () {
        // If no filter is selected, uncheck the filter.saveFilterSet checkbox
        this.saveFilterSet = false
      },
      deep: false, // Set default for migrating purpose. To know this occurrence is checked

    },

    selectedOptionsInStore: {
      handler () {
        this.selectedOptions = this.selectedFilterOptions
      },
      deep: true,
    },
  },

  methods: {
    ...mapActions('Filter', [
      'getFilterListAction',
      'getFilterOptionsAction',
      'getUserFilterSetsAction',
      'removeUserFilterSetAction',
      'updateBaseState',
    ]),

    ...mapMutations('AssessmentTable', [
      'setProperty',
    ]),

    ...mapMutations('Filter', [
      'loadAppliedFilterOptions',
      'loadSelectedFilterOptions',
      'resetSelectedOptions',
      'setActiveCfFilterEntries',
      'setLoading',
    ]),

    back () {
      this.saveFilterSetView = false
    },

    createSelectedFiltersBadge (filterGroup) {
      const selectedCount = this.selectedOptions.length ? this.selectedOptions.filter(option => option.type === filterGroup.type).length : 0

      return (selectedCount > 0) ? '<span class="o-badge o-badge--small o-badge--dark">' + selectedCount + '</span>' : ''
    },

    deleteSavedFilterSet (userFilterSetId) {
      if (confirm(Translator.trans('filter.savedFilterSet.delete.confirm'))) {
        this.removeUserFilterSetAction(userFilterSetId)
      }
    },

    hasOwnProp (obj, prop) {
      return hasOwnProp(obj, prop)
    },

    loadUserFilterSet () {
      const filterHash = this.userFilterSetFilterHash(this.selectedUserFilterSet)

      // Reload with userFilterSet
      document.location.href = Routing.generate(this.route, { procedureId: this.procedureId, filterHash })
    },

    nameFromAttributes (option) {
      return option?.attributes?.name || Translator.trans('choose')
    },

    initFilterList () {
      // Initially, only load empty filters without options
      return this.getFilterListAction()
        .then(() => {
          // Load selected options into store
          if (this.appliedFilterOptions.length > 0) {
            this.selectedOptions = this.appliedFilterOptions
            this.loadSelectedFilterOptions(this.appliedFilterOptions)
            this.loadAppliedFilterOptions(this.appliedFilterOptions)
          }
        })
        .then(() => this.getFilterOptionsAction({ filterHash: this.filterHash }))
    },

    initUserFilterSets () {
      return this.getUserFilterSetsAction()
        .then(() => {
          // Select current user filter set
          const currentFilterSet = this.userFilterSets.filter(userFilterSet => this.filterHash === this.userFilterSetFilterHash(userFilterSet))

          this.selectedUserFilterSet = (currentFilterSet.length === 1) ? currentFilterSet[0] : {}
        })
    },

    /**
     * Open filter modal and load filters without options.
     */
    openModal () {
      this.$refs.filterModalInner.toggle()

      if (this.filterList.length === 0) {
        this.updateBaseState({ procedureId: this.procedureId, original: this.original })
          .then(() => {
            const { fetchCustomFields } = useCustomFields()
            const promises = [
              this.initFilterList(),
              fetchCustomFields(this.procedureId, { sourceEntity: 'PROCEDURE', targetEntity: 'STATEMENT' })
                .then(definitions => {
                  this.customFieldDefinitions = definitions
                })
                .catch(() => {}),
            ]

            if (this.userFilterSetSaveEnabled) {
              promises.push(this.initUserFilterSets())
            }

            Promise.all(promises)
              .then(() => {
                this.isLoading = false
              })
          })
      }

      // Restore CF selections from the last applied filter state
      const cfValue = {}

      this.appliedFilterOptions
        .filter(f => f.type === 'customField')
        .forEach(f => {
          if (!cfValue[f.fieldId]) {
            cfValue[f.fieldId] = []
          }

          cfValue[f.fieldId].push(f.value)
        })

      this.customFieldFilterValue = cfValue
      this.setActiveCfFilterEntries(this.buildCfEntries(cfValue))
    },

    /**
     * Reset all selected filter options, also the saved ones
     */
    reset () {
      document.location.href = Routing.generate(this.route, { procedureId: this.procedureId, filterHash: '' })
    },

    /**
     * Reset only the unsaved selected filter options, but only when closing modal
     */
    resetUnsavedOptions (isOpen) {
      if (!isOpen) {
        this.$emit('close')
      }

      if (this.noFilterSelected === false && !isOpen) {
        this.resetSelectedOptions(this.appliedFilterOptions)
        this.updateSelectedOptions()
      }
    },

    setActiveTabId (id) {
      this.activeTabId = id
    },

    submitOrNext () {
      //  If user does not want to save the current filter set, just submit the form (a.k.a. apply filters)
      if (this.saveFilterSet === false) {
        this.submitWithSave(null)
      }

      //  If the user wants to save the current filter set, proceed to next step
      if (this.saveFilterSet && hasPermission('feature_procedure_user_filter_sets')) {
        this.saveFilterSetView = true
      }
    },

    submitWithSave (event) {
      /*
       * SubmitForm needs event to prevent default behaviour of submit button
       * it first updates the filterHash and then submits the form with a new
       * hash set in the action
       */
      const allEntries = this.buildAllEntries()
      const hasCfEntries = allEntries.some(e => e.name.startsWith('filter_customField_'))

      if (!hasCfEntries) {
        window.submitForm(event, 'filters')

        return
      }

      // Prevent default form submit; update hash with CF entries merged in, then submit
      if (event) {
        event.preventDefault()
      }

      window.updateFilterHash(this.procedureId, allEntries)
        .then(filterHash => {
          document.bpform.action = Routing.generate(this.route, { procedureId: this.procedureId, filterHash })
          document.bpform.submit()
        })
    },

    buildCfEntries (cfValue) {
      const entries = []
      Object.entries(cfValue).forEach(([fieldId, optionIds]) => {
        optionIds.forEach(optionId => {
          entries.push({ name: `filter_customField_${fieldId}[]`, value: optionId })
        })
      })
      return entries
    },

    buildAllEntries (cfValueOverride = null) {
      const cfValue = cfValueOverride ?? this.customFieldFilterValue
      return [...this.allSelectedFilterOptionsWithFilterName, ...this.buildCfEntries(cfValue)]
    },

    refreshCustomFieldCounts (fieldId) {
      // Mirror FilterModalSelectItem's sentinel pattern: post an empty-value entry for the
      // opened field so the backend knows to return its options with counts.
      const sentinel = { name: `filter_customField_${fieldId}[]`, value: '' }

      // Include active selections for OTHER CF fields; opened field gets only the sentinel.
      const otherCfEntries = []
      Object.entries(this.customFieldFilterValue).forEach(([fId, optionIds]) => {
        if (fId !== fieldId) {
          optionIds.forEach(optionId => {
            otherCfEntries.push({ name: `filter_customField_${fId}[]`, value: optionId })
          })
        }
      })

      const entries = [
        ...this.allSelectedFilterOptionsWithFilterName,
        sentinel,
        ...otherCfEntries,
      ]

      window.updateFilterHash(this.procedureId, entries)
        .then(filterHash => {
          this.getFilterOptionsAction({ filterHash })
        })
    },

    updateCustomFieldFilter (newValue) {
      this.customFieldFilterValue = newValue
      this.setActiveCfFilterEntries(this.buildCfEntries(newValue))

      window.updateFilterHash(this.procedureId, this.buildAllEntries(newValue))
        .then(filterHash => {
          this.getFilterOptionsAction({ filterHash })
        })
    },

    /**
     * Update filterHash with currently selected options from store
     * emit event to FilterModalSelectItem which then loads updated options from store
     */
    updateSelectedOptions (filterItemId = false) {
      window.updateFilterHash(this.procedureId, this.buildAllEntries())
        .then((filterHash) => {
          // Get updated options for selected filters
          this.getFilterOptionsAction({ filterHash })
            .then(() => {
              if (filterItemId) {
                this.setLoading({ filterId: filterItemId, isLoading: false })
              }

              this.disabledInteractions = false
            })
        })
    },

    userFilterSetFilterHash (userFilterSet) {
      return this.getFilterHash(userFilterSet)
    },
  },
}
</script>
