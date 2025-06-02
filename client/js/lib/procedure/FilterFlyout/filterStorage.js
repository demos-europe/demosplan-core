/**
 * Utility functions for managing filter queries in localStorage
 * Used by FilterFlyout components across the application
 */

export const filterQueryStorage = {
  /**
   * Get filter query from localStorage
   * @returns {Object} Parsed filter query object or empty object
   */
  get () {
    const filterQueryInStorage = localStorage.getItem('filterQuery')
    return filterQueryInStorage && filterQueryInStorage !== 'undefined'
      ? JSON.parse(filterQueryInStorage) : {}
  },

  /**
   * Set filter query in localStorage
   * @param {Object} filterQuery - Filter query object to store
   */
  set (filterQuery) {
    localStorage.setItem('filterQuery', JSON.stringify(filterQuery))
  },

  /**
   * Reset filter query in localStorage
   */
  reset () {
    localStorage.setItem('filterQuery', JSON.stringify({}))
  }
}

export const filterCategoriesStorage = {
  /**
   * Get selected filter categories from localStorage
   * @returns {Array|null} Array of selected categories or null
   */
  get () {
    const selectedFilterCategories = localStorage.getItem('visibleFilterFlyouts')
    return selectedFilterCategories ? JSON.parse(selectedFilterCategories) : null
  },

  /**
   * Set selected filter categories in localStorage
   * @param {Array} selectedFilterCategories - Array of selected category names
   */
  set (selectedFilterCategories) {
    localStorage.setItem('visibleFilterFlyouts', JSON.stringify(selectedFilterCategories))
  }
}
