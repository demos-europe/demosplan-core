/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { api1_0Routes, generateApi2_0Routes } from './VuexApiRoutes'
import { handleResponseMessages, hasOwnProp } from '@demos-europe/demosplan-ui'
import { initJsonApiPlugin, prepareModuleHashMap, Route, StaticRoute, StaticRouter } from '@efrane/vuex-json-api'
import { createStore } from 'vuex'
import notify from './Notify'

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

const handleResponse = async payload => {
  // If the response body is empty, contentType will be null
  const contentType = payload.headers.get('Content-Type')

  if (contentType && contentType.includes('json')) {
    const response = await payload.json()

    const meta = response.data?.meta
      ? response.data.meta
      : response.meta || null
    if (meta?.messages) {
      handleResponseMessages(meta)
    }

    return Promise.resolve(response)
  }

  return Promise.resolve(payload)
}

function initStore (storeModules, apiStoreModules, presetStoreModules) {
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
      const store = createStore({
        strict: process.env.NODE_ENV !== 'production',
        devtools: process.env.NODE_ENV !== 'production',
        modules: prepareModuleHashMap(staticModules),
        plugins: [
          initJsonApiPlugin({
            apiModules: apiStoreModules,
            router,
            baseUrl,
            headers: {
              'X-JWT-Authorization': 'Bearer ' + dplan.jwtToken,
              'X-Demosplan-Procedure-Id': dplan.procedureId,
              'X-CSRF-Token': dplan.csrfToken
            },
            successCallbacks: [
              handleResponse
            ],
            errorCallbacks: [
              handleResponse
            ]
          }),
          store => {
            store.api.newStaticRoute = (route) => {
              return new StaticRoute(route)
            }
            store.api.newRoute = (route) => {
              return new Route(route)
            }
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
