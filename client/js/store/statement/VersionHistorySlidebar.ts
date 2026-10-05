/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Open state of the version history slidebar. Lives in the store because the trigger
 * (e.g. TableCardFlyoutMenu) is far away from the component owning the slidebar (e.g. DpTable)
 */
interface VersionHistorySlidebarState {
  isOpen: boolean
}

const VersionHistorySlidebarStore = {
  namespaced: true,

  name: 'VersionHistorySlidebar',

  state: (): VersionHistorySlidebarState => ({
    isOpen: false,
  }),

  mutations: {
    setIsOpen (state: VersionHistorySlidebarState, isOpen: boolean) {
      state.isOpen = isOpen
    },
  },
}

export default VersionHistorySlidebarStore
