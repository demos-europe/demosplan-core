/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

export default {
  methods: {
    /**
     * Normalize pagination data from EDT 2.0 and API Platform 3.0 responses.
     *
     * @param {object} data - Pagination data from the API response.
     * @returns {object} Normalized pagination data.
     */
    normalizePagination (data) {
      const defaultCurrentPage = this.defaultPagination?.currentPage ?? 1
      const defaultPerPage = this.defaultPagination?.perPage ?? 10

      return {
        count: data.count ?? data.totalItems ?? 0,
        currentPage: data.current_page ?? data.currentPage ?? defaultCurrentPage,
        perPage: data.per_page ?? data.itemsPerPage ?? defaultPerPage,
        total: data.total ?? data.totalItems ?? 0,
        totalPages: data.total_pages ?? null,
      }
    },

    /**
     * Set pagination for current page and items per page to default or stored values.
     */
    initPagination () {
      let currentPage = this.defaultPagination?.currentPage ?? 1
      let perPage = this.defaultPagination?.perPage ?? 10

      if (window.localStorage.getItem(this.storageKeyPagination)) {
        currentPage = Number(JSON.parse(window.localStorage.getItem([this.storageKeyPagination])).currentPage)
        perPage = Number(JSON.parse(window.localStorage.getItem([this.storageKeyPagination])).perPage)
      }

      this.pagination = {
        currentPage,
        perPage,
      }
    },

    /**
     * Update pagination with data from the DB and local Storage.
     * Falls back to API-provided values if localStorage is empty.
     * @param {object} data - Pagination data from the DB via API (supports both EDT 2.0 and API Platform 3.0).
     */
    updatePagination (data) {
      const normalized = this.normalizePagination(data)
      const storedPagination = window.localStorage.getItem(this.storageKeyPagination)

      let currentPage = normalized.currentPage
      let perPage = normalized.perPage

      if (storedPagination) {
        const parsed = JSON.parse(storedPagination)
        currentPage = Number(parsed.currentPage)
        perPage = Number(parsed.perPage)
      }

      this.pagination = {
        count: normalized.count,
        currentPage,
        limits: this.defaultPagination?.limits ?? [10, 25, 50, 100],
        perPage,
        total: normalized.total,
        totalPages: normalized.totalPages,
      }
    },

    /**
     * Set local storage for pagination.
     * @param {object} data - Pagination data from the DB via API (supports both EDT 2.0 and API Platform 3.0).
     */
    setLocalStorage (data) {
      const normalized = this.normalizePagination(data)
      const paginationData = { currentPage: normalized.currentPage, perPage: normalized.perPage }

      window.localStorage.setItem(this.storageKeyPagination, JSON.stringify(paginationData))
    },
  },
}
