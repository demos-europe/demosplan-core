/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { del } from 'vue'

const Voter = {
  namespaced: true,

  name: 'Voter',

  state: {
    voters: {}
  },

  getters: {
    getVoters: state => state.voters
  },

  mutations: {
    setVoters (state, initVoters) {
      state.voters = initVoters
    },
    addNewVoter (state, voter) {
      state.voters[Object.keys(state.voters).length] = voter
    },
    updateVoter (state, { index, newData }) {
      Object.assign(state.voters[index], newData)
    },
    removeVoter (state, index) {
      delete state.voters[index]
    }

  }
}

export default Voter
