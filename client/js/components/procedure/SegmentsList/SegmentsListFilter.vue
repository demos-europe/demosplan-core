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
  <!-- Negative margin is to reset DpSlidebar's u-ml-1_5 -->
  <div class="flex flex-col flex-1 min-h-0 -ml-5">
    <h2 class="shrink-0 p-4 pt-0 border-b border-neutral bg-surface shadow-[0_3px_4px_-3px_rgba(0,0,0,0.26)]">
      {{ Translator.trans('filter') }}
    </h2>

    <div class="flex-1 min-h-0 overflow-y-auto">
      <dp-accordion
        v-for="category in categories"
        :key="category.id"
        class="p-4 border-b border-neutral"
        :data-cy="`segmentsListFilter:${category.id}`"
        :show-border="false"
        :show-status-dot="categoryHasPendingChanges(category)"
        :status-dot-label="Translator.trans('unsaved.changes')"
        :title="category.label"
        compressed
        @item:toggle="(isVisible) => setExpanded(category, isVisible)"
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
              class="m-0 p-0 pb-2 list-none border-b border-neutral mb-3"
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
              <h3 class="text-base font-normal m-2">
                {{ Translator.trans('filter.active') }}
              </h3>
              <ul class="m-0 list-none p-2 pt-0">
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
import {
  dataTableSearch,
  DpAccordion,
  DpButton,
  DpLoading,
  DpResettableInput,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import FilterFlyoutCheckbox from './FilterFlyoutCheckbox'

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

  data () {
    return {
      categories: Object.values(this.filters).map((filterDefinition, idx) => ({
        appliedQuery: [],
        currentQuery: [],
        hint: filterDefinition.labelTranslationKey !== 'tags',
        id: `${filterDefinition.labelTranslationKey}:${idx}`,
        label: Translator.trans(filterDefinition.labelTranslationKey),
        memberOf: this.groupName(filterDefinition.labelTranslationKey),
        operator: filterDefinition.comparisonOperator,
        path: filterDefinition.rootPath,
        searchTerm: '',
      })),
    }
  },

  computed: {
    ...mapGetters('FilterFlyout', [
      'getFilterQuery',
      'getGroupedOptionsByCategoryId',
      'getInitialFlyoutFilterIdsByCategoryId',
      'getIsLoadingByCategoryId',
      'getUngroupedOptionsByCategoryId',
    ]),

    hasPendingChanges () {
      return this.categories.some((category) => this.categoryHasPendingChanges(category))
    },

    hasSelectedFilters () {
      return this.categories.some((category) => category.currentQuery.length > 0)
    },

    // Same extraction as SegmentsList's own `queryIds` computed, applied to the same initialFilter data.
    queryIds () {
      let ids = []

      if (
        Array.isArray(this.initialFilter) === false &&
        Object.values(this.initialFilter).length > 0
      ) {
        ids = Object.values(this.initialFilter)
          .filter((el) => el.condition) // Remove group objects
          .map((el) => {
            if (!el.condition.value) {
              return 'unassigned'
            }

            return el.condition.value
          })
      }

      return ids
    },
  },

  methods: {
    ...mapActions('FilterFlyout', {
      updateFilters: 'updateFilterQuery',
    }),

    ...mapMutations('FilterFlyout', {
      setGroupedSelected: 'setGroupedOptionSelected',
      setIsExpanded: 'setIsExpanded',
      setIsLoadingMutation: 'setIsLoading',
      setUngroupedSelected: 'setUngroupedOptionSelected',
    }),

    applyAllFilters () {
      const mergedFilter = this.categories.reduce((acc, category) => ({ ...acc, ...this.getFilter(category) }), {})

      this.$emit('filterApply', mergedFilter)

      this.categories.forEach((category) => {
        category.appliedQuery = JSON.parse(JSON.stringify(category.currentQuery))
      })
    },

    categoryHasPendingChanges (category) {
      if (category.currentQuery.length !== category.appliedQuery.length) {
        return true
      }

      return category.currentQuery.some((id) => category.appliedQuery.includes(id) === false)
    },

    // Builds the JSON:API filter for a category's selected ids; 'unassigned' maps to IS NULL.
    getFilter (category) {
      const filter = {}

      category.currentQuery.forEach((id) => {
        if (id === 'unassigned') {
          filter[id] = {
            condition: {
              path: category.path,
              operator: 'IS NULL',
            },
          }
        } else {
          filter[id] = {
            condition: {
              path: category.path,
              value: id,
              operator: category.operator,
            },
          }
        }

        if (category.memberOf) {
          filter[id].condition.memberOf = category.memberOf
        }
      })

      return filter
    },

    getGroupedOptions (category) {
      return this.getGroupedOptionsByCategoryId(category.id) || []
    },

    /**
     * {Array of Objects} selected filterItems, same structure as items
     */
    getItemsSelected (category) {
      const items = [
        ...this.getUngroupedOptions(category),
        ...this.getGroupedOptions(category).flatMap((group) => group.options),
      ]

      return items.filter((item) => item.selected)
    },

    getSearchedGroupedOptions (category) {
      return this.getGroupedOptions(category).map((group) => ({
        ...group,
        options: dataTableSearch(category.searchTerm, group.options, ['label']),
      })).filter((group) => group.options.length > 0)
    },

    getSearchedUngroupedOptions (category) {
      return dataTableSearch(category.searchTerm, this.getUngroupedOptions(category), ['label'])
    },

    getUngroupedOptions (category) {
      return this.getUngroupedOptionsByCategoryId(category.id) || []
    },

    /*
     * Filters within one category are OR-combined by giving them a shared group key (memberOf).
     * 'tags' is the exception and uses none. Group keys can't contain '.', so 'workflow.places'
     * becomes 'workflow-places_group'.
     */
    groupName (filterType) {
      if (filterType === 'tags') {
        return null
      }

      return `${filterType.replaceAll('.', '-')}_group`
    },

    isChecked (category, optionId) {
      return category.currentQuery.includes(optionId)
    },

    isLoading (category) {
      return this.getIsLoadingByCategoryId(category.id) ?? false
    },

    /**
     * Emits a 'filterOptions:request' event with the provided query parameters.
     */
    requestFilterOptions (category, isInitialWithQuery = false) {
      // For OR groups (memberOf is set), exclude this group's own filters so counts always show full availability
      let filter = this.getFilterQuery

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

      this.$emit('filterOptions:request', {
        category: { id: category.id, label: category.label },
        currentQuery: category.currentQuery,
        filter,
        isInitialWithQuery,
        path: category.path,
      })
    },

    // Resets every category's selection and notifies the parent, mirroring FilterFlyout's resetAndApply.
    resetAllFilters () {
      this.categories.forEach((category) => this.resetCategory(category))
      this.$emit('filterApply', {})
    },

    resetCategory (category) {
      Object.values(this.getFilter(category)).forEach((el) => {
        const query = {}

        query[el.condition.value ?? 'unassigned'] = el
        this.updateFilters(query)
      })

      category.currentQuery = []
      category.appliedQuery = []

      this.requestFilterOptions(category)
    },

    resetSearch (category) {
      category.searchTerm = ''
    },

    setExpanded (category, isExpanded) {
      this.setIsExpanded({ categoryId: category.id, isExpanded })

      if (isExpanded) {
        this.requestFilterOptions(category)
      }
    },

    /**
     *
     * @param category
     * @param isSelected {Boolean}
     * @param option {Object} - { id: string, label: string, selected: boolean }
     */
    updateQuery (category, isSelected, option) {
      if (isSelected) {
        category.currentQuery.push(option.id)
        this.updateFilters({ [option.id]: this.getFilter(category)[option.id] })
      } else {
        this.updateFilters({ [option.id]: this.getFilter(category)[option.id] })
        category.currentQuery.splice(category.currentQuery.indexOf(option.id), 1)
      }

      // Update ungroupedOptions
      if (option.ungrouped) {
        this.setUngroupedSelected({ categoryId: category.id, optionId: option.id, value: isSelected })
      } else {
        // Update groupedOptions
        const group = this.getGroupedOptions(category).find((group) => group.options.some((item) => item.id === option.id))

        if (group) {
          this.setGroupedSelected({ categoryId: category.id, groupId: group.id, optionId: option.id, value: isSelected })
        }
      }

      this.requestFilterOptions(category)
    },
  },

  mounted () {
    this.categories.forEach((category) => {
      this.setIsLoadingMutation({ categoryId: category.id, isLoading: true })
      this.setIsExpanded({ categoryId: category.id, isExpanded: false })

      /*
       * When the page loads with filters in the URL, their ids arrive asynchronously in the FilterFlyout
       * store; copy them into the category so the matching checkboxes start out selected.
       */
      this.$watch(
        () => this.getInitialFlyoutFilterIdsByCategoryId(category.id),
        (newIds, oldIds) => {
          if (newIds && JSON.stringify(newIds) !== JSON.stringify(oldIds)) {
            category.currentQuery = JSON.parse(JSON.stringify(newIds))
            category.appliedQuery = JSON.parse(JSON.stringify(newIds))
          }
        },
        { deep: true },
      )

      if (this.queryIds.length) {
        this.requestFilterOptions(category, true)
      }
    })
  },
}
</script>
