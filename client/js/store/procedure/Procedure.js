/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { nextTick, set } from 'vue'
import { dpApi } from '@demos-europe/demosplan-ui'

const ProcedureStore = {
  namespaced: true,

  name: 'Procedure',

  state: {
    currentProcedureId: '',
    currentView: 'DpList',
    isDrawerOpened: true,
    isLoading: true,
    procedures: [],
    shouldMapZoomBeSet: false
  },

  mutations: {
    reset (state) {
      if (state.procedures.length) {
        state.procedures = []
      }
    },

    setProcedures (state, data) {
      state.procedures = data
    },

    setProperty (state, data) {
      set(state, data.prop, data.val)
    }
  },

  actions: {
    get ({ commit }, args = { search: '' }) {
      commit('setProperty', { prop: 'isLoading', val: true })
      commit('setProperty', { prop: 'shouldMapZoomBeSet', val: false })

      // Prepare params
      const urlParams = {
        search: args.search
      }

      const pathArray = window.location.pathname.split('/')
      const lastParam = pathArray[pathArray.length - 1]
      if (pathArray[pathArray.length - 2] === 'plaene') {
        urlParams.orgaSlug = lastParam
      }

      return dpApi({
        method: 'GET',
        url: Routing.generate('DemosPlan_procedure_search_ajax', urlParams)
      }).then(response => {
        commit('reset')
        nextTick(() => {
          const procedures = response.data.data.map(el => {
            return {
              ...el.attributes,
              id: el.id
            }
          })
          commit('setProcedures', procedures)
        })
        commit('setProperty', { prop: 'isLoading', val: false })
        commit('setProperty', { prop: 'currentView', val: 'DpList' })
        commit('setProperty', { prop: 'shouldMapZoomBeSet', val: true })

        return response.data.data
      })
    },

    showDetailView ({ state, commit }, procedureId) {
      commit('setProperty', { prop: 'currentProcedureId', val: procedureId })

      commit('setProperty', { prop: 'currentView', val: 'DpDetailView' })
      commit('setProperty', { prop: 'isDrawerOpened', val: true })
    }
  },

  getters: {
    currentProcedureId: state => state.currentProcedureId,
    currentView: state => state.currentView,
    isDrawerOpened: state => state.isDrawerOpened,
    isLoading: state => state.isLoading,
    shouldMapZoomBeSet: state => state.shouldMapZoomBeSet
  }
}

export default ProcedureStore
