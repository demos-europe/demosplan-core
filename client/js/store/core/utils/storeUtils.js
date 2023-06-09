/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { dpApi } from '@demos-europe/demosplan-ui/src'
import normalize from 'json-api-normalizer'

const fetchResourcesByProcedureId = (mutationName, url, includes = []) => ({ commit }, procedureId) => {
  return dpApi({
    method: 'GET',
    responseType: 'json',
    url: Routing.generate(url, {
      procedureId: procedureId,
      includes: includes
    })
  }).then(response => {
    commit(mutationName, normalize(response.data))
  })
}

const getItemById = (key) => (state) => (id) => {
  return state[key][id]
}

const setItem = (key) => (state, value) => {
  state[key] = value
}

export { normalize, fetchResourcesByProcedureId, getItemById, setItem }
