<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div class="sm:relative flex flex-col sm:flex-row flex-wrap space-x-1 space-x-reverse space-y-1 col-span-1 sm:col-span-7 ml-0 pl-0 sm:ml-2 sm:pl-[38px]">
    <div class="sm:absolute sm:top-0 sm:left-0 mt-1">
      <dp-flyout
        align="left"
        :aria-label="Translator.trans('filters.more')"
        class="bg-surface-medium rounded pb-1 pt-[4px]"
        data-cy="dpAddOrganisationList:filterCategories">
        <template v-slot:trigger>
                <span :title="Translator.trans('filters.more')">
                  <dp-icon
                    aria-hidden="true"
                    class="inline"
                    icon="faders" />
                </span>
        </template>
        <!-- 'More filters' flyout -->
        <div>
          <button
            class="btn--blank o-link--default ml-auto"
            data-cy="dpAddOrganisationList:toggleFilterCategories"
            v-text="Translator.trans('toggle_all')"
            @click="toggleAllCategories" />
          <div v-if="!isLoading">

            <dp-checkbox
              v-for="category in filterCategories"
              :key="category.id"
              :id="`filterCategorySelect:${category.label}`"
              :checked="selectedFilterCategories.includes(category.label)"
              :data-cy="`dpAddOrganisationList:filterCategoriesSelect:${category.label}`"
              :disabled="checkIfDisabled(appliedFilterQuery, category.id)"
              :label="{
                      text: `${category.label} (${getSelectedOptionsCount(appliedFilterQuery, category.id)})`
                    }"
              @change="handleChange(category.label, !selectedFilterCategories.includes(category.label))" />
          </div>
        </div>
      </dp-flyout>
    </div>

    <filter-flyout
      v-for="category in filterCategoriesToBeDisplayed"
      :key="`filter_${category.label}`"
      ref="filterFlyout"
      :category="{ id: category.id, label: category.label }"
      class="inline-block"
      :data-cy="`dpAddOrganisationList:${category.label}`"
      :initial-query-ids="queryIds"
      :member-of="category.memberOf"
      :operator="category.comparisonOperator"
      :path="category.rootPath"
      @filterApply="(filtersToBeApplied) => applyFilter(filtersToBeApplied, category.id)"
      @filterOptions:request="(params) => createFilterOptions({ ...params, categoryId: category.id})" />
  </div>

  <dp-button
    class="h-fit col-span-1 sm:col-span-2 mt-1 justify-center"
    data-cy="dpAddOrganisationList:resetFilter"
    :disabled="!isQueryApplied"
    :text="Translator.trans('reset')"
    variant="outline"
    v-tooltip="Translator.trans('search.filter.reset')"
    @click="reset" />
</template>

<script>
import { DpButton, DpCheckbox, DpIcon, DpFlyout } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState} from 'vuex'
import FilterFlyout from '@DpJs/components/procedure/SegmentsList/FilterFlyout.vue'

