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
     * Sets required attribute of a form field dynamically to true/false if the attribute is bound to requireField.
     * @param state {Object}
     * @param value {Boolean}
     */
    setRequiredField (state, value) {
      state.requireField = value
    }
  },
}
export default NewProcedure
