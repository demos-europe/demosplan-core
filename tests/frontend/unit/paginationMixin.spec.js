/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import paginationMixin from '@DpJs/components/shared/mixins/paginationMixin'

describe('paginationMixin', () => {
  let context
  let localStorageMock

  const createContext = () => ({
    defaultPagination: {
      currentPage: 1,
      perPage: 25,
      limits: [10, 25, 50, 100],
    },
    storageKeyPagination: 'test-pagination',
    pagination: null,
    ...paginationMixin.methods,
  })

  const mockStoredPagination = (pagination) => {
    localStorageMock.getItem.mockReturnValue(
      pagination ? JSON.stringify(pagination) : null,
    )
  }

  beforeEach(() => {
    localStorageMock = {
      getItem: vi.fn(),
      setItem: vi.fn(),
      removeItem: vi.fn(),
      clear: vi.fn(),
    }

    Object.defineProperty(window, 'localStorage', {
      value: localStorageMock,
      writable: true,
    })

    context = createContext()
  })

  afterEach(() => {
    vi.clearAllMocks()
  })

  describe('normalizePagination', () => {
    it.each([
      [
        'EDT 2.0',
        {
          count: 15,
          current_page: 2,
          per_page: 25,
          total: 150,
          total_pages: 6,
        },
        {
          count: 15,
          currentPage: 2,
          perPage: 25,
          total: 150,
          totalPages: 6,
        },
      ],
      [
        'API Platform 3.0',
        {
          totalItems: 150,
          currentPage: 2,
          itemsPerPage: 25,
        },
        {
          count: 150,
          currentPage: 2,
          perPage: 25,
          total: 150,
          totalPages: null,
        },
      ],
    ])('normalizes %s pagination format', (_, data, expected) => {
      expect(context.normalizePagination(data)).toEqual(expected)
    })

    it('returns default values for empty pagination data', () => {
      expect(context.normalizePagination({})).toEqual({
        count: 0,
        currentPage: 1, // From defaultPagination.currentPage
        perPage: 25, // From defaultPagination.perPage
        total: 0,
        totalPages: null,
      })
    })

    it('falls back to hardcoded defaults when defaultPagination is not provided', () => {
      const contextWithoutDefaults = {
        defaultPagination: undefined,
        ...paginationMixin.methods,
      }

      expect(contextWithoutDefaults.normalizePagination({})).toEqual({
        count: 0,
        currentPage: 1,
        perPage: 10,
        total: 0,
        totalPages: null,
      })
    })
  })

  describe('initPagination', () => {
    it('initializes pagination with default values when localStorage is empty', () => {
      mockStoredPagination(null)

      context.initPagination()

      expect(context.pagination).toEqual({
        currentPage: 1,
        perPage: 25,
      })

      expect(localStorageMock.getItem).toHaveBeenCalledWith('test-pagination')
    })

    it('initializes pagination with values from localStorage', () => {
      mockStoredPagination({
        currentPage: 3,
        perPage: 50,
      })

      context.initPagination()

      expect(context.pagination).toEqual({
        currentPage: 3,
        perPage: 50,
      })
    })

    it('falls back to hardcoded defaults when defaultPagination is not provided', () => {
      const contextWithoutDefaults = {
        defaultPagination: undefined,
        storageKeyPagination: 'test-pagination',
        pagination: null,
        ...paginationMixin.methods,
      }

      mockStoredPagination(null)
      contextWithoutDefaults.initPagination()

      expect(contextWithoutDefaults.pagination).toEqual({
        currentPage: 1,
        perPage: 10,
      })
    })
  })

  describe('updatePagination', () => {
    it('falls back to API values when localStorage is empty', () => {
      mockStoredPagination(null)

      const paginationData = {
        current_page: 3,
        per_page: 50,
        count: 20,
        total: 150,
        total_pages: 3,
      }

      context.updatePagination(paginationData)

      expect(context.pagination).toEqual({
        count: 20,
        currentPage: 3,
        limits: [10, 25, 50, 100],
        perPage: 50,
        total: 150,
        totalPages: 3,
      })
    })

    it('uses localStorage values when available', () => {
      mockStoredPagination({
        currentPage: 5,
        perPage: 100,
      })

      const paginationData = {
        current_page: 3,
        per_page: 50,
        count: 20,
        total: 150,
      }

      context.updatePagination(paginationData)

      expect(context.pagination).toEqual({
        count: 20,
        currentPage: 5,
        limits: [10, 25, 50, 100],
        perPage: 100,
        total: 150,
        totalPages: null,
      })
    })
  })

  describe('pagination workflow', () => {
    it('initializes, stores and updates pagination', () => {
      mockStoredPagination(null)

      context.initPagination()

      expect(context.pagination).toEqual({
        currentPage: 1,
        perPage: 25,
      })

      const paginationData = {
        current_page: 2,
        per_page: 50,
        count: 20,
        total: 150,
      }

      context.setLocalStorage(paginationData)

      expect(localStorageMock.setItem).toHaveBeenCalledWith(
        'test-pagination',
        JSON.stringify({
          currentPage: 2,
          perPage: 50,
        }),
      )

      mockStoredPagination({
        currentPage: 2,
        perPage: 50,
      })

      context.updatePagination(paginationData)

      expect(context.pagination).toEqual({
        count: 20,
        currentPage: 2,
        limits: [10, 25, 50, 100],
        perPage: 50,
        total: 150,
        totalPages: null,
      })
    })
  })
})
