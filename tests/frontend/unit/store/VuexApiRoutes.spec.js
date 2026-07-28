/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { api1_0Routes, generateApi2_0Routes, generateApi3_0Routes } from '@DpJs/store/core/VuexApiRoutes'
import { describe, expect, it } from '@jest/globals'
import { StaticRouter } from '@efrane/vuex-json-api'

/**
 * Mirrors the route composition in initStore(), including its order: routes are keyed
 * by module + action, so later entries replace earlier ones for the same pair.
 */
const resolveRoutes = (apiStoreModules) => new StaticRouter([
  ...generateApi2_0Routes(apiStoreModules),
  ...api1_0Routes,
  ...generateApi3_0Routes(),
]).getRoutes()

describe('VuexApiRoutes', () => {
  it('serves Place get/list from the ApiPlatform resource', () => {
    const routes = resolveRoutes(['Place'])

    expect(routes.Place.list.url).toBe('/3.0/Place')
    expect(routes.Place.get.url).toBe('/3.0/Place/{id}')
  })

  it('keeps Place create/update/delete on the EDT resource', () => {
    const routes = resolveRoutes(['Place'])

    expect(routes.Place.create.url).toBe('/2.0/Place')
    expect(routes.Place.update.url).toBe('/2.0/Place/{id}')
    expect(routes.Place.delete.url).toBe('/2.0/Place/{id}')
  })

  it('leaves unmigrated modules on the generated 2.0 routes', () => {
    const routes = resolveRoutes(['StatementSegment'])

    expect(routes.StatementSegment.list.url).toBe('/2.0/StatementSegment')
    expect(routes.StatementSegment.get.url).toBe('/2.0/StatementSegment/{id}')
  })

  it('lets hardcoded 1.0 routes override the generated 2.0 ones', () => {
    const routes = resolveRoutes(['User'])

    expect(routes.User.list.url).toBe('/1.0/user')
    expect(routes.User.get.url).toBe('/2.0/User/{id}')
  })

  it('passes the id as a route parameter for item actions only', () => {
    const routes = resolveRoutes(['Place'])

    expect(routes.Place.get.parameters).toEqual(['id'])
    expect(routes.Place.list.parameters).toEqual([])
  })
})
