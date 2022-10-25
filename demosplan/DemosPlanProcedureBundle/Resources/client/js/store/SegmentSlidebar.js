/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const SegmentSlidebarStore = {
  namespaced: true,
  name: 'segmentSlidebar',

  state: {
    content: {
      commentsList: {
        currentCommentText: '',
        externId: '',
        procedureId: '',
        segmentId: '',
        show: false,
        showForm: false,
        statementId: ''
      },
      olMap: {
        show: false
      },
      versionHistory: {
        segmentId: '',
        show: false
      }
    },
    isLoading: false
  },

  mutations: {
    setContent (state, data) {
      Vue.set(state.content, data.prop, data.val)
    },

    setProperty (state, data) {
      Vue.set(state, [data.prop], data.val)
    }
  },

  actions: {
    toggleSlidebarContent ({ commit, state }, data) {
      commit('setContent', data)

      Object.keys(state.content).forEach(key => {
        if (key !== data.prop) {
          commit('setContent', { prop: key, val: { ...state.content[key], show: false } })
        }
      })
    }
  },

  getters: {
    commentsList: (state) => state.content.commentsList,

    currentCommentText: (state) => state.content.commentsList.currentCommentText,

    olMap: (state) => state.content.olMap,

    procedureId: (state) => state.content.commentsList.procedureId,

    showForm: (state) => state.content.commentsList.showForm,

    statementId: (state) => state.content.commentsList.statementId,

    versionHistory: (state) => state.content.versionHistory
  }
}

export default SegmentSlidebarStore
