/**
 * Helper functions for filter category operations
 * Used by FilterFlyout components for common category logic
 */

import { filterCategoriesStorage, filterQueryStorage } from './filterStorage'

export const filterCategoryHelpers = {
  /**
   * Get count of selected options for a category
   * @param {Object} appliedFilterQuery - The applied filter query object
   * @param {string} categoryId - The category ID to count for
   * @returns {number} Number of selected options
   */

  getSelectedOptionsCount (appliedFilterQuery, categoryId) {
    return Object.values(appliedFilterQuery)
      .filter(el => el.condition?.memberOf === `${categoryId}_group`).length
  },

  /**
   * Check if a category should be disabled (has applied filters)
   * @param {Object} appliedFilterQuery - The applied filter query object
   * @param {string} categoryId - The category ID to check
   * @returns {boolean} True if category should be disabled
   */
  checkIfDisabled (appliedFilterQuery, categoryId) {
    return !!Object.values(appliedFilterQuery)
      .find(el => el.condition?.memberOf === `${categoryId}_group`)
  },

  /**
   * Set applied filter query with Vue reactivity support
   * Extracted from DpAddOrganisationList and InstitutionList
   */
  setAppliedFilterQuery (filter, currentAppliedFilterQuery, filterQuery, setMethod = null) {
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
      const filtersWithConditions = Object.fromEntries(
        Object.entries(filterQuery).filter(([key, value]) => value.condition)
      )
      newAppliedFilterQuery = Object.keys(filtersWithConditions).length ? filtersWithConditions : {}
    } else {
      newAppliedFilterQuery = selectedFilterOptions
    }

    return newAppliedFilterQuery
  },

  /**
   * Creates a comprehensive filter manager for list components
   * Consolidates all filtering logic from DpAddOrganisationList and InstitutionList
   * @param {Object} component - Vue component instance (this)
   * @returns {Object} FilterManager with all filtering methods
   */
  createFilterManager (component) {
    return {
      /**
       * Apply filter query - replaces applyFilterQuery() in components
       * @param {Object} filter - Filter object to apply
       * @param {String} categoryId - Category ID for FilterFlyout
       */
      applyFilter (filter, categoryId) {
        // Set the applied filter query using our extracted method
        component.appliedFilterQuery = filterCategoryHelpers.setAppliedFilterQuery(
          filter,
          component.appliedFilterQuery,
          component.filterQuery,
          component.$set || null
        )

        // Save to localStorage
        filterQueryStorage.set(component.filterQuery)

        // Trigger data refresh
        if (component.getInstitutionsByPage) {
          component.getInstitutionsByPage(1, categoryId)
        } else if (component.getItemsByPage) {
          component.getItemsByPage(1, categoryId)
        }
      },

      createFilterOptions (params) {
        const { categoryId, isInitialWithQuery } = params
        let filterOptions = component.institutionTagCategoriesCopy[categoryId]?.relationships?.tags?.data.length > 0
          ? component.institutionTagCategoriesCopy[categoryId].relationships.tags.list()
          : []

        const filterQueryFromStorage = filterQueryStorage.get()
        const selectedFilterOptionIds = Object.keys(filterQueryFromStorage).filter(id => !id.includes('_group'))

        if (Object.keys(filterOptions).length > 0) {
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

        component.setUngroupedFilterOptions({ categoryId, options: filterOptions })
        component.setIsFilterFlyoutLoading({ categoryId, isLoading: false })

        if (isInitialWithQuery) {
          component.setFilterOptionsFromFilterQuery()
        }
      },

      handleChange (filterCategoryName, isSelected) {
        // Update category selection (replaces updateCurrentlySelectedFilterCategories)
        if (isSelected) {
          component.currentlySelectedFilterCategories.push(filterCategoryName)
        } else {
          component.currentlySelectedFilterCategories = component.currentlySelectedFilterCategories.filter(category => category !== filterCategoryName)
        }

        // Save to localStorage
        filterCategoriesStorage.set(component.currentlySelectedFilterCategories)
      },

    /**
       * Reset all filters
       */
      reset () {
        // Reset filter flyouts if they exist
        Object.keys(component.allFilterCategories || {}).forEach((filterCategoryId, idx) => {
          const filterFlyoutExists = component.$refs.filterFlyout && component.$refs.filterFlyout[idx]
          const hasSelectedOption = !!Object.values(component.filterQuery || {})
            .find(el => el.condition?.memberOf === `${filterCategoryId}_group`)

          if (filterFlyoutExists) {
            component.$refs.filterFlyout[idx].reset()

            const isVisible = component.currentlySelectedFilterCategories.includes(
              component.allFilterCategories[filterCategoryId].label
            )

            if (!isVisible && hasSelectedOption) {
              const selectedOptions = Object.values(component.filterQuery)
                .filter(el => el.condition?.memberOf === `${filterCategoryId}_group`)
              const payload = selectedOptions.reduce((acc, el) => {
                acc[el.condition.value] = el
                return acc
              }, {})

              component.updateFilterQuery(payload)
            }
          }
        })

        // Reset storage
        filterQueryStorage.reset()
        component.appliedFilterQuery = {}

        // Refresh data
        if (component.getInstitutionsByPage) {
          component.getInstitutionsByPage(1)
        } else if (component.getItemsByPage) {
          component.getItemsByPage(1)
        }
      },

      setAppliedFilterQuery (filter) {
        component.appliedFilterQuery = filterCategoryHelpers.setAppliedFilterQuery(
          filter,
          component.appliedFilterQuery,
          component.filterQuery,
          component.$set || null
        )
      },

      setAppliedFilterQueryFromStorage () {
        const filterQueryFromStorage = filterQueryStorage.get()
        component.setAppliedFilterQuery(filterQueryFromStorage)
      },

      setFilterOptionsFromFilterQuery () {
        const filterQueryFromStorage = filterQueryStorage.get()
        const categoryIdsWithSelectedFilterOptions = Object.keys(filterQueryFromStorage)
          .filter(id => id.includes('_group'))
          .map(id => id.replace('_group', ''))

        categoryIdsWithSelectedFilterOptions.forEach(id => {
          const selectedFilterOptionIds = Object.values(filterQueryFromStorage)
            .filter(el => el.condition?.memberOf === `${id}_group`)
            .map(el => el.condition.value)

          component.setInitialFlyoutFilterIds({ categoryId: id, filterIds: selectedFilterOptionIds })
        })
      },

      setFilterQueryFromStorage () {
        const filterQueryFromStorage = filterQueryStorage.get()
        const filterIds = Object.keys(filterQueryFromStorage)

        if (filterIds.length > 0) {
          filterIds.forEach(id => {
            const payload = { [id]: filterQueryFromStorage[id] }

            if (filterQueryFromStorage[id].condition) {
              component.updateFilterQuery(payload)
            }
          })
        }
      },

      toggleAllCategories () {
        const allSelected = component.currentlySelectedFilterCategories.length === Object.keys(component.allFilterCategories).length
        const selectedFilterOptions = Object.values(component.appliedFilterQuery)
        const categoriesWithSelectedOptions = []

        selectedFilterOptions.forEach(option => {
          const categoryId = option.condition.memberOf.replace('_group', '')
          const category = component.allFilterCategories[categoryId]

          if (category && !categoriesWithSelectedOptions.includes(category.label)) {
            categoriesWithSelectedOptions.push(category.label)
          }
        })

        component.currentlySelectedFilterCategories = allSelected
          ? categoriesWithSelectedOptions
          : Object.values(component.allFilterCategories).map(filterCategory => filterCategory.label)
      }
    }
  }
}
