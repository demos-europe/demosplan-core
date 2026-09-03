<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<documentation>
  <!--
  SegmentsListFilter renders one accordion per filter category (tags, assignee, place) in the
  segments list slidebar. Reuses FilterFlyout's checkbox/search content and filter-building logic,
  but keeps currentQuery/appliedQuery per category so one shared pair of buttons resets/applies all
  categories at once. Emits `filterApply` and `filterOptions:request`, same payloads as FilterFlyout.
  -->
</documentation>

<template>
  <!--
    v-show hides the panel mounted while another slidebar tab is active,
    the applied per-category selection state survives (unapplied changes are discarded).
    Negative margin is to reset DpSlidebar's u-ml-1_5.
  -->
  <div
    v-show="isVisible"
    class="flex flex-col flex-1 min-h-0 -ml-5"
  >
    <h2 class="shrink-0 p-4 pt-0 border-b border-neutral bg-surface shadow-[0_3px_4px_-3px_rgba(0,0,0,0.26)]">
      {{ Translator.trans('filter') }}
    </h2>

    <div class="flex-1 min-h-0 overflow-y-auto">
      <dp-accordion
        v-for="category in categories"
        :key="category.id"
        class="p-4 border-b border-neutral"
        :data-cy="`segmentsListFilter:${category.id}`"
        :is-open="getIsExpandedByCategoryId(category.id)"
        :show-border="false"
        :show-status-dot="categoryHasPendingChanges(category)"
        :status-dot-label="Translator.trans('unsaved.changes')"
        :title="category.label"
        compressed
        @item:toggle="(isExpanded) => setExpanded(category, isExpanded)"
      >
        <div class="pt-3">
          <p
            v-if="category.hint"
            class="m-0 mb-3 text-sm"
          >
            {{ Translator.trans('filter.hint.or.logic') }}
          </p>

          <dp-resettable-input
            :id="`searchField_${category.path}`"
            v-model="category.searchTerm"
            :data-cy="`searchField:${category.path}`"
            :input-attributes="{ placeholder: Translator.trans('search.list'), type: 'search' }"
            @reset="resetSearch(category)"
          />

          <dp-loading
            v-if="isLoading(category)"
            class="mt-4 ml-2 pb-4"
          />

          <div
            v-else
            class="mt-3 pt-3 border-t border-neutral"
          >
            <ul
              v-if="getSearchedUngroupedOptions(category).length > 0"
              class="m-0 p-0 pb-2 list-none mb-2"
            >
              <filter-flyout-checkbox
                v-for="option in getSearchedUngroupedOptions(category)"
                :key="option.id"
                :checked="isChecked(category, option.id)"
                instance="ungrouped"
                :option="option"
                show-count
                @change="(isSelected, option) => updateQuery(category, isSelected, option)"
              />
            </ul>
            <ul
              v-for="(group, index) in getSearchedGroupedOptions(category)"
              :key="`list_${group.id}`"
              :class="['m-0 p-0 list-none', { 'pb-2 border-b border-neutral mb-3': index < getSearchedGroupedOptions(category).length - 1 }]"
            >
              <li class="font-semibold text-base mb-2">
                {{ group.label }}
              </li>
              <filter-flyout-checkbox
                v-for="option in group.options"
                :key="option.id"
                :checked="isChecked(category, option.id)"
                :instance="group.id"
                :option="option"
                show-count
                @change="(isSelected, option) => updateQuery(category, isSelected, option)"
              />
            </ul>

            <span v-if="getSearchedGroupedOptions(category).length === 0 && getSearchedUngroupedOptions(category).length === 0">
              {{ Translator.trans('search.results.none') }}
            </span>

            <template v-if="getItemsSelected(category).length > 0">
              <h3 class="text-base font-normal pt-3 border-t border-neutral">
                {{ Translator.trans('filter.active') }}
              </h3>
              <ul class="m-0 list-none pt-0">
                <filter-flyout-checkbox
                  v-for="item in getItemsSelected(category)"
                  :key="`itemsSelected_${item.id}`"
                  checked
                  :highlight="!category.appliedQuery.includes(item.id)"
                  instance="itemsSelected"
                  :option="item"
                  @change="(isSelected, option) => updateQuery(category, isSelected, option)"
                />
              </ul>
            </template>
          </div>
        </div>
      </dp-accordion>
    </div>

    <div class="shrink-0 flex justify-end gap-2 p-4 bg-surface shadow-lg">
      <dp-button
        data-cy="segmentsListFilter:reset"
        variant="outline"
        :disabled="hasSelectedFilters === false"
        :text="Translator.trans('filter.reset')"
        @click="resetAllFilters"
      />
      <dp-button
        data-cy="segmentsListFilter:apply"
        :disabled="hasPendingChanges === false"
        :text="Translator.trans('filter.apply')"
        @click="applyAllFilters"
      />
    </div>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import {
  dataTableSearch,
  DpAccordion,
  DpButton,
  DpLoading,
  DpResettableInput,
} from '@demos-europe/demosplan-ui'
import FilterFlyoutCheckbox from './FilterFlyoutCheckbox'
import { useStore } from 'vuex'

