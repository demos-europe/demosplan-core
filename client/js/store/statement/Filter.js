/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { del, set } from 'vue'
import { dpApi, hasOwnProp } from '@demos-europe/demosplan-ui'

const Filter = {
  namespaced: true,

  name: 'Filter',

  state: {
    appliedOptions: [],
    currentSearch: '',
    filterGroups: [
      {
        type: 'submission',
        label: 'submission'
      },
      {
        type: 'statement',
        label: 'statement'
      },
      {
        type: 'fragment',
        label: 'fragment',
        permission: ['area_statements_fragment']
      }
    ],
    filterHash: '',
    // List of empty filters
    filterList: [],
    // Available options for filters
    filterListOptions: {},
    original: false,
    procedureId: null,
    // Selected options for all filters
    selectedOptions: [],
    userFilterSets: {
      data: [],
      included: [],
      relationships: []
    }
  },

  mutations: {
    loadAppliedFilterOptions (state, options) {
      set(state, 'appliedOptions', options)
    },

    /**
     * Called by getFilterOptionsAction, loads available options of currently selected filters into filterListOptions
     * @param updatedFilters   filters to load options for
     */
    loadAvailableFilterListOptions (state, updatedFilters) {
      if (updatedFilters.length) {
        updatedFilters.forEach(filter => {
          set(state.filterListOptions, filter.id, filter.attributes.options)
        })
      } else {
        set(state, 'filterListOptions', {})
      }
    },

    /**
     * Load selected filter options into store
     * @param state
     * @param options
     */
    loadSelectedFilterOptions (state, options) {
      set(state, 'selectedOptions', options)
    },

    /**
     * Remove saved filterSet
     * Called by removeUserFilterSetAction
     * @param userFilterSetId
     * @return Boolean
     */
    removeUserFilterSet (state, userFilterSetId) {
      if (hasOwnProp(state.userFilterSets, 'data')) {
        const filterIndex = state.userFilterSets.data.findIndex(el => el.id === userFilterSetId)
        if (filterIndex >= 0) {
          state.userFilterSets.data.splice(filterIndex, 1)
          return true
        } else {
          return false
        }
      } else {
        return false
      }
    },

    resetSelectedOptions (state, optionsToKeep) {
      if (state.appliedOptions.length) {
        set(state, 'selectedOptions', optionsToKeep)
      } else {
        set(state, 'selectedOptions', [])
      }
    },

    setCurrentSearch (state, searchTerm) {
      state.currentSearch = searchTerm
    },

    /**
     * Sorts filter options either by count from large to small or by alphabet from a to z
     * Called when user opens filter select or clicks on sort option to sort filter options
     * @param {Object} data   id: id of the filter the options to be sorted belong to, sortingType: defines how to sort
     */
    sortFilterOptions (state, data) {
      const optionsToSort = state.filterListOptions[data.id]

      if (typeof optionsToSort !== 'undefined') {
        if (data.sortingType === 'count') {
          // Sort option from given ID by count-Order -> large to small
          optionsToSort.sort((a, b) => {
            // Checks if b is smaller, larger or equal a
            return (b.count > a.count) ? 1 : -1
          })
        } else {
          const customTrim = (string) => {
            return string.replace(/^\s+|\s+$/g, '')
          }

          // Sort option from given ID by alphabetic-Order -> a to z
          optionsToSort.sort((a, b) => {
            // Checks if a is smaller, larger or equal b
            return (customTrim(a.label.toString())).localeCompare(customTrim(b.label.toString()), 'de', { sensitivity: 'base' })
          })
        }

        // Currently, the empty value is not the same for all options, it can be either "keinezuordnung" or "no_value"
        const emptyOptionIndex = optionsToSort.findIndex(opt => opt.value === 'keinezuordnung' || opt.value === 'no_value')
        if (emptyOptionIndex > -1) {
          const emptyOption = optionsToSort[emptyOptionIndex]
          optionsToSort.splice(emptyOptionIndex, 1)
          optionsToSort.unshift(emptyOption)
        }
      }
    },

    /**
     * Called by getFilterListAction, updates filterList
     * @param updatedFilters
     */
    updateFilterList (state, updatedFilters) {
      state.filterList = updatedFilters
    },

    /**
     * Update original
     * @param {Boolean} original
     */
    updateOriginal (state, original) {
      state.original = original
    },

    /**
     * Updates procedure Id
     * Called by updateProcedureIdAction
     * @param procedureId
     * @return string
     */
    updateProcedureId (state, procedureId) {
      state.procedureId = procedureId
    },

    /**
     * Add or remove an option to/from selectedOptions
     * @param selectedOption
     * @param filterId
     */
    updateSelectedOptions (state, { selectedOption, filterId }) {
      // @improve send filterId with every option from BE
      if (hasOwnProp(selectedOption, 'filterId') === false) {
        selectedOption.filterId = filterId
      }
      if (state.selectedOptions.length) {
        const optionsForCurrentFilter = state.selectedOptions.filter(option => option.filterId === filterId)
        if (optionsForCurrentFilter.length) {
          const idx = state.selectedOptions.findIndex(option => option.value === selectedOption.value)
          if (idx < 0) {
            state.selectedOptions.splice(state.selectedOptions.length, 0, selectedOption)
          } else {
            del(state.selectedOptions, idx)
          }
        } else {
          state.selectedOptions.splice(state.selectedOptions.length, 0, selectedOption)
        }
      } else {
        state.selectedOptions.splice(state.selectedOptions.length, 0, selectedOption)
      }
    },

    /**
     * Update count for selected options with data from BE (e.g. after updating selected filter options in FE)
     */
    updateSelectedOptionsCount (state, updatedFilters) {
      if (updatedFilters.length) {
        updatedFilters.forEach(filter => {
          // Check if any of the selectedOptions needs to be updated
          state.selectedOptions.forEach(option => {
            if (option.filterId === filter.id) {
              const idx = state.selectedOptions.findIndex(opt => opt.filterId === filter.id && opt.value === option.value)
              if (idx >= 0) {
                const newCount = filter.attributes.options.find(opt => opt.value === option.value).count
                set(state.selectedOptions[idx], 'count', newCount)
              }
            }
          })
        })
      }
    },

    /**
     * Update UserFilterSets
     * Called by getUserFilterSetsAction
     * @param userFilterSets
     */
    updateUserFilterSets (state, userFilterSets) {
      state.userFilterSets = userFilterSets
    }
  },

  actions: {
    /**
     * Get the list of filters to be displayed in the filterModal (without options - as they will be lazyloaded
     * into filterListOptions when needed)
     */
    getFilterListAction ({ commit, state }) {
      performance.mark('start')

      const route = state.original ? 'dp_api_procedure_get_original_statement_empty_filters' : 'dp_api_procedure_get_statement_empty_filters'

      return dpApi({
        method: 'GET',
        url: Routing.generate(route, { procedureId: state.procedureId })
      })
        .then(this.api.checkResponse)
        .then(data => {
          commit('updateFilterList', data.data)
        })
        .catch(data => {
          console.log('Something happened', data)
        })
        .then(() => {
          performance.mark('end')
          performance.measure('gettingFilterList', 'start', 'end')
          console.log('get filter list', performance.getEntriesByName('gettingFilterList', 'measure')[0].duration, 'ms')
        })
    },

    /**
     * Get available filter options for given filterId or all selected filter options based on a filterHash
     * @improve the request always returns the updated options for all selected filters, but in some cases we only want
     * the updated options for one filter
     * @param data    contains filterHash {String} and filterId {String}; if filterId is omitted, action will return
     * unfiltered response, i.e. options for all selected filters
     */
    getFilterOptionsAction ({ commit, state }, data) {
      performance.mark('start')
      const route = state.original ? 'dp_api_procedure_get_original_filters' : 'dp_api_procedure_get_statement_filters'

      return dpApi({
        method: 'GET',
        url: Routing.generate(route, { procedureId: state.procedureId, filterHash: data.filterHash })
      })
        .then(this.api.checkResponse)
        .then(response => {
          let filtersToUpdateInStore
          // Update only options for one filter
          if (data.filterId) {
            filtersToUpdateInStore = response.data.filter(filter => filter.id === data.filterId)
          } else {
            // Update options for all selected filters
            filtersToUpdateInStore = response.data
          }
          commit('loadAvailableFilterListOptions', filtersToUpdateInStore)
          commit('updateSelectedOptionsCount', filtersToUpdateInStore)
        })
        .catch(response => {
          console.log('Something happened', response)
        })
        .then(() => {
          performance.mark('end')
          performance.measure('loadingFilterOptionsForCurrentFilter', 'start', 'end')
          console.log('load filter options for current filter', performance.getEntriesByName('loadingFilterOptionsForCurrentFilter', 'measure')[0].duration, 'ms')
        })
    },

    /**
     * Get UserFilterSets for current procedure
     */
    getUserFilterSetsAction ({ commit }) {
      const url = Routing.generate('api_resource_list', { resourceType: 'UserFilterSet' })
      const params = {
        include: 'filterSet',
        fields: {
          UserFilterSet: ['filterSet', 'name'].join(),
          FilterSet: ['hash', 'name'].join()
        }
      }
      return dpApi.get(url, params)
        .then(this.api.checkResponse)
        .then(data => commit('updateUserFilterSets', data))
        .catch((err) => {
          console.error('filter.saveFilterSet.load.error', err)
        })
    },

    /**
     *
     * @param {String} userFilterSetId
     * @return {Promise<dpApiResponse<any>>}
     */
    removeUserFilterSetAction ({ commit, state }, userFilterSetId) {
      return dpApi({
        method: 'DELETE',
        url: Routing.generate('dplan_api_procedure_delete_statement_filter', {
          procedureId: state.procedureId,
          filterSetId: userFilterSetId
        })
      })
        .then(this.api.checkResponse)
        .then(() => {
          commit('removeUserFilterSet', userFilterSetId)
        })
        .catch(data => {
          console.log('Something happened', data)
        })
    },

    /**
     * Update procedureId and original
     * Called when FilterModal is mounted
     * @param {String} procedureId
     * @param {Boolean} original
     */
    updateBaseState ({ commit }, { procedureId, original }) {
      commit('updateProcedureId', procedureId)
      commit('updateOriginal', original)
    }
  },

  getters: {
    // Get all selected filter options with corresponding filterName, needed for updateFilterHash
    allSelectedFilterOptionsWithFilterName: state => {
      const optionsForFilterHash = []
      if (state.selectedOptions.length) {
        state.selectedOptions.forEach(option => {
          state.filterList.forEach(filterItem => {
            if (option.filterId === filterItem.id) {
              optionsForFilterHash.push({
                name: filterItem.attributes.name + '[]',
                value: option.value
              })
            }
          })
        })
      }
      return optionsForFilterHash
    },

    /**
     * Get filter options of one type that have a count !== 0
     * @param type
     */
    filterByType: state => type => {
      const filter = JSON.parse(JSON.stringify(state.filterList.filter(filter => filter.attributes.type === type)))
      let i = 0
      if (typeof filter !== 'undefined') {
        for (; i < filter.length; i++) {
          filter[i].attributes.options = filter[i].attributes.options.filter(option => {
            return option.count !== 0
          })
        }
      }
      return filter
    },

    /**
     * Get filter groups shown as tabs (Einreichung, Stellungnahme, Planungsdokument and so on)
     */
    filterGroups: state => state.filterGroups,

    /**
     * Get all filters (Institution, Abteilung, Potenzialflächen, Kreise and so on), without options
     * @return array
     */
    filterList: state => state.filterList,

    /**
     * Get all filterOptions for one filter, without options that have no results (i.e., count === 0)
     * @return {Array}
     */
    filterOptionsByFilter: state => filterId => {
      if (Object.keys(state.filterListOptions).length) {
        const allOptions = state.filterListOptions[filterId]
        if (typeof allOptions !== 'undefined') {
          return Object.values(allOptions).filter(option => option.count !== 0)
        }
      }
      return []
    },

    /**
     * Get procedure id
     * @return string
     */
    procedureId: state => state.procedureId,

    /**
     * Get all selected filter options
     */
    selectedFilterOptions: state => state.selectedOptions,

    /**
     * Get selected options for one filter
     * @param {String} filterId
     */
    selectedFilterOptionsByFilter: state => filterId => {
      if (state.selectedOptions.length) {
        const selectedFilterOptions = state.selectedOptions.filter(option => option.filterId === filterId)
        return selectedFilterOptions.length ? selectedFilterOptions : []
      }
      return []
    },

    /**
     * Get filterHash for filter combination
     * @param {Object} userFilterSet
     * @return string
     */
    userFilterSetFilterHash: state => userFilterSet => {
      if (hasOwnProp(userFilterSet, 'relationships') && hasOwnProp(userFilterSet.relationships, 'filterSet')) {
        const filter = state.userFilterSets.included.filter(filterSet => filterSet.id === userFilterSet.relationships.filterSet.data.id)
        return filter[0].attributes.hash
      } else {
        return ''
      }
    },

    /**
     * Get filter combinations saved by user, a.k.a. filterSets
     * @return array
     */
    userFilterSets: state => state.userFilterSets.data.filter(el => hasOwnProp(el, 'attributes'))
  }
}

export default Filter
