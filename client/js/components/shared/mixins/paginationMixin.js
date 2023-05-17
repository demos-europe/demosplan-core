/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
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
        currentPage: currentPage,
        perPage: perPage
      }
    },

    /**
     * Update pagination with data from the DB and local Storage.
     * @param {object} pagination - Pagination data from the DB via API.
     */
    updatePagination (pagination) {
      const currentPage = Number(JSON.parse(window.localStorage.getItem([this.storageKeyPagination])).currentPage)
      const perPage = Number(JSON.parse(window.localStorage.getItem([this.storageKeyPagination])).perPage)
      this.pagination = {
        count: pagination.count,
        currentPage: currentPage,
        limits: this.defaultPagination.limits,
        perPage: perPage,
        total: pagination.total,
        totalPages: pagination.total_pages
      }
    }
  }
}
