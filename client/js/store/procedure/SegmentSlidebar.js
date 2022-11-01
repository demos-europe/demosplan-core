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
    slidebar: {
      segmentId: '',
      showTab: ''
    },
    commentsList: {
      currentCommentText: '',
      externId: '',
      procedureId: '',
      segmentId: '',
      show: false,
      showForm: false,
      statementId: ''
    },
    isLoading: false
  },

  mutations: {
    setContent (state, data) {
      Vue.set(state, data.prop, data.val)
    },

    setProperty (state, data) {
      Vue.set(state, [data.prop], data.val)
    }
  },

  actions: {
    toggleSlidebarContent ({ commit }, data) {
      commit('setContent', data)
    }
  },

  getters: {
    commentsList: (state) => state.commentsList,

    currentCommentText: (state) => state.commentsList.currentCommentText,

    procedureId: (state) => state.commentsList.procedureId,

    showForm: (state) => state.commentsList.showForm,

    statementId: (state) => state.commentsList.statementId,
  }
}

export default SegmentSlidebarStore
