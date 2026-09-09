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
      return {
        count: data.count ?? data.totalItems,
        currentPage: data.current_page ?? data.currentPage,
        perPage: data.per_page ?? data.itemsPerPage,
        total: data.total ?? data.totalItems,
        totalPages: data.total_pages ?? null,
      }
    },

    /**
     * Set pagination for current page and items per page to default or stored values.
     */
    initPagination () {
      let currentPage = this.defaultPagination.currentPage
      let perPage = this.defaultPagination.perPage

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
     * @param {object} data - Pagination data from the DB via API (supports both EDT 2.0 and API Platform 3.0).
     */
    updatePagination (data) {
      const normalized = this.normalizePagination(data)
      const currentPage = Number(JSON.parse(window.localStorage.getItem([this.storageKeyPagination])).currentPage)
      const perPage = Number(JSON.parse(window.localStorage.getItem([this.storageKeyPagination])).perPage)

      this.pagination = {
        count: normalized.count,
        currentPage,
        limits: this.defaultPagination.limits,
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
