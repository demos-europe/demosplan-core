<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--
  The FilterFlyout is a VueComponent that can handle all types of filters including filter groups.
  It displays a list of filter options as checkbox-elements that may be grouped by filter groups.
  The items come with a count that indicates the amount of items that would be displayed after applying the filter

  At the moment the requested filter has to be implemented in the controller and in `segmentsFilterNames.yaml` manually.

  (NOTICE)
  The Component itself does not filter the results or anything. it only displays the selection and provides the selected filter-query.

  ## Events

  `@filterApply` gets emitted after clicking "submit". as payload it sends an Object with the selected filters

  eg:
  ```
  filters: {
      some-uuid: {
          condition: {
              operator:"ARRAY_CONTAINS_VALUE",
              path:"tags",
              value:"some-uuid"
          }
      },
      another-uuid: {
          condition: {
              operator:"ARRAY_CONTAINS_VALUE",
              path:"tags",
              value:"another-uuid"
          }
      }
  }
  ```
  -->
</documentation>

<template>
  <dp-flyout
    ref="flyout"
    align="left"
    :data-cy="category.label"
    variant="dark"
    :padded="false"
    @close="handleClose"
    @open="handleOpen">
    <template v-slot:trigger>
      <span :class="{ 'weight--bold' : (appliedQuery.length > 0) }">
        {{ category.label }}
        <span
          class="o-badge o-badge--small o-badge--transparent mb-px mr-1"
          v-if="appliedQuery.length > 0">
          {{ appliedQuery.length }}
        </span>
      </span>
      <i
        class="fa"
        :class="isExpanded ? 'fa-angle-up' : 'fa-angle-down'"
        aria-hidden="true" />
    </template>

    <div
      class="min-w-12 border--bottom u-p-0_5 leading-[2] whitespace-nowrap">
      <dp-resettable-input
        :data-cy="`searchField:${path}`"
        :id="`searchField_${path}`"
        :input-attributes="{ placeholder: Translator.trans('search.list'), type: 'search' }"
        @reset="resetSearch"
        v-model="searchTerm" />
    </div>

    <dp-loading
      v-if="isLoading"
      class="u-mt u-ml-0_5 u-pb" />

    <div v-else>
      <div
        :style="maxHeight"
        class="w-full border--bottom overflow-y-scroll u-p-0_5">
        <ul
          v-if="ungroupedOptions?.length > 0"
          class="o-list line-height--1_6">
          <filter-flyout-checkbox
            v-for="option in searchedUngroupedOptions"
            :key="option.id"
            :checked="isChecked(option.id)"
            instance="ungrouped"
            :option="option"
            :show-count="showCount.ungroupedOptions"
            @change="updateQuery" />
        </ul>
        <ul
          v-for="group in searchedGroupedOptions"
          class="o-list line-height--1_6"
          :key="`list_${group.id}}`">
          <span class="font-size-small">
            {{ group.label }}
          </span>
          <filter-flyout-checkbox
            v-for="option in group.options"
            :key="option.id"
            :checked="isChecked(option.id)"
            :instance="group.id"
            :option="option"
            :show-count="showCount.groupedOptions"
            @change="updateQuery" />
        </ul>

        <span v-if="searchedGroupedOptions.length === 0 && searchedUngroupedOptions?.length === 0">
          {{ Translator.trans('search.results.none') }}
        </span>
      </div>
      <div
        v-if="itemsSelected.length > 0"
        class="flow-root">
        <h3
          class="inline-block font-size-small weight--normal u-m-0_5">
          {{ Translator.trans('filter.active') }}
        </h3>
        <button
          v-if="currentQuery.length"
          class="o-link--default btn--blank font-size-small u-m-0_5 float-right"
          :data-cy="`filter:removeActiveFilter:${path}`"
          @click="resetAndApply">
          {{ Translator.trans('filter.active.remove') }}
        </button>
      </div>
      <ul
        class="o-list u-p-0_5 u-pt-0 line-height--1_6">
        <filter-flyout-checkbox
          v-for="item in itemsSelected"
          :key="`itemsSelected_${item.id}}`"
          checked
          :highlight="appliedQuery.includes(item.id) === false"
          instance="itemsSelected"
          :option="item"
          @change="updateQuery" />
      </ul>
      <div class="flow-root u-p-0_5 u-pt-0">
        <dp-button
          class="float-left"
          :data-cy="`filter:applyFilter:${path}`"
          :text="Translator.trans('apply')"
          @click="apply" />
        <dp-button
          class="float-right"
          color="secondary"
          :data-cy="`filter:abortFilter:${path}`"
          :text="Translator.trans('abort')"
          @click="close" />
      </div>
    </div>
  </dp-flyout>
</template>

<script>
import {
  dataTableSearch,
  DpButton,
  DpFlyout,
  DpLoading,
  DpResettableInput,
  hasOwnProp
} from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import FilterFlyoutCheckbox from './FilterFlyoutCheckbox'

