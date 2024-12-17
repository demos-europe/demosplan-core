/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { checkResponse, handleResponseMessages, hasOwnProp } from '@demos-europe/demosplan-ui'
import { initJsonApiPlugin, prepareModuleHashMap, StaticRouter } from '@efrane/vuex-json-api'
import notify from './Notify'
import Vue from 'vue'
import Vuex from 'vuex'
import { api1_0Routes, generateApi2_0Routes } from './VuexApiRoutes'

function registerPresetModules (store, presetStoreModules) {
  if (Object.keys(presetStoreModules).length > 0) {
    for (const rootModule in presetStoreModules) {
      if (hasOwnProp(presetStoreModules, rootModule)) {
        presetStoreModules[rootModule].forEach(presetModule => {
          if (!hasOwnProp(presetModule, 'defaultQuery')) {
            presetModule.defaultQuery = {}
          }

          store.createPresetModule(presetModule.name, {
            base: rootModule,
            defaultQuery: presetModule.defaultQuery
          })
        })
      }
    }
  }
  return store
}

function initStore (storeModules, apiStoreModules, presetStoreModules) {
  Vue.use(Vuex)

  const staticModules = { notify, ...storeModules }
  const VuexApiRoutes = [...generateApi2_0Routes(apiStoreModules), ...api1_0Routes]
  // This should probably be replaced with an adapter to our existing routes
  const router = new StaticRouter(VuexApiRoutes)

  let baseUrl = '/api'

  if (document.location.href.match('app_dev')) {
    baseUrl = '/app_dev.php' + baseUrl
  }

  // eslint-disable-next-line no-undef
  if (URL_PATH_PREFIX) {
    // eslint-disable-next-line no-undef
    baseUrl = URL_PATH_PREFIX + baseUrl
  }

  return router
    .updateRoutes()
    .then(router => {
      const store = new Vuex.Store({
        strict: process.env.NODE_ENV !== 'production',

        modules: prepareModuleHashMap(staticModules),
        plugins: [
          initJsonApiPlugin({
            apiModules: apiStoreModules,
            router,
            baseUrl,
            headers: {
              'X-JWT-Authorization': 'Bearer ' + dplan.jwtToken,
              'X-Demosplan-Procedure-Id': dplan.procedureId
            },
            successCallbacks: [
              (response) => {
                if (typeof response.data !== 'undefined' &&
                typeof response.data.meta !== 'undefined' &&
                typeof response.data.meta.messages !== 'undefined') {
                  handleResponseMessages(response.data.meta)
                }
                return Promise.resolve(response)
              }
            ],
            errorCallbacks: [
              (err) => {
                const response = err.response
                if (typeof response !== 'undefined' &&
                typeof response.data !== 'undefined' &&
                typeof response.data.meta !== 'undefined' &&
                typeof response.data.meta.messages !== 'undefined') {
                  handleResponseMessages(response.data.meta)
                }
                return Promise.reject(err)
              }
            ]
          }),
          store => {
            store.api.checkResponse = checkResponse
          }
        ]
      })

      if (process.env.NODE_ENV === 'development') {
        window.store = store
      }

      return store
    }).then(store => registerPresetModules(store, presetStoreModules))
}

export { initStore }
