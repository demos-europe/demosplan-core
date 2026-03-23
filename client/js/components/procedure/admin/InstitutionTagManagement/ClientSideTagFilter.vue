<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div class="sm:relative flex flex-row flex-wrap h-fit gap-1 items-center col-span-1 sm:col-span-7 ml-0 pl-0 sm:ml-2 sm:pl-[38px]">
    <div class="sm:absolute sm:top-0 sm:left-0 mt-1">
      <dp-flyout
        align="left"
        :aria-label="Translator.trans('filters.more')"
        class="bg-surface-medium rounded pb-1 pt-[4px] rounded-md"
        data-cy="clientSideTagFilter:filterCategories"
      >
        <template v-slot:trigger>
          <span :title="Translator.trans('filters.more')">
            <dp-icon
              aria-hidden="true"
              class="inline"
              icon="faders"
            />
          </span>
        </template>
        <!-- 'More filters' flyout -->
        <div class="min-w-12 my-1">
          <button
            class="btn--blank o-link--default ml-auto mb-1"
            data-cy="clientSideTagFilter:toggleFilterCategories"
            @click="toggleAllCategories"
            v-text="Translator.trans('toggle_all')"
          />
          <div v-if="!isLoading">
            <dp-checkbox
              v-for="category in filterCategories"
              :id="`filterCategorySelect:${category.label}`"
              :key="category.id"
              :checked="selectedFilterCategories.includes(category.label)"
              :data-cy="`clientSideTagFilter:filterCategoriesSelect:${category.label}`"
              :disabled="checkIfDisabled(appliedFilterQuery, category.id)"
              :label="{
                text: `${category.label} (${getSelectedOptionsCount(appliedFilterQuery, category.id)})`
              }"
              @change="handleChange(category.label, !selectedFilterCategories.includes(category.label))"
            />
          </div>
        </div>
      </dp-flyout>
    </div>

    <filter-flyout
      v-for="category in filterCategoriesToBeDisplayed"
      :key="`filter_${category.label}`"
      ref="filterFlyout"
      :category="{ id: category.id, label: category.label }"
      class="inline-block mt-1"
      align="left"
      :data-cy="`ClientSideTagFilter:${category.label}`"
      :initial-query-ids="queryIds"
      :member-of="category.memberOf"
      :operator="category.comparisonOperator"
      :path="category.rootPath"
      variant="dark"
      @filter-apply="(filtersToBeApplied) => applyFilter(filtersToBeApplied, category.id)"
      @filter-options:request="(params) => createFilterOptions({ ...params, categoryId: category.id})"
    />
  </div>

  <dp-button
    v-tooltip="Translator.trans('search.filter.reset')"
    class="h-fit col-span-1 sm:col-span-2 mt-1 justify-center"
    data-cy="ClientSideTagFilter:resetFilter"
    :disabled="!isQueryApplied"
    :text="Translator.trans('reset')"
    variant="outline"
    @click="reset"
  />
</template>

<script>
import { DpButton, DpCheckbox, DpFlyout, DpIcon } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import FilterFlyout from '@DpJs/components/procedure/SegmentsList/FilterFlyout'

/**
 * ClientSideTagFilter - Client-side filtering component for institution tags
 *
 *  Provides filtering functionality for organization tables by institution tags.
 *  Handles cross-category filtering, localStorage persistence, and FilterFlyout integration.
 * @component
 * @example
 * <client-side-tag-filter
 *   :filter-categories="allFilterCategories"
 *   :raw-items="rowItems"
 *   @itemsFiltered="filteredItems = $event" />
 */
