
const FilterFlyoutStore = {
  namespaced: true,

  name: 'FilterFlyout',

  state: {
    /**
     * Array of objects (groups) - id, label, options.
     * Options is an array of objects - id, label, count, description, selected
     */
    groupedOptions: [],
    /**
     * Array of objects - id, label, count, description
     */
    ungroupedOptions: []
  },

  mutations: {
    setGroupedOptions (state, items) {
      state.groupedOptions = items
    },
    setUngroupedOptions (state, items) {
      state.ungroupedOptions = items
    }
  },
}

export default FilterFlyoutStore
