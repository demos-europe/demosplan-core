/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { normalize, setItem } from '@DpJs/store/core/utils/storeUtils'
import { dpApi } from '@demos-europe/demosplan-ui'

const BoilerplatesStore = {
  namespaced: true,

  name: 'Boilerplates',

  state: {
    boilerplates: {},
    groups: [],
    getBoilerplatesRequestFired: false
  },

  mutations: {
    setBoilerplates: setItem('boilerplates'),
    setGroups: setItem('groups'),
    getBoilerplatesFired: setItem('getBoilerplatesRequestFired')
  },

  actions: {
    getBoilerPlates: ({ commit }, procedureId) => {
      commit('getBoilerplatesFired', true)
      return dpApi({
        method: 'GET',
        url: Routing.generate('api_resource_list', {
          resourceType: 'Boilerplate',
          includes: ['groups']
        })
      }).then(response => {
        const normalized = normalize(response.data)
        if (normalized.boilerplate) {
          commit('setBoilerplates', normalized.boilerplate)
        }
        if (normalized.boilerplateGroup) {
          commit('setGroups', Object.values(normalized.boilerplateGroup))
        }
        return Promise.resolve(true)
      }).catch(e => Promise.reject(e))
    }
  },

  getters: {
    getGroupedBoilerplates: (state) => {
      if (Object.keys(state.boilerplates).length === 0) {
        return []
      }

      const bpCpy = { ...state.boilerplates }
      const grouped = state.groups.map(group => {
        const boilerplates = Object.values(bpCpy).filter(bp => {
          if (group.id === bp?.relationships?.group?.data?.id) {
            delete bpCpy[bp.id]
            return true
          }
          return false
        })
        return {
          id: group.id,
          groupName: group.attributes.title,
          boilerplates: boilerplates.map(bp => {
            return {
              id: bp.id,
              text: bp.attributes.text,
              title: bp.attributes.title,
              category: bp.attributes.categoriesTitle
            }
          })
        }
      })
      const noGroup = {
        id: 'withoutGroupID',
        groupName: Translator.trans('no.group'),
        boilerplates: Object.values(bpCpy).map(bp => {
          return {
            id: bp.id,
            text: bp.attributes.text,
            title: bp.attributes.title,
            category: bp.attributes.categoriesTitle
          }
        })
      }

      return [...grouped, noGroup].sort((a, b) => (a.groupName > b.groupName) ? 1 : ((b.groupName > a.groupName) ? -1 : 0))
    }

  }
}

export default BoilerplatesStore
