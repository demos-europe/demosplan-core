/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { checkResponse, dpApi, hasOwnProp } from '@demos-europe/demosplan-ui'
import { del, set } from 'vue'

export default {
  namespaced: true,

  name: 'Fragment',

  state: {
    /**
     * Fragment contains objects with such a structure:
     * statementId: {
     *    filteredFragments: {Array of fragment objects},
     *    fragments: {Array of fragment objects},
     *    statement: {Object}
     * }
     */
    fragments: {},
    initFragments: [],
    procedureId: '',
    /**
     * SelectedFragment is an object with such a structure:
     * selectedFragments: {
     *    fragmentId: { fragment with id and statementId }
     * }
     */
    selectedFragments: {}
  },

  mutations: {
    /**
     * Used in fragmentList of FB because the data structure there is different than in Fragment.vue in AT
     * @param {Object} fragment
     * @param {String} statementId
     */
    addFragment (state, { fragment, statementId }) {
      /*
       * Check if fragement allready exists
       * return if so.
       */
      if (hasOwnProp(state.fragments, statementId) && hasOwnProp(state.fragments[statementId], fragment.id)) {
        return
      }
      // If we don't have any fragments for the statement, we have to create an Object for the statement right now
      if (hasOwnProp(state.fragments, statementId) === false) {
        state.fragments[statementId] = { fragments: [] }
      }
      state.fragments[statementId].fragments.push(fragment)
    },

    /**
     * Set selected fragments
     * @param {Object} fragment
     */
    addFragmentToSelection (state, fragment) {
      set(state.selectedFragments, [fragment.id], fragment)
    },

    /**
     *
     * @param {Object} ids
     */
    deleteFragment (state, ids) {
      const statementObj = JSON.parse(JSON.stringify(state.fragments[ids.statementId]))
      const statementFragments = statementObj.fragments
      const fragmentToDelete = statementFragments.findIndex(fragment => fragment.id === ids.fragmentId)
      statementFragments.splice(fragmentToDelete, 1)
      statementObj.fragments = statementFragments

      if (hasOwnProp(state.fragments[ids.statementId], 'filteredFragments')) {
        const filteredFragments = statementObj.filteredFragments
        const filteredFragmentToDelete = filteredFragments.findIndex(fragment => fragment.id === ids.fragmentId)
        if (filteredFragmentToDelete !== -1) {
          filteredFragments.splice(filteredFragmentToDelete, 1)
        } else {
          return undefined
        }
        statementObj.filteredFragments = filteredFragments
      }

      const fragments = { ...state.fragments }
      fragments[ids.statementId] = statementObj
      set(state, 'fragments', fragments)

      if (hasOwnProp(state.selectedFragments, ids.fragmentId)) {
        del(state.selectedFragments, ids.fragmentId)
        state.selectedFragments = { ...state.selectedFragments }
      }

      const selectedEntries = JSON.parse(sessionStorage.getItem('selectedFragments')) || {}
      if (selectedEntries && hasOwnProp(selectedEntries, state.procedureId) && hasOwnProp(selectedEntries[state.procedureId], ids.fragmentId)) {
        delete selectedEntries[state.procedureId][ids.fragmentId]
        sessionStorage.setItem('selectedFragments', JSON.stringify(selectedEntries))
      }
      return Promise.resolve(true)
    },

    /**
     *
     * @param {Object} fragments
     * @param {String} statementId
     */
    loadFragmentsToStore (state, { fragments, statementId }) {
      if (hasOwnProp(fragments, 'fragments')) {
        for (const fragment in fragments.fragments) {
          // If the reviewer has been set, update fragment assignment
          if (hasOwnProp(fragment, 'assignee') && hasOwnProp(fragment.assignee, 'id') === false) {
            fragment.assignee = { id: '', name: '', orgaName: '', uId: '' }
          }
        }
      }
      set(state.fragments, statementId, fragments)
    },

    /**
     * Remove fragments from list of selectedFragments
     * @param {String} fragmentId
     */
    removeFragmentFromSelection (state, fragmentId) {
      del(state.selectedFragments, fragmentId)
    },

    /**
     *
     * @param {Array} initFragments
     */
    setInitFragments (state, initFragments) {
      set(state, 'initFragments', initFragments)
    },

    /**
     *
     * @param {String} procedureId
     */
    setProcedureId (state, procedureId) {
      state.procedureId = procedureId
      return Promise.resolve(true)
    },

    /**
     */
    setSideBarInitialized (state) {
      state.sideBarInitialized = true
    },

    /**
     *
     * @param {Object} data
     */
    updateFragment (state, data) {
      //  Reject if no statement id is found in data
      if (hasOwnProp(data, 'fragmentId') === false || hasOwnProp(data, 'statementId') === false) {
        return console.warn('fragment/updateFragment expects to be called with data.fragmentId and data.statementId')
      }

      // If fragment to update is selected and assignee or editableState is changed, we have to set it also in session storage
      if (hasOwnProp(data, 'assignee') && hasOwnProp(state.selectedFragments, data.fragmentId)) {
        set(state.selectedFragments[data.fragmentId], 'assignee', data.assignee)
        state.selectedFragments = { ...state.selectedFragments }

        const selectedEntries = JSON.parse(sessionStorage.getItem('selectedFragments')) || {}
        selectedEntries[state.procedureId][data.fragmentId].assignee = data.assignee
        sessionStorage.setItem('selectedFragments', JSON.stringify(selectedEntries))
      }

      /**
       * For reactivity reasons, we should update the fragment and then reassign the whole fragments object.
       */
      const fragmentId = data.fragmentId
      const statementId = data.statementId
      delete data.fragmentId
      delete data.statementId

      const fragmentInStore = state.fragments[statementId].fragments.find(frag => frag.id === fragmentId)
      const fragmentIndex = state.fragments[statementId].fragments.findIndex(frag => frag.id === fragmentId)
      state.fragments[statementId].fragments[fragmentIndex] = { ...fragmentInStore, ...data }
      state.fragments = { ...state.fragments }
    }
  },

  actions: {
    /**
     *
     * @param {Object} fragment
     */
    addFragmentToSelectionAction ({ state, commit }, fragment) {
      const selectedFragments = JSON.parse(sessionStorage.getItem('selectedFragments')) || {}
      if (hasOwnProp(selectedFragments, state.procedureId) === false) {
        selectedFragments[state.procedureId] = {}
      }

      selectedFragments[state.procedureId][fragment.id] = { ...fragment }

      sessionStorage.setItem('selectedFragments', JSON.stringify(selectedFragments))
      commit('addFragmentToSelection', fragment)
      return Promise.resolve(true)
    },

    /**
     *
     * @param {String} procedureId
     * @param {String} statementId
     * @param {String} fragmentId
     */
    deleteFragmentAction ({ commit }, { procedureId, statementId, fragmentId }) {
      const url = Routing.generate(
        'DemosPlan_statement_fragment_delete_ajax',
        {
          procedureId,
          statementId,
          fragmentId
        }
      )

      // We have to use params.append because params.set does not work in IE11
      const params = new FormData()
      params.append('delete', 'delete')

      return dpApi.post(url, params)
        .then(checkResponse)
        .then(response => {
          if (response.code === 200 && response.success === true) {
            commit('deleteFragment', { statementId, fragmentId })
            dplan.notify.notify('confirm', Translator.trans('confirm.fragment.deleted'))
            return Promise.resolve(true)
          } else {
            dplan.notify.notify('error', Translator.trans('error.delete'))
          }
        })
        .catch(() => dplan.notify.notify('error', Translator.trans('error.delete')))
    },

    /**
     *
     * @param {Object} data
     */
    loadFragments ({ commit }, data) {
      let url = Routing.generate('DemosPlan_assessment_statement_fragments_ajax', { procedureId: data.procedureId, statementId: data.statementId })

      if (document.location.search.length > 1) {
        url += document.location.search
      }

      return dpApi.get(url)
        .then(checkResponse)
        .then((response) => commit('loadFragmentsToStore', { fragments: response.data, statementId: data.statementId }))
    },

    /**
     *
     * @param {String} fragmentId
     */
    removeFragmentFromSelectionAction ({ state, commit }, fragmentId) {
      const selectedFragments = JSON.parse(sessionStorage.getItem('selectedFragments')) || {}

      if (hasOwnProp(selectedFragments, state.procedureId)) {
        delete selectedFragments[state.procedureId][fragmentId]
        sessionStorage.setItem('selectedFragments', JSON.stringify(selectedFragments))
      }

      commit('removeFragmentFromSelection', fragmentId)
      return Promise.resolve(true)
    },

    /**
     * Reset selectedFragments
     */
    resetSelection ({ state, commit }) {
      for (const itemId in state.selectedFragments) {
        commit('removeFragmentFromSelection', itemId)
      }
      const sessionStore = JSON.parse(sessionStorage.getItem('selectedFragments'))
      if (sessionStore) {
        delete sessionStore[state.procedureId]
        sessionStorage.setItem('selectedFragments', JSON.stringify(sessionStore))
      }
    },

    /**
     * Update the assignment status of the entity
     * The selected user gets set with the information passed as params
     * @param {String} fragmentId
     * @param {String} statementId
     * @param ignoreLastClaimed
     * @param {String} assigneeId
     * @param lastClaimed
     */
    setAssigneeAction ({ commit }, { fragmentId, statementId, ignoreLastClaimed, assigneeId, lastClaimed }) {
      return dpApi({
        method: 'PATCH',
        url: Routing.generate('dplan_claim_fragments_api', { entityId: fragmentId }),
        data: {
          data: {
            type: 'user',
            id: assigneeId,
            ignoreLastClaimed,
            ...((ignoreLastClaimed === false && typeof lastClaimed !== 'undefined') && { relationships: { lastClaimed: { data: { id: lastClaimed, type: 'user' } } } })
          }
        },
        headers: {
          'Content-type': 'application/vnd.api+json',
          Accept: 'application/vnd.api+json'
        }
      })
        .then(this.api.checkResponse)
        .then(response => {
          let updateObject = {}
          if (assigneeId === '' || assigneeId == null) {
            updateObject = { fragmentId, statementId, assignee: { id: '', name: '', orgaName: '', uId: '' } }
            commit('updateFragment', { ...updateObject, lastClaimedUserId: ignoreLastClaimed ? null : lastClaimed })
          } else {
            const assignee = { id: response.data.id, uId: response.data.id, name: response.data.attributes.name, orgaName: response.data.attributes.orgaName }
            updateObject = { fragmentId, statementId, assignee }
            commit('updateFragment', { ...updateObject, lastClaimedUserId: ignoreLastClaimed ? null : lastClaimed })
          }
          return updateObject
        })
    },

    /**
     *
     * @param {String} procedureId
     */
    setProcedureIdAction ({ commit }, procedureId) {
      commit('setProcedureId', procedureId)
      return Promise.resolve(true)
    },

    setSelectedFragmentsAction ({ state, dispatch }) {
      const selectedFrags = JSON.parse(sessionStorage.getItem('selectedFragments')) || {}
      // We dont have to do it if there are no items for this procedure
      if (hasOwnProp(selectedFrags, state.procedureId)) {
        for (const itemId in selectedFrags[state.procedureId]) {
          const item = selectedFrags[state.procedureId][itemId]
          const initFragment = state.initFragments.find(fragment => fragment.id === itemId)

          // If fragment is not in initFragments then it probably got deleted, so we remove it from selection
          if (state.initFragments.length > 0 && typeof initFragment === 'undefined') {
            dispatch('removeFragmentFromSelectionAction', itemId)
          } else {
            // Update assignee info
            if (initFragment && hasOwnProp(initFragment, 'assigneeId')) {
              item.assignee = { id: initFragment.assigneeId ? initFragment.assigneeId : '' }
            }
            dispatch('addFragmentToSelectionAction', item)
          }
        }
      }
      sessionStorage.setItem('selectedFragments', JSON.stringify(selectedFrags))
      return Promise.resolve(true)
    },

    /**
     *
     * @param {Object} data
     */
    updateFragmentAction ({ commit, state }, data) {
      //  Reject if no statement id is found in data
      if (hasOwnProp(data, 'id') === false) {
        return console.warn('fragment/updateFragmentAction expects to be called with data.id')
      }

      const payload = JSON.parse(JSON.stringify(data))
      // Because BE need paragraphId and not paragraphParentId (same for documents) we have to change it here
      if (hasOwnProp(payload, 'paragraphParentId')) {
        payload.paragraphId = payload.paragraphParentId
        delete payload.paragraphParentId
      }
      if (hasOwnProp(payload, 'documentParentId')) {
        payload.documentId = payload.documentParentId
        delete payload.documentParentId
      }
      delete payload.id

      const params = {}
      if (data.notifyReviewer === true) {
        params.notify_reviewer = true
      }

      if (data.forwardTagsStatement === true) {
        params.forward_tags_statement = true
      }

      return dpApi({
        method: 'PATCH',
        url: Routing.generate('dplan_api_statement_fragment_edit', {
          statementFragmentId: data.id,
          include: [
            'statement',
            'department',
            'tags',
            'counties',
            'municipalities',
            'priorityAreas',
            'element',
            'paragraph',
            'document',
            'assignee',
            'lastClaimedUser'
          ].join(),
          ...params
        }),
        data: {
          data: {
            type: 'StatementFragment',
            id: data.id,
            attributes: payload
          }
        },
        headers: {
          'Content-type': 'application/vnd.api+json',
          Accept: 'application/vnd.api+json'
        }
      })
        .then(this.api.checkResponse)
        .then(response => {
          const dataToUpdate = {}
          const responseAttributes = response.data.attributes
          const responseRelationships = response.data.relationships

          // If we update element/paragraph/document we have only id in data and we want to update title too so we set it as data field to get the value from response in the loop below
          if (hasOwnProp(data, 'elementId')) {
            data.elementTitle = ''
          }
          if (hasOwnProp(data, 'paragraphParentId')) {
            data.paragraphParentTitle = ''
          }
          if (hasOwnProp(data, 'documentParentId')) {
            data.documentParentTitle = ''
          }

          //  Loop over data (a.k.a. what is passed from component to be saved)
          for (const field in data) {
            if (hasOwnProp(data, field) === false) {
              continue
            }

            //  Do only update the saved property that may be found in attributes or relationships
            if (hasOwnProp(responseAttributes, field)) {
              dataToUpdate[field] = responseAttributes[field]
            } else if (responseRelationships && hasOwnProp(responseRelationships, field)) {
              const responseField = responseRelationships[field].data

              //  Updated field can either hold one value (EditFieldSelect) or an array of values (EditFieldSelect)
              if (hasOwnProp(responseField, 'id')) {
                dataToUpdate[field] = responseField.id
              } else {
                dataToUpdate[field] = responseField
              }
            } else {
              dataToUpdate[field] = data[field]
            }
          }

          /*
           *  If paragraph was deleted by choosing an element without paragraphs,
           *  there is no field `paragraph` in responseRelationships, so it has to be updated manually
           */
          if (dataToUpdate.elementId && data.paragraphParentId === '') {
            dataToUpdate.paragraphParentId = ''
            dataToUpdate.paragraphParentTitle = ''
          }
          // The same with files
          if (dataToUpdate.elementId && data.documentParentId === '') {
            dataToUpdate.documentParentId = ''
            dataToUpdate.documentParentTitle = ''
          }

          //  If element was deleted, there is no field `deleted` in responseRelationships, so it has to be updated manually
          if (hasOwnProp(data, 'elementId') && data.elementId === '') {
            dataToUpdate.elementId = ''
          }

          // If the reviewer has been set, update fragment assignment
          if (hasOwnProp(data, 'departmentId')) {
            if (hasOwnProp(data, 'lastClaimed') && responseRelationships.lastClaimedUser?.data) {
              dataToUpdate.lastClaimedUserId = responseRelationships.lastClaimedUser.data.id
            }

            dataToUpdate.departmentId = responseRelationships.department?.data ? responseRelationships.department.data.id : ''

            if (dataToUpdate.departmentId) { // If departmentId is in response and is not null
              // we reset the assignee with the values from BE
              if (responseRelationships.assignee?.data) {
                const newAssigneeId = responseRelationships.assignee.data.id
                const newAssignee = response.included.find(elem => elem.type === 'User' && elem.id === newAssigneeId)
                const orgaId = newAssignee.relationships.orga.data.id

                dataToUpdate.assignee = {
                  id: newAssigneeId,
                  uId: newAssigneeId,
                  name: newAssignee.attributes.fullName,
                  orgaName: response.included(elem => elem.type === 'Orga' && elem.id === orgaId).attributes.name
                }
                // If assignee is not sent from BE assignee is probably null, so we reset the assignment with empty object
              } else {
                dataToUpdate.assignee = { id: '', uId: '', orgaName: '', name: '' }
              }
            }
          }

          // If there is ne assignee we don't want an empty Object, so we build the object with all the props we need
          if (hasOwnProp(data, 'assignee')) {
            if (hasOwnProp(data.assignee, 'id') === false) {
              dataToUpdate.assignee = { id: '', name: '', orgaName: '', uId: '' }
            }
          }

          //  Keep id to find fragment in mutation
          dataToUpdate.fragmentId = response.data.id
          dataToUpdate.statementId = responseRelationships.statement.data.id

          //  Update store
          commit('updateFragment', { ...dataToUpdate })

          //  Use this in Fragment.saveFragment()
          return dataToUpdate
        })
        .catch(e => {
          console.log('Something happened', e)
          dplan.notify.error(Translator.trans('error.api.generic'))
          return e
        })
    }
  },

  getters: {
    /**
     *
     * @param {String} statementId
     * @param {String} fragmentId
     */
    fragmentById: state => (statementId, fragmentId) => {
      return state.fragments[statementId].fragments.find(frag => frag.id === fragmentId)
    },

    /**
     *
     * @param {String} statementId
     */
    fragmentsByStatement: state => statementId => {
      return state.fragments[statementId] || { fragments: [], filteredFragments: [], statement: [] }
    },

    selectedFragments: state => state.selectedFragments,

    selectedFragmentsLength: state => Object.keys(state.selectedFragments).length
  }
}
