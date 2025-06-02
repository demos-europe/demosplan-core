/**
 * Helper functions for filter category operations
 * Used by FilterFlyout components for common category logic
 */

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
  }
}
