/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const NewProcedure = {
  namespaced: true,

  name: 'newProcedure',

  state: {
    requireField: true
  },

  mutations: {
    /**
     *
     * @param state {Object}
     * @param value {Boolean} Sets required form field dynamically to true/false.
     */
    setRequiredField (state, value) {
      state.requireField = value
    }
  },
}
export default NewProcedure