export default {
  name: 'SegmentsListFilter',

  components: {
    DpAccordion,
    DpButton,
    DpLoading,
    DpResettableInput,
    FilterFlyoutCheckbox,
  },

  props: {
    /**
     * {Object of objects}
     * {
     *   assignee: {
     *     comparisonOperator: string,
     *     labelTranslationKey: string,
     *     rootPath: string,
     *   },
     *   place: s. assignee,
     *   tags: s. assignee
     * }
     */
    filters: {
      type: Object,
      required: false,
      default: () => ({}),
    },

    initialFilter: {
      type: [Object, Array],
      required: false,
      default: () => ({}),
    },
  },

  emits: [
    'filterApply',
    'filterOptions:request',
  ],

  setup (props, { emit }) {
    const store = useStore()

    // *** STORE BINDINGS ***
    const getFilterQuery = computed(() => store.getters['FilterFlyout/getFilterQuery'])
    const slidebar = computed(() => store.state.SegmentSlidebar.slidebar)
    const getIsExpandedByCategoryId = (categoryId) => store.getters['FilterFlyout/getIsExpandedByCategoryId'](categoryId)

    const updateFilters = (query) => store.dispatch('FilterFlyout/updateFilterQuery', query)

    const setGroupedSelected = (payload) => store.commit('FilterFlyout/setGroupedOptionSelected', payload)
    const setIsExpanded = (payload) => store.commit('FilterFlyout/setIsExpanded', payload)
    const setIsLoadingMutation = (payload) => store.commit('FilterFlyout/setIsLoading', payload)
    const setUngroupedSelected = (payload) => store.commit('FilterFlyout/setUngroupedOptionSelected', payload)

    const closeSlidebar = () => {
      store.commit('SegmentSlidebar/setContent', {
        prop: 'slidebar',
        val: { externId: '', isOpen: false, segmentId: '', showTab: '' },
      })
    }

    // *** VISIBILITY ***
    const isVisible = ref(slidebar.value.showTab === 'filter')

    watch(() => slidebar.value.showTab, (showTab) => {
      isVisible.value = showTab === 'filter'
    })

    // *** CATEGORIES ***
    /*
     * Filters within one category are OR-combined by giving them a shared group key (memberOf).
     * 'tags' is the exception and uses none. Group keys can't contain '.', so 'workflow.place'
     * from segmentsFilterNames.yaml becomes 'workflow-place_group'.
     */
    const groupName = (filterType) => {
      if (filterType === 'tags') {
        return null
      }

      return `${filterType.replaceAll('.', '-')}_group`
    }

    const categories = reactive(Object.values(props.filters).map((filterDefinition, idx) => ({
      appliedQuery: [],
      currentQuery: [],
      hint: filterDefinition.labelTranslationKey !== 'tags',
      id: `${filterDefinition.labelTranslationKey}:${idx}`,
      label: Translator.trans(filterDefinition.labelTranslationKey),
      memberOf: groupName(filterDefinition.labelTranslationKey),
      operator: filterDefinition.comparisonOperator,
      path: filterDefinition.rootPath,
      searchTerm: '',
    })))

    const categoryHasPendingChanges = (category) => {
      if (category.currentQuery.length !== category.appliedQuery.length) {
        return true
      }

      return category.currentQuery.some((id) => category.appliedQuery.includes(id) === false)
    }

    const hasPendingChanges = computed(() => categories.some((category) => categoryHasPendingChanges(category)))

    const hasSelectedFilters = computed(() => categories.some((category) => category.currentQuery.length > 0))

    // Same extraction as SegmentsList's own `queryIds` computed, applied to the same initialFilter data.
    const queryIds = computed(() => {
      let ids = []

      if (
        Array.isArray(props.initialFilter) === false &&
        Object.values(props.initialFilter).length > 0
      ) {
        ids = Object.values(props.initialFilter)
          .filter((el) => el.condition) // Remove group objects
          .map((el) => {
            if (!el.condition.value) {
              return 'unassigned'
            }

            return el.condition.value
          })
      }

      return ids
    })

    // *** DISPLAY OPTIONS ***
    const getGroupedOptions = (category) => store.getters['FilterFlyout/getGroupedOptionsByCategoryId'](category.id) || []

    const getUngroupedOptions = (category) => store.getters['FilterFlyout/getUngroupedOptionsByCategoryId'](category.id) || []

    /**
     * {Array of Objects} selected filterItems, same structure as items
     */
    const getItemsSelected = (category) => {
      const items = [
        ...getUngroupedOptions(category),
        ...getGroupedOptions(category).flatMap((group) => group.options),
      ]

      return items.filter((item) => item.selected)
    }

    const getSearchedGroupedOptions = (category) => getGroupedOptions(category).map((group) => ({
      ...group,
      options: dataTableSearch(category.searchTerm, group.options, ['label']),
    })).filter((group) => group.options.length > 0)

    const getSearchedUngroupedOptions = (category) => dataTableSearch(category.searchTerm, getUngroupedOptions(category), ['label'])

    const isChecked = (category, optionId) => category.currentQuery.includes(optionId)

    const isLoading = (category) => store.getters['FilterFlyout/getIsLoadingByCategoryId'](category.id) ?? false

    // *** FILTER ACTIONS ***
    const buildFilterCondition = (category, id) => {
      // Builds the JSON:API condition for one option id; 'unassigned' maps to IS NULL.
      const condition = id === 'unassigned' ?
        { path: category.path, operator: 'IS NULL' } :
        { path: category.path, value: id, operator: category.operator }

      if (category.memberOf) {
        condition.memberOf = category.memberOf
      }

      return { condition }
    }

    // Builds the JSON:API filter map for all of a category's selected ids.
    const getFilter = (category) => {
      const filter = {}

      category.currentQuery.forEach((id) => {
        filter[id] = buildFilterCondition(category, id)
      })

      return filter
    }

    // Emits a 'filterOptions:request' event with the provided query parameters.
    const requestFilterOptions = (category, isInitialWithQuery = false) => {
      // For OR groups (memberOf is set), exclude this group's own filters so counts always show full availability
      let filter = getFilterQuery.value

      if (category.memberOf && !isInitialWithQuery) {
        filter = Object.fromEntries(
          Object.entries(filter).filter(([key, val]) => {
            if (key === category.memberOf) {
              return false
            }

            return val.condition?.memberOf !== category.memberOf
          }),
        )
      }

      emit('filterOptions:request', {
        category: { id: category.id, label: category.label },
        currentQuery: category.currentQuery,
        filter,
        isInitialWithQuery,
        path: category.path,
      })
    }

    const applyAllFilters = () => {
      const mergedFilter = categories.reduce((acc, category) => ({ ...acc, ...getFilter(category) }), {})

      emit('filterApply', mergedFilter)

      categories.forEach((category) => {
        category.appliedQuery = structuredClone(category.currentQuery)
      })

      closeSlidebar()
    }

    const resetCategory = (category) => {
      Object.values(getFilter(category)).forEach((el) => {
        const query = {}

        query[el.condition.value ?? 'unassigned'] = el
        updateFilters(query)
      })

      category.currentQuery = []
      category.appliedQuery = []

      requestFilterOptions(category)
    }

    // Resets every category's selection and notifies the parent, mirroring FilterFlyout's resetAndApply.
    const resetAllFilters = () => {
      categories.forEach((category) => resetCategory(category))
      emit('filterApply', {})
    }

    const resetSearch = (category) => {
      category.searchTerm = ''
    }

    // Remove filters that were selected or deselected but not applied for this category
    const restoreAppliedFilterQuery = (category) => {
      const diverged = [
        ...category.currentQuery.filter(id => category.appliedQuery.includes(id) === false),
        ...category.appliedQuery.filter(id => category.currentQuery.includes(id) === false),
      ]

      diverged.forEach(id => updateFilters({ [id]: buildFilterCondition(category, id) }))
    }

    // Reset search, discard unapplied filter changes and collapse the category on close
    const handleClose = (category) => {
      resetSearch(category)
      restoreAppliedFilterQuery(category)
      category.currentQuery = structuredClone(category.appliedQuery)
      setIsExpanded({ categoryId: category.id, isExpanded: false })
    }

    const setExpanded = (category, isExpanded) => {
      setIsExpanded({ categoryId: category.id, isExpanded })

      if (isExpanded) {
        requestFilterOptions(category)
      }
    }

    /**
     *
     * @param category
     * @param isSelected {Boolean}
     * @param option {Object} - { id: string, label: string, selected: boolean }
     */
    const updateQuery = (category, isSelected, option) => {
      if (isSelected) {
        category.currentQuery.push(option.id)
        updateFilters({ [option.id]: buildFilterCondition(category, option.id) })
      } else {
        updateFilters({ [option.id]: buildFilterCondition(category, option.id) })
        category.currentQuery.splice(category.currentQuery.indexOf(option.id), 1)
      }

      // Update ungroupedOptions
      if (option.ungrouped) {
        setUngroupedSelected({ categoryId: category.id, optionId: option.id, value: isSelected })
      } else {
        // Update groupedOptions
        const group = getGroupedOptions(category).find((group) => group.options.some((item) => item.id === option.id))

        if (group) {
          setGroupedSelected({ categoryId: category.id, groupId: group.id, optionId: option.id, value: isSelected })
        }
      }

      requestFilterOptions(category)
    }

    /*
     * Discard unapplied changes and collapse accordions whenever the filter panel stops showing
     * (slidebar closed or switched to another tab)
     */
    const isFilterActive = computed(() => slidebar.value.isOpen && slidebar.value.showTab === 'filter')

    watch(isFilterActive, (isActive, wasActive) => {
      if (wasActive && isActive === false) {
        categories.forEach((category) => handleClose(category))
      }
    })

    onMounted(() => {
      categories.forEach((category) => {
        setIsLoadingMutation({ categoryId: category.id, isLoading: true })
        setIsExpanded({ categoryId: category.id, isExpanded: false })

        /*
         * When the page loads with filters in the URL, their ids arrive asynchronously in the FilterFlyout
         * store; copy them into the category so the matching checkboxes start out selected.
         */
        watch(
          () => store.getters['FilterFlyout/getInitialFlyoutFilterIdsByCategoryId'](category.id),
          (newIds, oldIds) => {
            if (newIds && JSON.stringify(newIds) !== JSON.stringify(oldIds)) {
              category.currentQuery = structuredClone(newIds)
              category.appliedQuery = structuredClone(newIds)
            }
          },
          { deep: true },
        )

        if (queryIds.value.length) {
          requestFilterOptions(category, true)
        }
      })
    })

    return {
      applyAllFilters,
      categories,
      categoryHasPendingChanges,
      getIsExpandedByCategoryId,
      getItemsSelected,
      getSearchedGroupedOptions,
      getSearchedUngroupedOptions,
      hasPendingChanges,
      hasSelectedFilters,
      isChecked,
      isLoading,
      isVisible,
      resetAllFilters,
      resetSearch,
      setExpanded,
      updateQuery,
    }
  },
}
</script>