export default {
  name: 'FilterFlyout',

  components: {
    DpButton,
    DpFlyout,
    DpLoading,
    DpResettableInput,
    FilterFlyoutCheckbox
  },

  props: {
    additionalQueryParams: {
      type: Object,
      required: false,
      default: () => ({})
    },

    category: {
      type: Object,
      required: true,
      validator: prop => {
        return hasOwnProp(prop, 'label') && hasOwnProp(prop, 'id')
      }
    },

    // Contains ids of applied filters from this and the neighboring filterFlyouts
    initialQueryIds: {
      type: Array,
      required: false,
      default: () => ([])
    },

    /**
     * Set to group id if the filter options should be applied with an 'OR' conjunction,
     * i.e. they need to be a member of a group:
     * 'groupId' : {
     *   group: {
     *     conjunction: 'OR'
     *   }
     * }
     */
    memberOf: {
      type: String,
      required: false,
      default: ''
    },

    operator: {
      type: String,
      required: true
    },

    path: {
      type: String,
      required: true
    },

    /**
     * Define if count should be displayed behind each filter option
     */
    showCount: {
      type: Object,
      required: false,
      default: () => ({
        groupedOptions: false,
        ungroupedOptions: false
      }),
      validator: prop => {
        return Object.keys(prop).length === 2 && hasOwnProp(prop, 'groupedOptions') && hasOwnProp(prop, 'ungroupedOptions')
      }
    }
  },

  emits: [
    'filterApply',
    'filterOptions:request'
  ],

  data () {
    return {
      // Filters in the current flyout that have been applied to the segment list, contains only ids
      appliedQuery: [],
      /*
       * Filters in the current flyout that have been selected, but not applied to the segment list (the filter facets have been updated though),
       * contains only ids
       */
      currentQuery: [],
      searchTerm: ''
    }
  },

  computed: {
    ...mapGetters('FilterFlyout', [
      'getFilterQuery',
      'getGroupedOptionsByCategoryId',
      'getInitialFlyoutFilterIdsByCategoryId',
      'getIsExpandedByCategoryId',
      'getIsLoadingByCategoryId',
      'getUngroupedOptionsByCategoryId'
    ]),

    initialFlyoutFilterIds () {
      return this.getInitialFlyoutFilterIdsByCategoryId(this.category.id)
    },

    /**
     * Construct the query object to be passed into vuex-json-api call.
     * if the id is unassigned, then set the operator to IS NULL so that only segments with assignee null are taking.
     * That is because the BE uses Elastic Search to make the filtering
     */
    filter () {
      const filter = {}

      this.currentQuery.forEach(id => {
        if (id === 'unassigned') {
          filter[id] = {
            condition: {
              path: this.path,
              operator: 'IS NULL'
            }
          }
        } else {
          filter[id] = {
            condition: {
              path: this.path,
              value: id,
              operator: this.operator
            }
          }

          if (this.memberOf) {
            filter[id].condition = {
              ...filter[id].condition,
              memberOf: this.memberOf
            }
          }
        }
      })

      return filter
    },

    groupedOptions () {
      return this.getGroupedOptionsByCategoryId(this.category.id) || []
    },

    isLoading () {
      return this.getIsLoadingByCategoryId(this.category.id) ?? false
    },

    /*
     * The maxHeight for the scrollable options is calculated to better match devices.
     */
    maxHeight () {
      const offsetTop = this.$el?.getBoundingClientRect().top + document.documentElement.scrollTop
      const searchFieldHeight = 58
      const buttonRowHeight = 58
      /*
       * The "26" equals the height of one option, whereas the
       * 42 equals the height of the "Active Filters" row.
       */
      const selectedItemsHeight = (this.itemsSelected.length + 1) * 26 + 42
      const subtractedHeight = selectedItemsHeight + offsetTop + searchFieldHeight + buttonRowHeight
      return `max-height: calc(100vh - ${subtractedHeight}px);min-height: 100px;`
    },

    isExpanded () {
      return this.getIsExpandedByCategoryId(this.category.id) ?? false
    },

    /**
     * {Array of Objects} selected filterItems, same structure as items
     */
    itemsSelected () {
      const items = [
        ...this.ungroupedOptions,
        ...this.groupedOptions.flatMap(group => group.options)
      ]
      return items.filter((item) => item.selected)
    },

    searchedGroupedOptions () {
      return this.groupedOptions.map(group => ({
        ...group,
        options: dataTableSearch(this.searchTerm, group.options, ['label'])
      })).filter(group => group.options.length > 0)
    },

    searchedUngroupedOptions () {
      return dataTableSearch(this.searchTerm, this.ungroupedOptions, ['label'])
    },

    ungroupedOptions () {
      return this.getUngroupedOptionsByCategoryId(this.category.id) || []
    }
  },

  watch: {
    /**
     * Watcher for initialFlyoutFilterIds.
     * Compares new and old values deeply and updates the current query if they differ.
     *
     * @param {Array} newIds - The new array of filter IDs.
     * @param {Array} oldIds - The old array of filter IDs.
     */
    initialFlyoutFilterIds: {
      handler (newIds, oldIds) {
        if (JSON.stringify(newIds) !== JSON.stringify(oldIds)) {
          this.setCurrentQuery(newIds)
        }
      },
      deep: true
    }
  },

  methods: {
    ...mapActions('FilterFlyout', {
      updateFilters: 'updateFilterQuery'
    }),

    ...mapMutations('FilterFlyout', {
      setGroupedSelected: 'setGroupedOptionSelected',
      setIsExpanded: 'setIsExpanded',
      setIsLoading: 'setIsLoading',
      setUngroupedSelected: 'setUngroupedOptionSelected'
    }),

    /**
     * Emit event with currently selected filters as query object.
     */
    apply () {
      this.$emit('filterApply', this.filter)
      this.appliedQuery = JSON.parse(JSON.stringify(this.currentQuery))
      this.$refs.flyout.close()
    },

    /**
     * Close filter flyout, reset to the last applied state.
     */
    close () {
      this.handleClose()
      this.$refs.flyout.close()
    },

    isChecked (id) {
      return this.currentQuery.includes(id)
    },

    /**
     * - Resets filter flyout search
     * - Resets filters that were selected but not applied
     * - Updates facets
     */
    handleClose () {
      this.setIsExpanded({ categoryId: this.category.id, isExpanded: false })
      this.resetSearch()
      this.restoreAppliedFilterQuery()
      this.currentQuery = JSON.parse(JSON.stringify(this.appliedQuery))
    },

    handleOpen () {
      this.setIsExpanded({ categoryId: this.category.id, isExpanded: true })
      this.requestFilterOptions()
    },

    /**
     * Emits a 'filterOptions:request' event with the provided query parameters.
     *
     * @param {boolean} [isInitialWithQuery=false] - Indicates if it is an initial request with query.
     */
    requestFilterOptions (isInitialWithQuery = false) {
      this.$emit('filterOptions:request', {
        additionalQueryParams: this.additionalQueryParams,
        filter: this.getFilterQuery,
        isInitialWithQuery,
        path: this.path
      })
    },

    reset () {
      this.resetFilterQuery()
      this.appliedQuery = []
      this.currentQuery = []
    },

    resetAndApply () {
      this.reset()
      this.apply()
    },

    // Remove all selected filters for this flyout
    resetFilterQuery () {
      Object.values(this.filter).forEach(el => {
        const query = {}
        query[el.condition.value] = el
        this.updateFilters(query)
      })
    },

    resetSearch () {
      this.searchTerm = ''
    },

    // Remove filters that were not applied for this flyout
    restoreAppliedFilterQuery () {
      const filterArray = Object.values(this.filter)
      const hasUnappliedFilters = filterArray.length > this.appliedQuery.length
      if (filterArray.length && hasUnappliedFilters) {
        filterArray.forEach(filter => {
          // Delete filters that are not in appliedQuery
          if (typeof this.appliedQuery.find(queryId => queryId === filter.condition.value) === 'undefined') {
            const query = {}
            query[filter.condition.value] = filter
            this.updateFilters(query)
          }
        })
      }
    },

    setCurrentQuery (query) {
      this.currentQuery = JSON.parse(JSON.stringify(query))
      this.appliedQuery = JSON.parse(JSON.stringify(query))
    },

    /**
     *
     * @param {Boolean} isSelected
     * @param {Object} option - { id: string, label: string, selected: boolean }
     */
    updateQuery (isSelected, option) {
      if (isSelected) {
        this.currentQuery.push(option.id)
        const query = {}
        query[option.id] = this.filter[option.id]

        this.updateFilters(query)
      } else if (!isSelected) {
        const query = {}
        query[option.id] = this.filter[option.id]

        this.updateFilters(query)
        this.currentQuery.splice(this.currentQuery.indexOf(option.id), 1)
      }

      // Update ungroupedOptions
      if (option.ungrouped) {
        this.setUngroupedSelected({ categoryId: this.category.id, optionId: option.id, value: isSelected })
      } else {
        // Update groupedOptions
        const group = this.groupedOptions.find(group => group.options.some(item => item.id === option.id))

        if (group) {
          this.setGroupedSelected({ categoryId: this.category.id, groupId: group.id, optionId: option.id, value: isSelected })
        }
      }

      this.requestFilterOptions()
    }
  },

  mounted () {
    this.setIsLoading({ categoryId: this.category.id, isLoading: true })
    this.setIsExpanded({ categoryId: this.category.id, isExpanded: false })

    if (this.initialQueryIds.length) {
      const isInitialWithQuery = true

      this.requestFilterOptions(isInitialWithQuery)

      if (this.itemsSelected) {
        const selectedIds = this.itemsSelected.map(item => item.id)
        this.appliedQuery = selectedIds
        this.currentQuery = selectedIds
      }
    }
  }
}
</script>
