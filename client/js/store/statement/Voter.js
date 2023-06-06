/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
const Voter = {

  namespaced: true,
  name: 'voter',

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
      state.voters.splice(index, 1)
    }

  }
}

export default Voter
