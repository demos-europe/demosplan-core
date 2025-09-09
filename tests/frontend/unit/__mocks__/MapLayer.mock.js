/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import Vuex from 'vuex'

export default function () {
  return {
    store: new Vuex.Store({
      modules: {
        Statement: {
          namespaced: true,
          state: {
            procedureId: '',
            selectedElements: {},
            statements: {}
          },
          actions: {
            get: jest.fn()
          },
          getters: {
            selectedElementsLength: jest.fn()
          }
        }
      }
    })
  }
}
