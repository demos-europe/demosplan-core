/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { dpApi } from '@demos-europe/demosplan-ui'

const LocationStore = {

  namespaced: true,
  name: 'location',

  state: {
    locations: []
  },

  mutations: {
    set (state, data) {
      state.locations = data
    }
  },

  actions: {
    get ({ commit }, args = { query: '' }) {
      return dpApi({
        method: 'get',
        url: Routing.generate('DemosPlan_procedure_public_suggest_procedure_location_json', {
          query: args.query,
          maxResults: 12
        }),
        responseType: 'json'
      })
        .then(response => {
          const locations = []
          const suggestions = response.data.data.suggestions
          let i = 0; const l = suggestions.length

          for (; i < l; i++) {
            locations.push(suggestions[i].value)
          }
          commit('set', locations)
        })
    }
  }
}

export default LocationStore
