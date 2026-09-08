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
     * Normalize pagination data from both EDT 2.0 and API Platform 3.0 formats.
     * @param {object} meta - Meta object from API response (data.meta).
     * @returns {object} - Normalized pagination object with EDT 2.0 structure.
     */
    normalizePagination (data) {
      // API Platform 3.0 format
      if ('totalItems' in data) {
        const {
          totalItems,
          currentPage,
          itemsPerPage,
        } = data

        return {
          count: totalItems,
          currentPage,
          perPage: itemsPerPage,
          total: totalItems,
          totalPages: null,
        }
      } else {
        // EDT 2.0 format
        const {
          count,
          current_page: currentPage,
          per_page: perPage,
          total,
          total_pages: totalPages,
        } = data

        return {
          count,
          currentPage,
          perPage,
          total,
          totalPages,
        }
      }

      return meta
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
      console.log('updatePagination - normalized', normalized)
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
      console.log('data', data)
      const normalized = this.normalizePagination(data)
      console.log('setLocalStorage - normalized', normalized)

      console.log('normalized', normalized)
      const paginationData = { currentPage: normalized.currentPage, perPage: normalized.perPage }

      window.localStorage.setItem(this.storageKeyPagination, JSON.stringify(paginationData))
    },
  },
}
