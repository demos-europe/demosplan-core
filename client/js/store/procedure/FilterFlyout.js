import { del, set } from 'vue'

const FilterFlyoutStore = {
  namespaced: true,

  name: 'FilterFlyout',

  state: {
    filterQuery: {},
    /**
     * Object to store the initial flyout filter IDs.
     * Keys are category IDs and values are arrays of filter IDs.
     */
    initialFlyoutFilterIds: {},
    /**
     * @type {boolean}
     * @description Indicates whether the filter flyout is expanded or not.
     * This state is used to control the visibility of the filter flyout.
     */
    isExpanded: {},
    /**
     * Grouped filter options, stored in groupedOptions by categoryId of the filter flyout:
     * Object with categoryIds as keys and grouped options as values.
     * Each object has  id, label, and options.
     * Options is an array of objects - id, label, count, description, selected
     */
    groupedOptions: {},
    /**
     * Object with categoryIds as keys and booleans as values
     * Used to show/hide loading spinner in each filter flyout
     */
    isLoading: {},
    /**
     * Ungrouped filter options, stored in ungroupedOptions by categoryId of the filter flyout:
     * Object with categoryIds as keys and array of ungrouped options as values
     * Each option has id, label, count, description
     */
    ungroupedOptions: {}
  },

  mutations: {
    /**
     * Sets the initial flyout filter IDs for a given category.
     *
     * @param {Object} state - The state object.
     * @param {Object} payload - The payload object.
     * @param {String} payload.categoryId - The ID of the category.
     * @param {Array} payload.filterIds - The array of filter IDs to set.
     */
    setInitialFlyoutFilterIds (state, payload) {
      const { categoryId, filterIds } = payload

      set(state.initialFlyoutFilterIds, categoryId, filterIds)
    },

    /**
     *
     * @param state
     * @param payload {Object}
     * @param payload.categoryId {String} id of the category used as label for the filter flyout
     * @param payload.groupedOptions {Object} grouped filter options { Array of objects (groups) - id, label, options;
     * options is an array of objects - id, label, count, description, selected}
     */
    setGroupedOptions (state, payload) {
      const { categoryId, groupedOptions } = payload

      set(state.groupedOptions, categoryId, groupedOptions)
    },

    /**
     * Sets the selected state of a grouped option for a given category and group.
     *
     * @param {Object} state - The state object.
     * @param {Object} payload - The payload object.
     * @param {String} payload.categoryId - The ID of the category.
     * @param {String} payload.groupId - The ID of the group.
     * @param {String} payload.optionId - The ID of the option.
     * @param {Boolean} payload.value - The selected state to set.
     */
    setGroupedOptionSelected (state, { categoryId, groupId, optionId, value }) {
      const groups = state.groupedOptions[categoryId]

      if (groups) {
        const group = groups.find(group => group.id === groupId)

        if (group) {
          const option = group.options.find(item => item.id === optionId)

          if (option) {
            option.selected = value
          }
        }
      }
    },

    /**
     * Sets the expanded state for a given category.
     *
     * @param {Object} state - The state object.
     * @param {Object} payload - The payload object.
     * @param {String} payload.categoryId - The ID of the category.
     * @param {Boolean} payload.isExpanded - The expanded state to set.
     */
    setIsExpanded (state, payload) {
      const { categoryId, isExpanded } = payload

      set(state.isExpanded, categoryId, isExpanded)
    },

    /**
     *
     * @param state
     * @param payload {Object}
     * @param payload.categoryId {String} id of the category used as label for the filter flyout
     * @param payload.isLoading {Boolean}
     */
    setIsLoading (state, payload) {
      const { categoryId, isLoading } = payload

      set(state.isLoading, categoryId, isLoading)
    },

    /**
     *
     * @param state
     * @param payload {Object}
     * @param payload.categoryId {String} id of the category used as label for the filter flyout
     * @param payload.options {Array} ungrouped filter options
     */
    setUngroupedOptions (state, payload) {
      const { categoryId, options } = payload

      set(state.ungroupedOptions, categoryId, options)
    },

    /**
     * Sets the selected state of an ungrouped option for a given category.
     *
     * @param {Object} state - The state object.
     * @param {Object} payload - The payload object.
     * @param {String} payload.categoryId - The ID of the category.
     * @param {String} payload.optionId - The ID of the option.
     * @param {Boolean} payload.value - The selected state to set.
     */
    setUngroupedOptionSelected (state, { categoryId, optionId, value }) {
      const options = state.ungroupedOptions[categoryId]

      if (options) {
        const option = options.find(item => item.id === optionId)

        if (option) {
          option.selected = value
        }
      }
    },

    /**
     * Adds or removes filters from the filterQuery
     * @param filter {Object} with the following structure:
     * {
     *   id: {
     *     condition: {
     *       operator: <String>,
     *       path: <String>,
     *       value: <String>
     *     }
     *   }
     * }
     */
    updateFilterQuery (state, filter) {
      if (Object.keys(filter).length) {
        const filterQuery = Object.values(filter)[0]
        const value = !filterQuery.condition.value ? 'unassigned' : filterQuery.condition.value
        const queryIdx = Object.values(state.filterQuery).findIndex(el => {
          if (value === 'unassigned') {
            return el.condition.value === undefined
          }

          return el.condition.value === value
        })

        if (queryIdx < 0) {
          set(state.filterQuery, [value], filterQuery)
        } else {
          del(state.filterQuery, [value])
        }
      } else {
        set(state, 'filterQuery', filter)
      }
    }
  },

  getters: {
    getFilterQuery: (state) => state.filterQuery,

    /**
     * Retrieves the initial flyout filter IDs for a given category.
     *
     * @param {Object} state - The state object.
     * @param {String} categoryId - The ID of the category.
     * @return {Array} The initial flyout filter IDs for the specified category.
     */
    getInitialFlyoutFilterIdsByCategoryId: (state) => (categoryId) => {
      return state.initialFlyoutFilterIds[categoryId]
    },

    /**
     * Retrieves the grouped filter options for a given category.
     *
     * @param {Object} state - The state object.
     * @param {String} categoryId - The ID of the category.
     * @return {Object} The grouped filter options for the specified category.
     */
    getGroupedOptionsByCategoryId: (state) => (categoryId) => {
      return state.groupedOptions[categoryId]
    },

    /**
     * Retrieves the expanded state for a given category.
     *
     * @param {Object} state - The state object.
     * @param {String} categoryId - The ID of the category.
     * @return {Boolean} The expanded state for the specified category.
     */
    getIsExpandedByCategoryId: (state) => (categoryId) => {
      return state.isExpanded[categoryId]
    },

    /**
     * Retrieves the loading state for a given category.
     *
     * @param {Object} state - The state object.
     * @param {String} categoryId - The ID of the category.
     * @return {Boolean} The loading state for the specified category.
     */
    getIsLoadingByCategoryId: (state) => (categoryId) => {
      return state.isLoading[categoryId]
    },

    /**
     *
     * @param state
     * @param categoryId {String}
     * @return {Object}
     */
    getUngroupedOptionsByCategoryId: (state) => (categoryId) => {
      return state.ungroupedOptions[categoryId]
    }
  }
}

export default FilterFlyoutStore
