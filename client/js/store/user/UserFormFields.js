/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { dpApi } from '@demos-europe/demosplan-ui'
import qs from 'qs'

const UserFormFields = {
  namespaced: true,

  name: 'UserFormFields',

  state: {
    orgaSuggestions: []
  },

  mutations: {
    setOrgaSuggestions (state, organisations) {
      state.orgaSuggestions = organisations
    }
  },

  actions: {
    fetchOrgaSuggestions ({ commit }) {
      if (hasPermission('area_organisations') || hasPermission('feature_organisation_user_list')) {
        const url = Routing.generate('dplan_api_organisation_list') + '?' + qs.stringify({ page: { number: 1, size: 500 } }) + '&' + qs.stringify({ include: 'departments' })
        dpApi.get(url).then((response) => {
          const organisations = []
          const inclDepartments = {}

          response.data.included.forEach(dep => {
            if (dep.type === 'Department') inclDepartments[dep.id] = { id: dep.id, type: dep.type, ...dep.attributes }
          })

          response.data.data.forEach(org => {
            const newOrg = { ...org.attributes, id: org.id, type: org.type }

            if (org.relationships && org.relationships.departments) {
              const departments = org.relationships.departments.data
              const orgaDepartments = {}
              departments.forEach(dep => {
                orgaDepartments[dep.id] = inclDepartments[dep.id]
              })
              newOrg.departments = orgaDepartments
            }
            organisations.push(newOrg)
          })

          commit('setOrgaSuggestions', organisations)
        })
      }
    }
  },

  getters: {
    getOrgaSuggestions: state => state.orgaSuggestions
  }
}

export default UserFormFields
