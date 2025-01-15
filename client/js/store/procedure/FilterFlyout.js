
const FilterFlyoutStore = {
  namespaced: true,

  name: 'FilterFlyout',

  state: {
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
     *
     * @param state
     * @param payload {Object}
     * @param payload.categoryId {String} id of the category used as label for the filter flyout
     * @param payload.groupedOptions {Object} grouped filter options { Array of objects (groups) - id, label, options;
     * options is an array of objects - id, label, count, description, selected}
     */
    setGroupedOptions (state, payload) {
      const { categoryId, groupedOptions } = payload

      Vue.set(state.groupedOptions, categoryId, groupedOptions)
    },

    setGroupedOptionSelected(state, { categoryId, groupId, optionId, value }) {
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
     *
     * @param state
     * @param payload {Object}
     * @param payload.categoryId {String} id of the category used as label for the filter flyout
     * @param payload.isLoading {Boolean}
     */
    setIsLoading (state, payload) {
      const { categoryId, isLoading } = payload

      Vue.set(state.isLoading, categoryId, isLoading)
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

      Vue.set(state.ungroupedOptions, categoryId, options)
    },

    setUngroupedOptionSelected(state, { categoryId, optionId, value }) {
      const options = state.ungroupedOptions[categoryId]
      if (options) {
        const option = options.find(item => item.id === optionId)
        if (option) {
          option.selected = value
        }
      }
    }
  },

  getters: {
    getGroupedOptionsByCategoryId: (state) => (categoryId) => {
      return state.groupedOptions[categoryId]
    },

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
