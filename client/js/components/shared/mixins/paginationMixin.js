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
        perPage
      }
    },

    /**
     * Update pagination with data from the DB and local Storage.
     * @param {object} data - Pagination data from the DB via API.
     */
    updatePagination (data) {
      const currentPage = Number(JSON.parse(window.localStorage.getItem([this.storageKeyPagination])).currentPage)
      const perPage = Number(JSON.parse(window.localStorage.getItem([this.storageKeyPagination])).perPage)
      this.pagination = {
        count: data.count,
        currentPage,
        limits: this.defaultPagination.limits,
        perPage,
        total: data.total,
        totalPages: data.total_pages
      }
    },

    /**
     * Set local storage for pagination.
     * @param {object} data - Pagination data from the DB via API.
     */
    setLocalStorage (data) {
      const paginationData = { currentPage: data.current_page, perPage: data.per_page }
      window.localStorage.setItem(this.storageKeyPagination, JSON.stringify(paginationData))
    }
  }
}
