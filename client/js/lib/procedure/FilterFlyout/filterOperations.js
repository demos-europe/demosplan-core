import { filterQueryStorage } from './filterStorage'

export const filterOperations = {
  /**
   * Apply filter query and trigger page reload
   * @param {Object} context - Vue component instance
   * @param {Object} filter - Filter object
   * @param {string} categoryId - Category ID
   */
  applyFilterQuery(context, filter, categoryId) {
    context.setAppliedFilterQuery(filter)
    filterQueryStorage.set(context.filterQuery)
    context.getInstitutionsByPage(1, categoryId)
  },

  /**
   * Create filter options for a category
   * @param {Object} context - Vue component instance
   * @param {Object} params - Parameters object
   */
  createFilterOptions(context, params) {
    const { categoryId, isInitialWithQuery } = params
    let filterOptions = context.institutionTagCategoriesCopy[categoryId]?.relationships?.tags?.data.length >
    0
      ? context.institutionTagCategoriesCopy[categoryId].relationships.tags.list() : []
    const filterQueryFromStorage = filterQueryStorage.get()
    const selectedFilterOptionIds = Object.keys(filterQueryFromStorage).filter(id => !id.includes('_group'))

    if (Object.keys(filterOptions).length > 0) {
      filterOptions = Object.values(filterOptions).map(option => {
        const { id, attributes } = option
        const { name } = attributes
        const selected = selectedFilterOptionIds.includes(id)

        return { id, label: name, selected }
      })
    }

    context.setUngroupedFilterOptions({ categoryId, options: filterOptions })
    context.setIsFilterFlyoutLoading({ categoryId, isLoading: false })

    if (isInitialWithQuery) {
      filterOperations.setFilterOptionsFromFilterQuery(context)
    }
  },

  /**
   * Set filter options from filter query stored in localStorage
   * @param {Object} context - Vue component instance
   */
  setFilterOptionsFromFilterQuery(context) {
    const filterQueryFromStorage = filterQueryStorage.get()
    const categoryIdsWithSelectedFilterOptions = Object.keys(filterQueryFromStorage)
      .filter(id => id.includes('_group'))
      .map(id => id.replace('_group', ''))

    categoryIdsWithSelectedFilterOptions.forEach(id => {
      const selectedFilterOptionIds = Object.values(filterQueryFromStorage)
        .filter(el => el.condition?.memberOf === `${id}_group`)
        .map(el => el.condition.value)

      context.setInitialFlyoutFilterIds({ categoryId: id, filterIds: selectedFilterOptionIds })
    })
  },

  /**
   * Set applied filter query from filter object
   * @param {Object} context - Vue component instance
   * @param {Object} filter - Filter object
   */
  setAppliedFilterQuery(context, filter) {
    // Remove groups from filter
    const selectedFilterOptions = Object.fromEntries(Object.entries(filter).filter(([_key, value]) =>
      value.condition))
    const isReset = Object.keys(selectedFilterOptions).length === 0
    const isAppliedFilterQueryEmpty = Object.keys(context.appliedFilterQuery).length === 0

    if (!isReset && isAppliedFilterQueryEmpty) {
      Object.values(selectedFilterOptions).forEach(option => {
        if (context.$set) {
          // Vue 2
          context.$set(context.appliedFilterQuery, option.condition.value, option)
        } else {
          // Vue 3
          context.appliedFilterQuery[option.condition.value] = option
        }
      })
    } else if (isReset) {
      const filtersWithConditions = Object.fromEntries(
        Object.entries(context.filterQuery).filter(([key, value]) => value.condition)
      )
      context.appliedFilterQuery = Object.keys(filtersWithConditions).length ? filtersWithConditions : {}
    } else {
      context.appliedFilterQuery = selectedFilterOptions
    }
  },

  /**
   * Set filter query from localStorage
   * @param {Object} context - Vue component instance
   */
  setFilterQueryFromStorage(context) {
    const filterQueryFromStorage = filterQueryStorage.get()
    const filterIds = Object.keys(filterQueryFromStorage)

    if (filterIds.length > 0) {
      filterIds.forEach(id => {
        const payload = { [id]: filterQueryFromStorage[id] }

        if (filterQueryFromStorage[id].condition) {
          context.updateFilterQuery(payload)
        }
      })
    }
  },

  /**
   * Set applied filter query from localStorage
   * @param {Object} context - Vue component instance
   */
  setAppliedFilterQueryFromStorage(context) {
    const filterQueryFromStorage = filterQueryStorage.get()
    filterOperations.setAppliedFilterQuery(context, filterQueryFromStorage)
  },

  /**
   * Toggle all selected filter categories
   * @param {Object} context - Vue component instance
   */
  toggleAllSelectedFilterCategories(context) {
    const allSelected = context.currentlySelectedFilterCategories.length ===
      Object.keys(context.allFilterCategories).length
    const selectedFilterOptions = Object.values(context.appliedFilterQuery)
    const categoriesWithSelectedOptions = []

    selectedFilterOptions.forEach(option => {
      const categoryId = option.condition.memberOf.replace('_group', '')
      const category = context.allFilterCategories[categoryId]

      if (category && !categoriesWithSelectedOptions.includes(category.label)) {
        categoriesWithSelectedOptions.push(category.label)
      }
    })

    context.currentlySelectedFilterCategories = allSelected
      ? categoriesWithSelectedOptions
      : Object.values(context.allFilterCategories).map(filterCategory => filterCategory.label)
  },

  /**
   * Update currently selected filter categories
   * @param {Object} context - Vue component instance
   * @param {string} filterCategoryName - Category name
   * @param {boolean} isSelected - Is selected
   */
  updateCurrentlySelectedFilterCategories(context, filterCategoryName, isSelected) {
    if (isSelected) {
      context.currentlySelectedFilterCategories.push(filterCategoryName)
    } else {
      context.currentlySelectedFilterCategories = context.currentlySelectedFilterCategories.filter(category =>
        category !== filterCategoryName)
    }
  },

  /**
   * Reset filter query and search
   * @param {Object} context - Vue component instance
   */
  resetQuery(context) {
    context.searchTerm = ''
    Object.keys(context.allFilterCategories).forEach((filterCategoryId, idx) => {
      const filterFlyoutComponentExists = typeof context.$refs.filterFlyout[idx] !== 'undefined'
      const hasFilterCategorySelectedOption = !!Object.values(context.filterQuery).find(el =>
        el.condition?.memberOf === `${filterCategoryId}_group`)

      if (filterFlyoutComponentExists) {
        context.$refs.filterFlyout[idx].reset()
        const isFilterFlyoutVisible =
          context.currentlySelectedFilterCategories.includes(context.allFilterCategories[filterCategoryId].label)

        if (!isFilterFlyoutVisible && hasFilterCategorySelectedOption) {
          const selectedFilterOptions = Object.values(context.filterQuery).filter(el =>
            el.condition?.memberOf === `${filterCategoryId}_group`)
          const payload = selectedFilterOptions.reduce((acc, el) => {
            acc[el.condition.value] = el
            return acc
          }, {})

          context.updateFilterQuery(payload)
        }
      }
    })

    filterQueryStorage.reset()
    context.appliedFilterQuery = {}
    context.getInstitutionsByPage(1)
  }
}

