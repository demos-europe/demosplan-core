/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { dpApi } from '@demos-europe/demosplan-ui'
import normalize from 'json-api-normalizer'
import { set } from 'vue'

const fetchResourcesByProcedureId = (mutationName, url, includes = []) => ({ commit }, procedureId) => {
  return dpApi({
    method: 'GET',
    url: Routing.generate(url, {
      procedureId,
      includes
    })
  }).then(response => {
    commit(mutationName, normalize(response.data))
  })
}

const getItemById = (key) => (state) => (id) => {
  return state[key][id]
}

const setItem = (key) => (state, value) => {
  set(state, key, value)
}

export { normalize, fetchResourcesByProcedureId, getItemById, setItem }
