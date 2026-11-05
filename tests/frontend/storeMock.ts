/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { vi } from 'vitest'

interface CollectionState {
  loading: boolean
  items: Record<string, unknown>
  initial: Record<string, unknown>
  currentPage: number | null
  totalPages: number | null
  urlInfo: Record<string, unknown>
  options: Record<string, unknown>
}

/**
 * Creates a mock Vuex store configuration mirroring the vuex-json-api module structure.
 * Provides all standard CRUD state, getters, mutations, and actions for collection modules.
 *
 * @param moduleName - The name of the Vuex module
 * @returns A Vuex store configuration object
 *
 * @example
 * ```typescript
 * import { createMockStore } from '../storeMock'
 *
 * const mockStore = createStore(createMockStore('ScheduledExport'))
 * ```
 */
export function createMockStore(moduleName: string) {
  return {
    modules: {
      [moduleName]: {
        namespaced: true,
        state: {
          loading: false,
          items: {},
          initial: {},
          currentPage: null,
          totalPages: null,
          urlInfo: {},
          options: {},
        },
        getters: {
          getProperty: (state: CollectionState) => (id: string, property: string) => state.items[id]?.[property],
          hasChanges: (state: CollectionState) => JSON.stringify(state.items) !== JSON.stringify(state.initial),
          itemsInRelationshipFormat: (state: CollectionState) => Object.keys(state.items).map(id => ({ id, type: moduleName })),
        },
        mutations: {
          resetItems: vi.fn(),
          set: vi.fn(),
          setItem: vi.fn(),
          startLoading: vi.fn(),
          endLoading: vi.fn(),
          update: vi.fn(),
          remove: vi.fn(),
          setPagination: vi.fn(),
        },
        actions: {
          get: vi.fn(() => Promise.resolve({ data: {} })),
          resetItems: vi.fn(),
          restoreFromInitial: vi.fn(),
          list: vi.fn(() => Promise.resolve({ data: [] })),
          set: vi.fn(),
          save: vi.fn(() => Promise.resolve()),
          create: vi.fn(() => Promise.resolve()),
          delete: vi.fn(() => Promise.resolve()),
        },
      },
    },
  }
}
