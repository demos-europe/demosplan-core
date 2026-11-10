/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Version history slidebar of the assessment table. Lives in the store because the triggers
 * (e.g. TableCardFlyoutMenu) are far away from the component owning the slidebar (DpTable)
 */
interface VersionHistoryEntity {
  externId: string
  id: string
  type: 'fragment' | 'statement'
}

interface VersionHistorySlidebarState {
  entity: VersionHistoryEntity | null
  isOpen: boolean
  reloadKey: number
}

const VersionHistorySlidebarStore = {
  namespaced: true,

  name: 'VersionHistorySlidebar',

  state: (): VersionHistorySlidebarState => ({
    entity: null,
    isOpen: false,
    reloadKey: 0,
  }),

  mutations: {
    // Ignores other entities so edits elsewhere in the table do not reload the shown history
    markEntityUpdated (state: VersionHistorySlidebarState, entityId: string) {
      if (state.entity?.id === entityId) {
        state.reloadKey++
      }
    },

    setIsOpen (state: VersionHistorySlidebarState, isOpen: boolean) {
      state.isOpen = isOpen
    },

    // Bumps the reload key so reopening the same entity reloads its history
    showHistory (state: VersionHistorySlidebarState, entity: VersionHistoryEntity) {
      state.entity = entity
      state.isOpen = true
      state.reloadKey++
    },
  },
}

export default VersionHistorySlidebarStore
