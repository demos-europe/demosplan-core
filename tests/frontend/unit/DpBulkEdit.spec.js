/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import $ from 'jquery'
import DpBulkEditStatement from '@DemosPlanStatementBundle/components/assessmentTable/DpBulkEditStatement'
import shallowMountWithGlobalMocks from '@DemosPlanCoreBundle/VueConfigLocal'
import Vuex from 'vuex'

global.$ = $

describe('DpBulkEditStatement', () => {
  it('should be an object', () => {
    expect(typeof DpBulkEditStatement).toBe('object')
  })

  it('should be named DpBulkEdit', () => {
    expect(DpBulkEditStatement.name).toBe('DpBulkEditStatement')
  })

  let store
  let actions
  let getters

  const stubs = {
    DpTiptap: '<h3>DpLinkModal</h3>'
  }

  /*
  beforeEach(() => {
    actions = {
      setProcedureIdAction: jest.fn(),
      setSelectedElementsAction: jest.fn(),
      addToSelectionAction: jest.fn()
    }

    getters = {
      selectedElementsLength: jest.fn()
    }

    store = new Vuex.Store({
      modules: {
        statement: {
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
  })
  */

  // @TODO: find out how to fix 'Routing is not defined' error
  // it('renders correct markup with isEditMode = true', () => {
  //
  //     let wrapper = shallowMountWithGlobalMocks(
  //         DpBulkEdit,
  //         {
  //             propsData: {
  //                 procedureId: '2a1cc2f5-bd9e-11e8-b87f-4f2df2384097',
  //                 isEditMode: true,
  //                 token: ''
  //             },
  //             store,
  //             actions
  //          },
  //     );
  //     expect(wrapper.html()).toMatchSnapshot()
  // });
})
