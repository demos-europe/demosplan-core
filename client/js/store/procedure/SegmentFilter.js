/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const SegmentFilterStore = {
  namespaced: true,
  name: 'segmentfilter',
  state: {
    filterQuery: {}
  },

  mutations: {
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
        const queryIdx = Object.values(state.filterQuery).findIndex(el => el.condition.value === filterQuery.condition.value)
        if (queryIdx < 0) {
          Vue.set(state.filterQuery, [filterQuery.condition.value], filterQuery)
        } else {
          Vue.delete(state.filterQuery, [filterQuery.condition.value])
        }
      } else {
        Vue.set(state, 'filterQuery', filter)
      }
    }
  },

  getters: {
    filterQuery: (state) => state.filterQuery
  }
}

export default SegmentFilterStore
