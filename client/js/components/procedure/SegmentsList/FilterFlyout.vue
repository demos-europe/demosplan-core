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

  `@filter-apply` gets emitted after clicking "submit". as payload it sends an Object with the selected fitlers

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
    align="left"
    :data-cy="label"
    :padded="false"
    @open="handleOpen"
    @close="handleClose"
    ref="flyout">
    <template v-slot:trigger>
      <span :class="{ 'weight--bold' : (appliedQuery.length > 0) }">
        {{ label }}
        <span
          class="o-badge o-badge--small o-badge--transparent"
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
      class="min-w-12 border--bottom u-p-0_5">
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
          v-if="getUngroupedItems().length"
          class="o-list line-height--1_6">
          <filter-flyout-checkbox
            v-for="item in getUngroupedItems()"
            @change="updateQuery"
            :checked="isChecked(item)"
            :option="item"
            instance="ungrouped"
            :key="item.id" />
        </ul>
        <ul
          v-for="group in groups"
          class="o-list line-height--1_6"
          :key="`list_${group.id}}`">
          <span class="font-size-small">
            {{ group.attributes.label }}
          </span>
          <filter-flyout-checkbox
            v-for="item in getItemsByGroup(group)"
            @change="updateQuery"
            :checked="isChecked(item)"
            :option="item"
            :instance="group.id"
            :key="item.id" />
        </ul>
        <span v-if="groups.length === 0 && getUngroupedItems().length === 0">
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
          @change="updateQuery"
          :checked="true"
          :show-count="false"
          :highlight="appliedQuery.includes(item.id) === false"
          :option="item"
          instance="itemsSelected"
          :key="`itemsSelected_${item.id}}`" />
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
  checkResponse,
  dataTableSearch,
  DpButton,
  DpFlyout,
  DpLoading,
  DpResettableInput,
  dpRpc,
  hasOwnProp
} from '@demos-europe/demosplan-ui'
import { mapGetters, mapMutations } from 'vuex'
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

    // Contains applied filters from this and the neighboring filterFlyouts
    initialQuery: {
      type: Array,
      default: () => ([])
    },

    label: {
      type: String,
      required: true
    },

    operator: {
      type: String,
      required: true
    },

    path: {
      type: String,
      required: true
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      // Filters in the current flyout that have been applied to the segment list, contains only ids
      appliedQuery: [],
      /*
       * Filters in the current flyout that have been selected, but not applied to the segment list (the filter facets have been updated though),
       * contains only ids
       */
      currentQuery: [],
      groupsObject: {},
      itemsObject: {},
      isExpanded: false,
      isLoading: true,
      searchTerm: ''
    }
  },

  computed: {
    ...mapGetters('SegmentFilter', {
      // All currently selected filters, in this as well as in (possible) neighboring filterFlyouts
      getFilterQuery: 'filterQuery'
    }),

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
        }
      })
      return filter
    },

    /*
     * The maxHeight for the scrollable options is calculated to better match devices.
     */
    maxHeight () {
      const offsetTop = this.$el.getBoundingClientRect().top + document.documentElement.scrollTop
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

    /**
     * {Array of Objects} filterItems
     * {
     *   attributes: { count: <number>, label: <String>, selected: <Boole<n> },
     *   id: <String>,
     *   type: <String>
     * }
     */
    items () {
      return dataTableSearch(this.searchTerm, Object.values(this.itemsObject), ['attributes.label'])
    },

    /**
     * {Array of Objects} selected filterItems, same structure as items
     */
    itemsSelected () {
      const items = Object.values(this.itemsObject)
      return items.filter((item) => item.attributes.selected)
    },

    // Contains only groups that contain at least 1 item
    groups () {
      const groups = Object.values(this.groupsObject)
      return groups.filter(group => this.getItemsByGroup(group).length)
    }
  },

  methods: {
    ...mapMutations('SegmentFilter', ['updateFilterQuery']),

    /**
     * Emit event with currently selected filters as query object.
     */
    apply () {
      this.$emit('filter-apply', this.filter)
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

    getItemsByGroup (group) {
      if (hasOwnProp(group.relationships, 'aggregationFilterItems')) {
        const currentItemIds = this.items.map(item => item.id)
        return Object.values(group.relationships.aggregationFilterItems.data)
          .filter(item => currentItemIds.includes(item.id))
          .map(item => this.itemsObject[item.id])
      } else {
        // Return if the itemTopic has no items associated with it (for whatever reason)
        return []
      }
    },

    getUngroupedItems () {
      return this.items.filter(item => item.ungrouped === true)
    },

    isChecked (item) {
      return this.currentQuery.includes(item.id)
    },

    /**
     * - resets filter flyout search
     * - resets filters that were selected but not applied
     * - updates facets
     */
    handleClose () {
      this.isExpanded = false
      this.resetSearch()
      this.restoreAppliedFilterQuery()
      this.currentQuery = JSON.parse(JSON.stringify(this.appliedQuery))
    },

    handleOpen () {
      this.isLoading = true
      this.isExpanded = true

      this.requestNewFilterResults()
        .then(() => {
          this.isLoading = false
          document.getElementById(`searchField_${this.path}`).focus()
        })
    },

    requestNewFilterResults () {
      const params = {
        ...this.additionalQueryParams,
        filter: {
          ...this.getFilterQuery,
          sameProcedure: {
            condition: {
              path: 'parentStatement.procedure.id',
              value: this.procedureId
            }
          }
        },
        path: this.path
      }
      // We have to set the searchPhrase to null if its empty to satisfy the backend
      if (params.searchPhrase === '') {
        params.searchPhrase = null
      }

      return dpRpc('segments.facets.list', params, 'filterList')
        .then(response => checkResponse(response))
        .then(response => {
          const result = (hasOwnProp(response, 0) && response[0].id === 'filterList') ? response[0].result : null
          const currentFilterType = result.data.find(type => type.attributes.path === this.path)
          if (currentFilterType && hasOwnProp(result, 'included')) {
            result.included.forEach(el => {
              if (el.type === 'AggregationFilterGroup' && typeof currentFilterType.relationships.aggregationFilterGroups.data.find(group => group.id === el.id) !== 'undefined') {
                this.groupsObject[el.id] = el
                if (hasOwnProp(el.relationships, 'aggregationFilterItems') && el.relationships.aggregationFilterItems.data.length > 0) {
                  el.relationships.aggregationFilterItems.data.forEach(item => {
                    const filterItem = result.included.find(filterItem => filterItem.id === item.id)
                    this.itemsObject[filterItem.id] = filterItem
                  })
                }
              } else if (el.type === 'AggregationFilterItem' && typeof currentFilterType.relationships.aggregationFilterItems.data.find(item => item.id === el.id) !== 'undefined') {
                el.ungrouped = true
                this.itemsObject[el.id] = el
              }
            })

            // If the current filter is assignee, display amount of Segments that have assignee as null. That is given by the field missingResourcesSum
             if (result.data[0].attributes.path === 'assignee') {
              this.$set(this.itemsObject, 'unassigned', {
                attributes: {
                  count: result.data[0].attributes.missingResourcesSum,
                  label: Translator.trans('not.assigned'),
                  ungrouped: true,
                  selected: result.meta.unassigned_selected
                },
                id: 'unassigned',
                type: 'AggregationFilterItem',
                ungrouped: true
              })
            }
          }
        })
        .catch(err => console.log(err))
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
        this.updateFilterQuery(query)
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
            this.updateFilterQuery(query)
          }
        })
      }
    },

    setCurrentQuery (query) {
      this.currentQuery = JSON.parse(JSON.stringify(query))
      this.appliedQuery = JSON.parse(JSON.stringify(query))
    },

    updateQuery (value, option) {
      if (value === true) {
        this.currentQuery.push(option.id)
        const query = {}
        query[option.id] = this.filter[option.id]
        this.updateFilterQuery(query)
      } else if (value === false) {
        const query = {}
        query[option.id] = this.filter[option.id]
        this.updateFilterQuery(query)
        this.currentQuery.splice(this.currentQuery.indexOf(option.id), 1)
      }

      this.itemsObject[option.id].attributes.selected = value
      return this.requestNewFilterResults()
    }
  },

  mounted () {
    if (this.initialQuery.length) {
      this.requestNewFilterResults()
        .then(() => {
          const currentFlyoutFilterIds = this.initialQuery.filter(queryId => {
            const item = this.items.find(item => item.id === queryId)
            return item ? item.id : null
          })
          this.setCurrentQuery(currentFlyoutFilterIds)
        })
    }
  }
}
</script>
