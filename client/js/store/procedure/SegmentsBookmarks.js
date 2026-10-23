/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { dpApi } from '@demos-europe/demosplan-ui'

const bookmarkApiUrl = () => `${Routing.getBaseUrl()}/api/3.0/Bookmark`

const SegmentsBookmarksStore = {
  namespaced: true,

  name: 'SegmentsBookmarks',

  state: {
    allBookmarks: [],
    currentBookmark: null,
    currentBookmarkId: null,
    currentSearchPhrase: '',
    currentSorting: '',
    currentSelectedColumns: [],
    defaultSelectedColumns: [],
    isBookmarkLoading: false,
    isViewStateInitialized: false,
  },

  mutations: {
    setBookmarks (state, bookmarks) {
      state.allBookmarks = bookmarks
    },

    addBookmark (state, bookmark) {
      state.allBookmarks.push(bookmark)
    },

    updateBookmarkInStore (state, updatedBookmark) {
      const index = state.allBookmarks.findIndex(b => b.id === updatedBookmark.id)

      if (index !== -1) {
        state.allBookmarks[index] = updatedBookmark
      }
    },

    removeBookmark (state, bookmarkId) {
      state.allBookmarks = state.allBookmarks.filter(b => b.id !== bookmarkId)
    },

    setCurrentBookmark (state, bookmark) {
      state.currentBookmark = bookmark
    },

    setCurrentBookmarkId (state, bookmarkId) {
      state.currentBookmarkId = bookmarkId
    },

    setIsBookmarkLoading (state, isLoading) {
      state.isBookmarkLoading = isLoading
    },

    setCurrentSearchPhrase (state, searchPhrase) {
      state.currentSearchPhrase = searchPhrase
    },

    setCurrentSorting (state, sorting) {
      state.currentSorting = sorting
    },

    setCurrentSelectedColumns (state, columns) {
      state.currentSelectedColumns = columns
    },

    setDefaultSelectedColumns (state, columns) {
      state.defaultSelectedColumns = columns
    },

    setViewStateInitialized (state, value) {
      state.isViewStateInitialized = value
    },
  },

  actions: {
    /**
     * Fetch all bookmarks
     * @param {Object} context - Vuex context
     */
    async fetchBookmarks ({ commit }) {
      commit('setIsBookmarkLoading', true)

      try {
        const response = await dpApi.get(bookmarkApiUrl(), {
          fields: {
            Bookmark: [
              'name',
              'queryHash',
              'filter',
              'searchPhrase',
              'viewSettings',
            ].join(),
          },
        })

        const bookmarks = response.data?.data || []

        commit('setBookmarks', bookmarks)

        return bookmarks
      } catch (error) {
        console.error('Failed to fetch bookmarks:', error)

        return []
      } finally {
        commit('setIsBookmarkLoading', false)
      }
    },

    /**
     * Apply a bookmarked view by its ID
     *
     * This action applies filters from the bookmark to the FilterFlyout store.
     * The searchPhrase and viewSettings are applied by the component.
     *
     * @param {Object} context - Vuex context
     * @param {Object} payload
     * @param {String} payload.bookmarkId - The bookmark ID to apply
     * @param {Object} payload.bookmark - The bookmark object with attributes (filter, searchPhrase, viewSettings, queryHash)
     */
    async applyBookmark ({ commit, dispatch }, { bookmarkId, bookmark }) {
      commit('setIsBookmarkLoading', true)
      commit('setCurrentBookmarkId', bookmarkId)
      commit('setCurrentBookmark', bookmark)

      try {
        await dispatch('FilterFlyout/updateFilterQuery', {}, { root: true }) // First, clear all existing filters

        const filters = bookmark.attributes.filter

        if (filters && !Array.isArray(filters)) {
          Object.values(filters).forEach(filter => {
            if (filter.condition) {
              dispatch('FilterFlyout/updateFilterQuery', {
                [filter.condition.value]: filter,
              }, { root: true })
            }
          })
        }

        await dispatch('FilterFlyout/commitFilterQuery', null, { root: true })
        commit('setIsBookmarkLoading', false)

        return true
      } catch {
        commit('setIsBookmarkLoading', false)

        return false
      }
    },

    /**
     * Create a new bookmark
     * @param {Object} context - Vuex context
     * @param {Object} payload - Bookmark data
     */
    async createBookmark ({ commit }, payload) {
      commit('setIsBookmarkLoading', true)

      try {
        const response = await dpApi.post(bookmarkApiUrl(), {}, { data: payload })
        const newBookmark = response.data?.data

        if (newBookmark) {
          commit('addBookmark', newBookmark)
        }

        return true
      } catch {
        return false
      } finally {
        commit('setIsBookmarkLoading', false)
      }
    },

    /**
     * Update an existing bookmark
     * @param {Object} context - Vuex context
     * @param {Object} payload - Bookmark update data
     */
    async updateBookmark ({ commit }, payload) {
      commit('setIsBookmarkLoading', true)

      try {
        const url = `${bookmarkApiUrl()}/${encodeURIComponent(payload.id)}`
        const apiPayload = {
          data: {
            id: payload.id,
            type: 'Bookmark',
            attributes: {
              queryHash: payload.attributes.queryHash,
            },
          },
        }

        const response = await dpApi.patch(url, {}, apiPayload)
        const updatedBookmark = response.data?.data

        if (updatedBookmark) {
          commit('updateBookmarkInStore', updatedBookmark)
          commit('setCurrentBookmark', updatedBookmark)
        }

        return true
      } catch {
        return false
      } finally {
        commit('setIsBookmarkLoading', false)
      }
    },

    /**
     * Delete a bookmark
     * @param {Object} context - Vuex context
     * @param {String} id - Bookmark ID
     */
    async deleteBookmark ({ commit }, id) {
      try {
        const url = `${bookmarkApiUrl()}/${encodeURIComponent(id)}`

        await dpApi.delete(url)
        commit('removeBookmark', id)

        return true
      } catch {
        return false
      }
    },

    /**
     * Reset to default view
     * @param {Object} context - Vuex context
     */
    resetToDefaultView ({ commit }) {
      commit('setCurrentBookmarkId', null)
      commit('setCurrentBookmark', null)
    },
  },

  getters: {
    allBookmarks: (state) => state.allBookmarks,
    currentBookmark: (state) => state.currentBookmark,
    currentBookmarkId: (state) => state.currentBookmarkId,
    currentSearchPhrase: (state) => state.currentSearchPhrase,
    currentSelectedColumns: (state) => state.currentSelectedColumns,
    currentSorting: (state) => state.currentSorting,
    defaultSelectedColumns: (state) => state.defaultSelectedColumns,
    isBookmarkLoading: (state) => state.isBookmarkLoading,
    isViewStateInitialized: (state) => state.isViewStateInitialized,
  },
}

export default SegmentsBookmarksStore
