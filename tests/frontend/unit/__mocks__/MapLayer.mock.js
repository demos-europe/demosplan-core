/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

export default function () {
  return {
    actions: {
      get: jest.fn()
    },
    getters: {
      selectedElementsLength: jest.fn()
    },
    store: new Vuex.Store({
      modules: {
        Statement: {
          namespaced: true,
          state: {
            procedureId: '',
            selectedElements: {},
            statements: {}
          },
          actions,
          getters
        }
      }
    })
  }
}
