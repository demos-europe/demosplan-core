/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const SegmentsBookmarksStore = {
  namespaced: true,

  name: 'SegmentsBookmarks',

  state: {
    allBookmarks: [],
    currentBookmark: null,
    currentBookmarkId: null,
    isApplyingBookmark: false,
  },

  mutations: {
    setAllBookmarks (state, bookmarks) {
      state.allBookmarks = bookmarks
    },

    setCurrentBookmark (state, bookmark) {
      state.currentBookmark = bookmark
    },

    setCurrentBookmarkId (state, bookmarkId) {
      state.currentBookmarkId = bookmarkId
    },

    setIsApplyingBookmark (state, isApplying) {
      state.isApplyingBookmark = isApplying
    },
  },

  actions: {
    /**
     * Fetch all bookmarks from the Bookmark API store
     * @param {Object} context - Vuex context
     */
    async fetchBookmarks ({ commit, dispatch }) {
      try {
        await dispatch('Bookmark/list', {
          params: {
            fields: {
              Bookmark: 'name,queryHash,filter,searchPhrase,viewSettings'
            }
          }
        }, { root: true })

        // After fetching, get the bookmarks from the Bookmark store and save to our store
        const bookmarks = await dispatch('getBookmarksFromApiStore', null, { root: false })
        commit('setAllBookmarks', bookmarks)

        return bookmarks
      } catch (error) {
        console.error('Failed to fetch bookmarks:', error)
        return []
      }
    },

    /**
     * Get bookmarks from the Bookmark API store
     * @param {Object} context - Vuex context
     */
    getBookmarksFromApiStore ({ rootState }) {
      const items = rootState.Bookmark?.items || {}
      return Object.values(items)
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
      commit('setIsApplyingBookmark', true)
      commit('setCurrentBookmarkId', bookmarkId)
      commit('setCurrentBookmark', bookmark)

      try {
        // First, clear all existing filters
        await dispatch('FilterFlyout/updateFilterQuery', {}, { root: true })

        // Apply filters from bookmark - same pattern as mounted() with initialFilter
        if (
          bookmark.attributes.filter &&
          Array.isArray(bookmark.attributes.filter) === false &&
          Object.keys(bookmark.attributes.filter).length
        ) {
          Object.values(bookmark.attributes.filter).forEach((filter) => {
            if (!filter.condition) {
              return
            }

            const query = {}
            query[filter.condition.value] = filter
            dispatch('FilterFlyout/updateFilterQuery', query, { root: true })
          })
        }

        // Commit the filters
        await dispatch('FilterFlyout/commitFilterQuery', null, { root: true })

        commit('setIsApplyingBookmark', false)

        return true
      } catch (error) {
        console.error('Failed to apply bookmark:', error)
        commit('setIsApplyingBookmark', false)

        return false
      }
    },

    /**
     * Create a new bookmark
     * @param {Object} context - Vuex context
     * @param {Object} payload - Bookmark data
     */
    async createBookmark ({ dispatch }, payload) {
      try {
        await dispatch('Bookmark/create', payload, { root: true })
        await dispatch('fetchBookmarks')
        return true
      } catch (error) {
        console.error('Failed to create bookmark:', error)
        return false
      }
    },

    /**
     * Update an existing bookmark
     * @param {Object} context - Vuex context
     * @param {Object} payload - Bookmark update data
     */
    async updateBookmark ({ dispatch }, payload) {
      try {
        await dispatch('Bookmark/update', payload, { root: true })
        await dispatch('fetchBookmarks')
        return true
      } catch (error) {
        console.error('Failed to update bookmark:', error)
        return false
      }
    },

    /**
     * Delete a bookmark
     * @param {Object} context - Vuex context
     * @param {String} id - Bookmark ID
     */
    async deleteBookmark ({ dispatch }, id) {
      try {
        await dispatch('Bookmark/delete', id, { root: true })
        await dispatch('fetchBookmarks')
        return true
      } catch (error) {
        console.error('Failed to delete bookmark:', error)
        return false
      }
    },

    /**
     * Reset to default view
     * @param {Object} context - Vuex context
     */
    async resetToDefaultView ({ commit, dispatch }) {
      commit('setCurrentBookmarkId', null)
      commit('setCurrentBookmark', null)

      // Clear all filters
      await dispatch('FilterFlyout/updateFilterQuery', {}, { root: true })
      await dispatch('FilterFlyout/commitFilterQuery', null, { root: true })

      // Return true to indicate caller should trigger applyQuery
      return true
    },
  },

  getters: {
    allBookmarks: (state) => state.allBookmarks,
    currentBookmark: (state) => state.currentBookmark,
    currentBookmarkId: (state) => state.currentBookmarkId,
    isApplyingBookmark: (state) => state.isApplyingBookmark,
  },
}

export default SegmentsBookmarksStore
