/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { api1_0Routes, apiUrl, generateApi2_0Routes, generateApi3_0Routes } from '@DpJs/store/core/VuexApiRoutes'
import { describe, expect, it, jest } from '@jest/globals'
import { StaticRouter } from '@efrane/vuex-json-api'

// Mock Routing (following the pattern from VueConfigLocal.js)
global.Routing = {
  getBaseUrl: jest.fn(() => ''),
}

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

describe('apiUrl', () => {
  it('resolves direct dpApi calls to the same version as the store', () => {
    expect(apiUrl('Place')).toBe('/api/3.0/Place')
    expect(apiUrl('Place', 'get', 'some-uuid')).toBe('/api/3.0/Place/some-uuid')
    expect(apiUrl('Place', 'create')).toBe('/api/2.0/Place')
    expect(apiUrl('Place', 'update', 'some-uuid')).toBe('/api/2.0/Place/some-uuid')
  })

  it('falls back to 2.0 for unmigrated modules', () => {
    expect(apiUrl('StatementSegment')).toBe('/api/2.0/StatementSegment')
  })

  it('applies hardcoded 1.0 overrides', () => {
    expect(apiUrl('User', 'list')).toBe('/api/1.0/user')
  })

  it('prepends the base url', () => {
    global.Routing.getBaseUrl.mockReturnValueOnce('/app_dev.php')

    expect(apiUrl('Place')).toBe('/app_dev.php/api/3.0/Place')
  })

  it('throws when an item action is called without a usable id', () => {
    expect(() => apiUrl('Place', 'update')).toThrow('requires an id')
    expect(() => apiUrl('Place', 'update', undefined)).toThrow('requires an id')
    expect(() => apiUrl('Place', 'update', null)).toThrow('requires an id')
    expect(() => apiUrl('Place', 'update', '')).toThrow('requires an id')
  })

  it('encodes the id', () => {
    expect(apiUrl('Place', 'get', 'a b&c')).toBe('/api/3.0/Place/a%20b%26c')
  })
})