export default {
  name: 'ClientSideTagFilter',

  components: {
    DpButton,
    DpCheckbox,
    DpIcon,
    DpFlyout,
    FilterFlyout,
  },

  props: {
    filterCategories: {
      type: Array,
      required: true,
    },

    rawItems: {
      type: Array,
      required: true,
    },

    searchApplied: {
      type: Boolean,
      required: false,
      default: false,
    },
  },

  emits: [
    'itemsFiltered',
    'reset',
  ],

  data () {
    return {
      appliedFilterQuery: {},
      currentlySelectedFilterCategories: [],
      institutionTagCategoriesCopy: {},
      initiallySelectedFilterCategories: [],
      isLoading: true,
    }
  },

  computed: {
    ...mapGetters('FilterFlyout', {
      filterQuery: 'getFilterQuery',
    }),

    ...mapState('InstitutionTag', {
      institutionTagItems: 'items',
    }),

    ...mapState('InstitutionTagCategory', {
      institutionTagCategories: 'items',
    }),

    filterCategoriesToBeDisplayed () {
      return (this.filterCategories || [])
        .filter(filter =>
          this.currentlySelectedFilterCategories.includes(filter.label))
    },

    isQueryApplied () {
      return Object.keys(this.appliedFilterQuery).length > 0 || this.searchApplied
    },

    queryIds () {
      if (Object.keys(this.appliedFilterQuery).length === 0) {
        return []
      }
      return Object.values(this.appliedFilterQuery)
        .filter(el => el && el.condition && el.condition.value)
        .map(el => el.condition.value)
    },

    selectedFilterCategories () {
      return this.currentlySelectedFilterCategories
    },
  },

  watch: {
    appliedFilterQuery: {
      handler () {
        this.filterAndEmitItems()
      },
      deep: true,
      immediate: true,
    },

    rawItems: {
      handler () {
        this.filterAndEmitItems()
      },
      immediate: true,
    },
  },

  methods: {
    ...mapActions('FilterFlyout', [
      'updateFilterQuery',
    ]),

    ...mapActions('InstitutionTagCategory', {
      fetchInstitutionTagCategories: 'list',
    }),

    ...mapMutations('FilterFlyout', {
      setInitialFlyoutFilterIds: 'setInitialFlyoutFilterIds',
      setIsFilterFlyoutLoading: 'setIsLoading',
      setUngroupedFilterOptions: 'setUngroupedOptions',
    }),

    applyFilter (filter, categoryId) {
      this.appliedFilterQuery = this.setAppliedFilterQuery(
        filter,
        this.appliedFilterQuery,
        categoryId,
      )

      this.updateFilterQuery(filter)
      this.setFilterQueryStorage(this.appliedFilterQuery)
    },

    checkIfDisabled (appliedFilterQuery, categoryId) {
      return !!Object.values(appliedFilterQuery)
        .find(el => el.condition?.memberOf === `${categoryId}_group`)
    },

    /**
     * Creates filter options for FilterFlyout with correct selected state
     * Maps institution tags to FilterFlyout format and marks selected options
     * @param {Object} params - Parameters containing categoryId and isInitialWithQuery
     */
    createFilterOptions (params) {
      const { categoryId, isInitialWithQuery } = params
      const selectedFilterOptionIds = Object.keys(this.appliedFilterQuery).filter(id => !id.includes('_group'))

      let filterOptions = this.institutionTagCategoriesCopy[categoryId]?.relationships?.tags?.data.length > 0 ?
        this.institutionTagCategoriesCopy[categoryId].relationships.tags.list() :
        []

      if (Object.keys(filterOptions).length > 0) {
        filterOptions = Object.values(filterOptions)
          .map(option => {
            const { id, attributes } = option
            const { name } = attributes
            const selected = selectedFilterOptionIds.includes(id)

            return {
              id,
              label: name,
              selected,
            }
          })
      }

      this.setUngroupedFilterOptions({ categoryId, options: filterOptions })
      this.setIsFilterFlyoutLoading({ categoryId, isLoading: false })

      if (isInitialWithQuery) {
        const filterQueryFromStorage = this.getFilterQueryStorage()
        if (Object.keys(filterQueryFromStorage).length > 0) {
          this.updateFilterQuery({ ...this.filterQuery, ...filterQueryFromStorage })
        }
      }
    },

    /**
     * Filters raw items based on applied filter query and emits result to parent
     * Implements AND logic across filter conditions
     */
    filterAndEmitItems () {
      let filteredItems = this.rawItems

      if (!this.rawItems) {
        console.error('rawItems is undefined!')
        return
      }

      if (Object.keys(this.appliedFilterQuery).length > 0) {
        filteredItems = this.rawItems.filter(item => {
          return Object.values(this.appliedFilterQuery).every(filterCondition => {
            if (!filterCondition.condition) return true

            // Support both 'assignedTags' (OrganisationTable) and 'tags' (InstitutionList) properties
            const itemTags = item.assignedTags || item.tags || []
            const tagIds = itemTags.map(tag => tag.id) || []

            return tagIds.includes(filterCondition.condition.value)
          })
        })
      }
      this.$emit('itemsFiltered', filteredItems)
    },

    getInstitutionTagCategories (isInitial = false) {
      return this.fetchInstitutionTagCategories({
        fields: {
          InstitutionTagCategory: [
            'creationDate',
            'name',
            'tags',
          ].join(),
          InstitutionTag: [
            'creationDate',
            'isUsed',
            'name',
            'category',
          ].join(),
        },
        include: [
          'tags',
          'tags.category',
        ].join(),
      })
        .then(() => {
          this.institutionTagCategoriesCopy = { ...this.institutionTagCategories }

          if (isInitial) {
            const selectedFilterCategoriesInStorage = this.getFilterCategoriesStorage()

            this.initiallySelectedFilterCategories = selectedFilterCategoriesInStorage !== null ?
              selectedFilterCategoriesInStorage :
              Object.values(this.institutionTagCategoriesCopy).slice(0, 5).map(category => category.attributes.name)

            this.currentlySelectedFilterCategories = [...this.initiallySelectedFilterCategories]
          }

          return this.institutionTagCategoriesCopy
        })
        .catch(err => {
          console.error('Error loading tag categories:', err)
          return {}
        })
    },

    getFilterCategoriesStorage () {
      const selectedFilterCategories = localStorage.getItem('visibleFilterFlyouts')
      return selectedFilterCategories ? JSON.parse(selectedFilterCategories) : null
    },

    getFilterQueryStorage () {
      const filterQueryInStorage = localStorage.getItem('filterQuery')
      return filterQueryInStorage && filterQueryInStorage !== 'undefined' ?
        JSON.parse(filterQueryInStorage) :
        {}
    },

    getSelectedOptionsCount (appliedFilterQuery, categoryId) {
      return Object.values(appliedFilterQuery)
        .filter(el => el.condition?.memberOf === `${categoryId}_group`).length
    },

    handleChange (categoryLabel, isSelected) {
      if (isSelected) {
        if (!this.currentlySelectedFilterCategories.includes(categoryLabel)) {
          this.currentlySelectedFilterCategories.push(categoryLabel)
        }
      } else {
        const index = this.currentlySelectedFilterCategories.indexOf(categoryLabel)
        if (index > -1) {
          this.currentlySelectedFilterCategories.splice(index, 1)
        }
      }
      this.setFilterCategoriesStorage(this.currentlySelectedFilterCategories)
    },

    /**
     *  Loads persisted filter state from localStorage
     * Restores both visible categories and applied filters
     */
    loadFilterStateFromStorage () {
      const savedCategories = this.getFilterCategoriesStorage()
      const savedFilterQuery = this.getFilterQueryStorage()

      if (savedCategories) {
        this.currentlySelectedFilterCategories = savedCategories
      }

      if (Object.keys(savedFilterQuery).length > 0) {
        this.appliedFilterQuery = savedFilterQuery
        this.setFilterQueryStorage(this.appliedFilterQuery)
      }
    },

    reset () {
      this.appliedFilterQuery = {}
      this.resetFilterQueryStorage()

      if (this.$refs.filterFlyout) {
        this.$refs.filterFlyout.forEach(flyout => {
          flyout.reset()
        })
      }

      this.updateLocalFilterQuery({})
      this.$emit('reset')
    },

    resetFilterQueryStorage () {
      localStorage.setItem('filterQuery', JSON.stringify({}))
    },

    setFilterCategoriesStorage (selectedFilterCategories) {
      localStorage.setItem('visibleFilterFlyouts', JSON.stringify(selectedFilterCategories))
    },

    setFilterQueryStorage (filterQuery) {
      localStorage.setItem('filterQuery', JSON.stringify(filterQuery))
    },

    /**
     * Core filtering logic handling cross-category filters and resets
     * Manages filter state transitions and category-specific operations
     * @param {Object} filter - Filter object from FilterFlyout
     * @param {Object} currentlyAppliedFilterQuery - Current applied filters
     * @param {string|null} categoryId - Category ID for targeted resets
     * @returns {Object} Updated filter query object
     */
    setAppliedFilterQuery (filter, currentlyAppliedFilterQuery, categoryId = null) {
      const selectedFilterOptions = Object.fromEntries(
        Object.entries(filter).filter(([_key, value]) => value.condition),
      )
      const isReset = Object.keys(selectedFilterOptions).length === 0
      const isAppliedFilterQueryEmpty = Object.keys(currentlyAppliedFilterQuery).length === 0
      let newAppliedFilterQuery = { ...currentlyAppliedFilterQuery }

      if (!isReset && isAppliedFilterQueryEmpty) {
        Object.values(selectedFilterOptions).forEach(option => {
          newAppliedFilterQuery[option.condition.value] = option
        })
      } else if (isReset) {
        if (categoryId) {
          const groupKey = `${categoryId}_group`

          Object.keys(newAppliedFilterQuery).forEach(key => {
            if (newAppliedFilterQuery[key].condition?.memberOf === groupKey) {
              delete newAppliedFilterQuery[key]
            }
          })
        } else {
          newAppliedFilterQuery = {}
        }
      } else {
        const currentCategoryGroupKey = Object.values(selectedFilterOptions)[0]?.condition?.memberOf

        if (currentCategoryGroupKey) {
          Object.keys(newAppliedFilterQuery).forEach(key => {
            if (newAppliedFilterQuery[key].condition?.memberOf === currentCategoryGroupKey) {
              delete newAppliedFilterQuery[key]
            }
          })
        }

        Object.values(selectedFilterOptions).forEach(option => {
          newAppliedFilterQuery[option.condition.value] = option
        })
      }

      return newAppliedFilterQuery
    },

    toggleAllCategories () {
      const allSelected = this.currentlySelectedFilterCategories.length === this.filterCategories.length
      const selectedFilterOptions = Object.values(this.appliedFilterQuery)
      const categoriesWithSelectedOptions = []

      selectedFilterOptions.forEach(option => {
        const categoryId = option.condition.memberOf.replace('_group', '')
        const category = this.filterCategories.find(cat => cat.id === categoryId)

        if (category && !categoriesWithSelectedOptions.includes(category.label)) {
          categoriesWithSelectedOptions.push(category.label)
        }
      })

      this.currentlySelectedFilterCategories = allSelected ?
        categoriesWithSelectedOptions :
        this.filterCategories.map(filterCategory => filterCategory.label)

      this.setFilterCategoriesStorage(this.currentlySelectedFilterCategories)
    },

    updateLocalFilterQuery (payload) {
      this.updateFilterQuery(payload)
      this.setFilterQueryStorage(this.appliedFilterQuery)
    },
  },

  mounted () {
    this.loadFilterStateFromStorage()

    const promises = [
      this.getInstitutionTagCategories(true),
    ]

    Promise.allSettled(promises)
      .then(() => {
        this.isLoading = false
      })
  },
}
</script>
