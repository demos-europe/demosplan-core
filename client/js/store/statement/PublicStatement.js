/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { hasOwnProp } from '@demos-europe/demosplan-ui'

const statementStructure = {
  action: 'statementpublicnew',
  delete_file: [],
  location_is_set: '',
  r_city: '',
  r_county: '',
  r_document_id: '',
  r_document_title: '',
  r_element_id: '',
  r_element_title: '',
  r_email: '',
  r_email2: '',
  r_firstname: '',
  r_getEvaluation: 'email',
  r_getFeedback: 'off',
  r_houseNumber: '',
  r_ident: '',
  r_isNegativeReport: '0',
  r_location: '',
  r_location_priority_area_key: '',
  r_location_priority_area_type: '',
  r_location_point: '',
  r_location_geometry: '',
  r_makePublic: 'off',
  r_paragraph_id: '',
  r_paragraph_title: '',
  r_lastname: '',
  r_loadtime: '',
  r_phone: '',
  r_postalCode: '',
  r_privacy: 'off',
  r_represents: '',
  r_street: '',
  r_submitter_name: '',
  r_submitter_role: '',
  r_text: '',
  r_userGroup: '',
  r_userOrganisation: '',
  r_useName: '',
  r_userPosition: '',
  r_userState: '',
  url: '',
  uploadedFiles: ''
}

const PublicStatementStore = {
  namespaced: true,

  name: 'PublicStatement',

  state: {
    activeActionBoxTab: 'talk',
    activeTab: '',
    highlighted: {
      location: false,
      documents: false
    },
    initDraftStatements: {},
    initForm: '',
    localStorageName: '',
    userId: '',
    procedureId: '',
    showMapHint: false,
    statement: statementStructure,
    draftStatements: {},
    unsavedDrafts: []
  },

  mutations: {
    addUnsavedDraft (state, id) {
      const idx = state.unsavedDrafts.findIndex(el => el === id)
      if (idx < 0) {
        state.unsavedDrafts.push(id)
        localStorage.setItem(`unsavedDrafts:${state.userId}:${state.procedureId}`, JSON.stringify(state.unsavedDrafts))
      }
    },

    initialiseStore (state, data) {
      if (state.draftStatements[state.statement.r_ident]) {
        state.initForm = JSON.stringify(state.draftStatements[state.statement.r_ident])
      } else {
        state.initForm = JSON.stringify(state.statement)
      }

      state.procedureId = data.procedureId
      state.userId = data.userId

      // Prepare localStorage to handle Drafts an new
      state.localStorageName = ''

      const localStorageForUser = `user:${state.userId}:${state.procedureId}`
      state.showMapHint = true
      const userItem = localStorage.getItem(localStorageForUser)
      if (userItem) {
        const userState = JSON.parse(userItem)
        Object.keys(userState).forEach(el => {
          state[el] = userState[el]
        })
      }

      const unsavedDraftsStorage = localStorage.getItem(`unsavedDrafts:${state.userId}:${state.procedureId}`)
      state.unsavedDrafts = unsavedDraftsStorage ? JSON.parse(unsavedDraftsStorage) : []
      state.unsavedDrafts.forEach(draftId => {
        state.initDraftStatements[draftId] = localStorage.getItem(`init:publicStatement:${state.userId}:${state.procedureId}:${draftId}`)
      })
    },

    clearDraftState (state, draftStatementId) {
      state.initForm = JSON.stringify(statementStructure)
      delete state.initDraftStatements[draftStatementId]
      localStorage.removeItem(`init:${state.localStorageName}`)
      localStorage.removeItem(`${state.localStorageName}`)
    },

    update (state, data) {
      state[data.key] = data.val

      const localStorageForUser = `user:${state.userId}:${state.procedureId}`
      let userState = {}
      if (localStorage.getItem(localStorageForUser)) {
        userState = JSON.parse(localStorage.getItem(localStorageForUser))
      }
      userState = { ...userState, [data.key]: data.val }
      localStorage.setItem(localStorageForUser, JSON.stringify(userState))
    },

    updateHighlighted (state, data) {
      state.highlighted[data.key] = data.val
    },

    updateStatement (state, data) {
      const statementId = data.r_ident || state.statement.r_ident || ''
      if (hasOwnProp(data, 'r_ident') && data.r_ident !== '') {
        state.localStorageName = `publicStatement:${state.userId}:${state.procedureId}:${data.r_ident}`
      } else {
        state.localStorageName = `publicStatement:${state.userId}:${state.procedureId}:new`
      }

      if (statementId !== '') {
        if (hasOwnProp(state.draftStatements, statementId)) {
          state.draftStatements[statementId] = { ...state.draftStatements[statementId], ...data }
        } else {
          state.draftStatements[statementId] = { ...statementStructure, ...data }
        }
      }

      state.statement = { ...state.statement, ...data }
      localStorage.setItem(state.localStorageName, JSON.stringify(state.statement))
    },

    updateDeleteFile (state, hash) {
      const idx = state.statement.delete_file.findIndex(el => el === hash)
      if (idx > -1) {
        state.statement.delete_file.splice(idx, 1)
      } else {
        state.statement.delete_file.push(hash)
      }
    },

    resetInitForm (state, draftStatementId) {
      state.initForm = JSON.stringify(statementStructure)
      if (draftStatementId) {
        state.initDraftStatements[draftStatementId] = JSON.stringify(state.draftStatements[draftStatementId])
        localStorage.setItem(`init:${state.localStorageName}`, state.initDraftStatements[draftStatementId])
        state.initForm = state.initDraftStatements[draftStatementId]
        state.draftStatements[draftStatementId] = state.statement
        localStorage.setItem(`${state.localStorageName}`, state.initDraftStatements[draftStatementId])
      }
    },

    removeStatementProp (state, propKey) {
      delete state.statement[propKey]
    },

    removeUnsavedDraft (state, id) {
      const idx = state.unsavedDrafts.findIndex(el => el === id)
      if (idx > -1) {
        state.unsavedDrafts.splice(idx, 1)
        localStorage.setItem(`unsavedDrafts:${state.userId}:${state.procedureId}`, JSON.stringify(state.unsavedDrafts))
      }
    },

    resetStatement (state) {
      // Prevent parsing error in Entwurfsordner, where the statement is an empty object and not JSON
      if (Object.keys(state.initForm).length === 0) {
        state.initForm = '{}'
      }
      if (hasOwnProp(state.statement, 'r_ident')) {
        delete state.initDraftStatements[state.statement.r_ident]
      }
      state.statement = JSON.parse(state.initForm)
      localStorage.removeItem(state.localStorageName)
    }
  },

  actions: {
  },

  getters: {
  }
}

export default PublicStatementStore