export default {
  name: 'ClientSideTagFilter',

  components: {
    DpButton,
    DpCheckbox,
    DpIcon,
    DpFlyout,
    FilterFlyout
  },

  props: {
    filterCategories: {
      type: Array,
      required: true
    },

    rawItems: {
      type: Array,
      required: true
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      appliedFilterQuery: {},
      currentlySelectedFilterCategories: [],
      institutionTagCategoriesCopy: {},
      initiallySelectedFilterCategories: [],
      isLoading: true,

      filterCategoriesStorage: {
        get () {
          const selectedFilterCategories = localStorage.getItem('visibleFilterFlyouts')
          return selectedFilterCategories ? JSON.parse(selectedFilterCategories) : null
        },
        set (selectedFilterCategories) {
          localStorage.setItem('visibleFilterFlyouts', JSON.stringify(selectedFilterCategories))
        }
      },

      filterQueryStorage: {
        get () {
          const filterQueryInStorage = localStorage.getItem('filterQuery')
          return filterQueryInStorage && filterQueryInStorage !== 'undefined'
              ? JSON.parse(filterQueryInStorage)
              : {}
        },
        set (filterQuery) {
          localStorage.setItem('filterQuery', JSON.stringify(filterQuery))
        },
        reset () {
          localStorage.setItem('filterQuery', JSON.stringify({}))
        }
      }
    }
  },

  computed: {
    ...mapGetters('FilterFlyout', {
      filterQuery: 'getFilterQuery'
    }),

    ...mapState('InstitutionTagCategory', {
      institutionTagCategories: 'items'
    }),

    ...mapState('InstitutionTag', {
      institutionTagItems: 'items'
    }),

    isQueryApplied () {
      return this.currentlySelectedFilterCategories.length > 0
    },

    filterAndEmitItems() {
      if (!this.rawItems) {
        console.error('rawItems is undefined!')
        return
      }

      let filteredItems = this.rawItems

      // Wenn Filter angewendet sind
      if (Object.keys(this.appliedFilterQuery).length > 0) {
        filteredItems = this.rawItems.filter(item => {
          return Object.values(this.appliedFilterQuery).every(filterCondition => {
            if (!filterCondition.condition) return true

            const tagIds = item.assignedTags?.map(tag => tag.id) || []
            return tagIds.includes(filterCondition.condition.value)
          })
        })
      }

      this.$emit('items-filtered', filteredItems)
    },

    filterCategoriesToBeDisplayed () {
      return (this.filterCategories || [])
        .filter(filter =>
          this.currentlySelectedFilterCategories.includes(filter.label))
    },

    selectedFilterCategories () {
      return this.currentlySelectedFilterCategories
    },

    queryIds () {
      if (Object.keys(this.appliedFilterQuery).length === 0) {
        return []
      }
      return Object.values(this.appliedFilterQuery)
        .filter(el => el && el.condition && el.condition.value)
        .map(el => el.condition.value)
    }
  },

  watch: {
    appliedFilterQuery: {
      handler() {
        const result = this.filterAndEmitItems  // ✅ Ohne () - triggert die computed
      },
      deep: true,
      immediate: true
    },

    rawItems: {
      handler() {
        const result = this.filterAndEmitItems
      },
      immediate: true
    }
  },

  methods: {
    ...mapActions('FilterFlyout', {
      updateFilterQuery: 'updateFilterQuery'
    }),

    ...mapActions('InstitutionTagCategory', {
      fetchInstitutionTagCategories: 'list'
    }),

    ...mapMutations('FilterFlyout', {
      setInitialFlyoutFilterIds: 'setInitialFlyoutFilterIds',
      setIsFilterFlyoutLoading: 'setIsLoading',
      setUngroupedFilterOptions: 'setUngroupedOptions'
    }),

    applyFilter (filter, categoryId) {
      console.log('🚀 applyFilter ENTRY - filter:', filter, 'categoryId:', categoryId)

      console.log('🔥 APPLY FILTER CALLED:', filter)
      console.log('🔥 categoryId:', categoryId)
      console.log('🔥 BEFORE appliedFilterQuery:', this.appliedFilterQuery)

      this.appliedFilterQuery = this.setAppliedFilterQuery(
        filter,
        this.appliedFilterQuery,
        this.filterQuery,
          null,
          categoryId
      )
      console.log('🔥 AFTER appliedFilterQuery:', this.appliedFilterQuery)

      this.filterQueryStorage.set(this.appliedFilterQuery)
    },


    checkIfDisabled(appliedFilterQuery, categoryId) {
      return !!Object.values(appliedFilterQuery)
        .find(el => el.condition?.memberOf === `${categoryId}_group`)
    },

    createFilterOptions (params) {
      const { categoryId, isInitialWithQuery } = params

      // 1. Selected Filter IDs aus APPLIED filters holen (KORREKT)
      const selectedFilterOptionIds = Object.keys(this.appliedFilterQuery).filter(id => !id.includes('_group'))

      // 2. Tags für diese Kategorie holen
      let filterOptions = this.institutionTagCategoriesCopy[categoryId]?.relationships?.tags?.data.length > 0
          ? this.institutionTagCategoriesCopy[categoryId].relationships.tags.list()
          : []

      if (Object.keys(filterOptions).length > 0) {
        // 3. Options mit selected Status mappen
        filterOptions = Object.values(filterOptions).map(option => {
          const { id, attributes } = option
          const { name } = attributes
          const selected = selectedFilterOptionIds.includes(id)

          return {
            id,
            label: name,
            selected
          }
        })
      }

      this.setUngroupedFilterOptions({ categoryId, options: filterOptions })
      this.setIsFilterFlyoutLoading({ categoryId, isLoading: false })

      if (isInitialWithQuery) {
        const filterQueryFromStorage = this.filterQueryStorage.get()
        if (Object.keys(filterQueryFromStorage).length > 0) {
          this.filterQuery = { ...this.filterQuery, ...filterQueryFromStorage }
        }
      }
    },

    getInstitutionTagCategories(isInitial = false) {
      return this.fetchInstitutionTagCategories({
        fields: {
          InstitutionTagCategory: [
            'creationDate',
            'name',
            'tags'
          ].join(),
          InstitutionTag: [
            'creationDate',
            'isUsed',
            'name',
            'category'
          ].join()
        },
        include: [
          'tags',
          'tags.category'
        ].join()
      })
        .then(() => {
          // Copy the object to avoid issues with filter requests
          this.institutionTagCategoriesCopy = { ...this.institutionTagCategories }

          if (isInitial) {
            // Initialwerte für Filter-Kategorien setzen (originalgetreu)
            const selectedFilterCategoriesInStorage = this.filterCategoriesStorage.get()

            this.initiallySelectedFilterCategories = selectedFilterCategoriesInStorage !== null
                ? selectedFilterCategoriesInStorage
                : Object.values(this.institutionTagCategoriesCopy).slice(0, 5).map(category => category.attributes.name)

            // Aktuelle Auswahl setzen
            this.currentlySelectedFilterCategories = [...this.initiallySelectedFilterCategories]
          }


          return this.institutionTagCategoriesCopy
        })
        .catch(err => {
          console.error('Error loading tag categories:', err)
          return {}
        })
    },

    getSelectedOptionsCount(appliedFilterQuery, categoryId) {
      const result = Object.values(appliedFilterQuery)
        .filter(el => el.condition?.memberOf === `${categoryId}_group`).length


      console.log(`📊 getSelectedOptionsCount for ${categoryId}:`, result)
      console.log(`📊 appliedFilterQuery:`, appliedFilterQuery)

      return result

    },

    handleChange(categoryLabel, isSelected) {

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
      this.filterCategoriesStorage.set(this.currentlySelectedFilterCategories)
    },

    loadFilterStateFromStorage () {
      console.log('🔄 LOAD START: localStorage keys:', Object.keys(localStorage))

      const savedCategories = this.filterCategoriesStorage.get()
      console.log('🔄 LOAD: savedCategories:', savedCategories)

      if (savedCategories) {
        this.currentlySelectedFilterCategories = savedCategories
      }

      const savedFilterQuery = this.filterQueryStorage.get()
      console.log('🔄 LOAD: savedFilterQuery:', savedFilterQuery)
      console.log('🔄 LOAD: keys count:', Object.keys(savedFilterQuery).length)

      console.log('🔄 RELOAD: savedFilterQuery:', savedFilterQuery)
      console.log('🔄 RELOAD: keys count:', Object.keys(savedFilterQuery).length)

      if (Object.keys(savedFilterQuery).length > 0) {
        console.log('🔄 LOAD: Setting appliedFilterQuery to:', savedFilterQuery)

        this.appliedFilterQuery = savedFilterQuery
        this.updateFilterQuery(savedFilterQuery)

        console.log('🔄 RELOAD: appliedFilterQuery set to:', this.appliedFilterQuery)
        console.log('🔄 RELOAD: filterQuery set to:', this.filterQuery)

        // Force watch trigger
        this.$nextTick(() => {
          console.log('🔄 LOAD: After nextTick - appliedFilterQuery:', this.appliedFilterQuery)
          this.filterAndEmitItems
        })

      }
    },

    // Methode 3: Reset alle Filter
    reset() {
      this.appliedFilterQuery = {}
      this.filterQueryStorage.reset()

      // FilterFlyouts resetten
      if (this.$refs.filterFlyout) {
        this.$refs.filterFlyout.forEach(flyout => {
          flyout.reset()
        })
      }

      // Vuex FilterQuery auch resetten
      this.updateFilterQuery({})

    },

    setAppliedFilterQuery (filter, currentAppliedFilterQuery, filterQuery, setMethod = null, categoryId = null) {
      // Remove groups from filter - only keep conditions
      const selectedFilterOptions = Object.fromEntries(
        Object.entries(filter).filter(([_key, value]) => value.condition)
      )
      const isReset = Object.keys(selectedFilterOptions).length === 0
      const isAppliedFilterQueryEmpty = Object.keys(currentAppliedFilterQuery).length === 0
      let newAppliedFilterQuery = { ...currentAppliedFilterQuery }

      if (!isReset && isAppliedFilterQueryEmpty) {
        Object.values(selectedFilterOptions).forEach(option => {
          if (setMethod) {
            setMethod(newAppliedFilterQuery, option.condition.value, option)
          } else {
            newAppliedFilterQuery[option.condition.value] = option
          }
        })
      } else if (isReset) {
        console.log('🔴 RESET DEBUG')
        console.log('🔴 filter:', filter)
        console.log('🔴 categoryId:', categoryId)

        if (categoryId) {
          // FilterFlyout Reset → nur diese Kategorie leeren
          console.log('🔴 Resetting only category:', categoryId)
          const groupKey = `${categoryId}_group`
          Object.keys(newAppliedFilterQuery).forEach(key => {
            if (newAppliedFilterQuery[key].condition?.memberOf === groupKey) {
              console.log('🔴 Deleting filter:', key)
              delete newAppliedFilterQuery[key]
            }
          })
        } else {
          // Global Reset → alle Filter löschen
          console.log('🔴 Global reset - clearing all filters')
          newAppliedFilterQuery = {}
        }
      } else {
        console.log('🟡 Cross-category case')
        console.log('🟡 selectedFilterOptions:', selectedFilterOptions)
        console.log('🟡 currentAppliedFilterQuery BEFORE:', currentAppliedFilterQuery)

        // Cross-category filtering: merge selectedFilterOptions with existing filters from other categories
        const currentCategoryGroupKey = Object.values(selectedFilterOptions)[0]?.condition?.memberOf

        console.log('🟡 currentCategoryGroupKey:', currentCategoryGroupKey)


        if (currentCategoryGroupKey) {
          // Remove all existing filters from this category
          Object.keys(newAppliedFilterQuery).forEach(key => {
            if (newAppliedFilterQuery[key].condition?.memberOf === currentCategoryGroupKey) {
              console.log('🟡 Deleting key:', key)

              delete newAppliedFilterQuery[key]
            }
          })
        }

        // Add the new filters for this category
        Object.values(selectedFilterOptions).forEach(option => {
          console.log('🟡 Adding option:', option)

          if (setMethod) {
            setMethod(newAppliedFilterQuery, option.condition.value, option)
          } else {
            newAppliedFilterQuery[option.condition.value] = option
          }
        })
        console.log('🟡 newAppliedFilterQuery AFTER:', newAppliedFilterQuery)

      }

      return newAppliedFilterQuery
    },

    toggleAllCategories() {
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

      this.currentlySelectedFilterCategories = allSelected
          ? categoriesWithSelectedOptions  // Alle → nur die mit aktiven Filtern
          : this.filterCategories.map(filterCategory => filterCategory.label)  // Nicht alle → alle

      this.filterCategoriesStorage.set(this.currentlySelectedFilterCategories)
    },

    updateFilterQuery(payload) {
      this.filterQuery = { ...this.filterQuery, ...payload }
console.log('💾 SAVE: this.filterQuery:', this.filterQuery)
console.log('💾 SAVE: this.appliedFilterQuery:', this.appliedFilterQuery)
 console.log('💾 SAVE: Saving to localStorage:', this.appliedFilterQuery)
      this.filterQueryStorage.set(this.appliedFilterQuery)
    }
  },

  mounted() {
    this.loadFilterStateFromStorage()

    const promises = [
      this.getInstitutionTagCategories(true)
    ]

    Promise.allSettled(promises)
        .then(() => {
          this.isLoading = false
        })
  }
}
</script>
