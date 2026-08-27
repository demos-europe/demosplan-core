/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/*
 * State for the segments-list slidebar. The button that opens it lives inside SegmentsList.vue, while
 * the slidebar is a separate element in the twig template, so a store lets them share this state.
 * Registered in administrationSegmentsList.js.
 */
const SegmentsListFilterStore = {
  namespaced: true,

  name: 'SegmentsListFilter',

  state: {
    // Slidebar view: filter panel (true) or version history (false).
    isFilterPanelActive: true,
    // Whether the slidebar is open; bound to dp-slidebar's `open` prop as the single source of truth.
    isSlidebarOpen: false,
  },

  mutations: {
    setIsFilterPanelActive (state, isFilterPanelActive) {
      state.isFilterPanelActive = isFilterPanelActive
    },

    setIsSlidebarOpen (state, isSlidebarOpen) {
      state.isSlidebarOpen = isSlidebarOpen
    },
  },

  getters: {
    getIsFilterPanelActive: (state) => state.isFilterPanelActive,

    getIsSlidebarOpen: (state) => state.isSlidebarOpen,
  },
}

export default SegmentsListFilterStore
