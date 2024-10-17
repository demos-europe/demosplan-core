/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { dpApi, handleResponseMessages, hasAnyPermissions, hasOwnProp } from '@demos-europe/demosplan-ui'

/**
 * Adds empty title attribute for element/paragraph/document
 * @param data {Object}
 */
function addTitleAttr (data) {
  const hasElement = data.data.relationships?.elements && Object.keys(data.data.relationships.elements.data).length
  let titleAttrs = {}

  if (hasElement) {
    titleAttrs = {
      elementId: data.data.relationships.elements.data.id,
      elementTitle: ''
    }
  }

  if (data.data.relationships?.paragraph) {
    titleAttrs.paragraphTitle = ''
  }
  if (data.data.relationships?.document) {
    titleAttrs.documentTitle = ''
  }

  return titleAttrs
}

/**
 * Adds empty id attributes (paragraphParentId, documentParentId, elementId) for element/paragraph/document
 * @param dataToUpdate {Object}
 * @param data {Object}
 */
function addIdAttr (dataToUpdate, data) {
  const idAttrs = {}
  if (dataToUpdate.elementId && data.data.relationships.paragraph.data === null) {
    idAttrs.paragraphParentId = ''
  }

  if (dataToUpdate.elementId && data.data.relationships.document.data === null) {
    idAttrs.documentParentId = ''
  }

  //  If element was deleted, there is no field `deleted` in responseRelationships, so it has to be updated manually
  if (data.data.relationships?.elements && data.data.relationships.elements?.data === null) {
    idAttrs.elementId = ''
  }

  return idAttrs
}

/**
 *
 * @param response {Object} data from BE
 * @param dataToUpdate {Object} empty object
 * @param updatedData {Object} local data
 */
function getDataFromResponse (response, dataToUpdate, updatedData) {
  const responseAttributes = response.data.attributes
  const responseRelationships = response.data.relationships

  for (const field in updatedData) {
    if (hasOwnProp(updatedData, field) === false) {
      continue
    }

    //  Do only update the saved property that may be found in attributes or relationships
    if (hasOwnProp(responseAttributes, field)) {
      dataToUpdate[field] = responseAttributes[field]
    } else if (responseRelationships && hasOwnProp(responseRelationships, field) && field !== 'elements') {
      const responseField = responseRelationships[field].data

      //  Updated field can either hold one value (EditFieldSelect) or an array of values (EditFieldMultiSelect)
      if (hasOwnProp(responseField, 'id')) {
        dataToUpdate[field] = responseField.id
      } else {
        dataToUpdate[field] = responseField
      }
    }
  }

  return dataToUpdate
}

function setUpdatedProps (data) {
  // If we update element/paragraph/document we want to update title too, so we set it as attribute to get the value from response in the loop below
  data.data.attributes = {
    ...data.data.attributes,
    ...addTitleAttr(data)
  }

  //  Loop over data (a.k.a. what is passed from component to be saved)
  let updatedData = {}
  if (data.data.attributes) {
    updatedData = {
      ...data.data.attributes
    }
  }

  if (data.data.relationships) {
    updatedData = {
      ...updatedData,
      ...data.data.relationships
    }
  }

  return updatedData
}

/**
 *
 * @param {Object} el
 * @param {Array} includes
 * @param {Object} meta
 */
function transformStatementStructure ({ el, includes, meta }) {
  // Map attributes to match the old structure/naming
  const statement = el.attributes
  statement.id = el.id
  statement.clusterName = statement.name || ''
  statement.fragments = statement.fragments || []
  statement.fragmentsElements = statement.fragmentsElements || []
  statement.fragmentsTotal = statement.fragmentsCount || 0
  statement.files = statement.files || []
  statement.attachments = statement.attachments || []
  statement.sourceAttachment = statement.sourceAttachment || ''
  statement.initialFilteredFragmentsCount = statement.filteredFragmentsCount || 0 // This Information is missing from BE-Side by now
  statement.phase = Translator.trans(statement.phase)
  statement.isFiltered = meta.isFiltered || false
  statement.orgaName = statement.organisationName
  statement.orgaDepartmentName = statement.organisationDepartmentName

  if (hasOwnProp(el, 'relationships')) {
    const relationships = Object.keys(el.relationships)

    // Get the data for the relationships and put it into the statement-element
    relationships.forEach(relationKey => {
      const relation = el.relationships[relationKey]
      // For 1-n Relations
      if (relation.data instanceof Array) {
        if (relation.data.length > 0) {
          const ids = relation.data.map(id => id.id)
          const type = relation.data[0].type

          statement[relationKey] = includes.filter(incl => ids.includes(incl.id) && type === incl.type)
          statement[relationKey] = statement[relationKey].map(statementRel => Object.assign(statementRel.attributes, { id: statementRel.id }))

          if (type === 'StatementAttachment' && hasOwnProp(statement[relationKey][0], 'id')) {
            const attachment = includes
              .filter(incl => incl.type === 'StatementAttachment')
              .filter(incl => statement[relationKey][0].id === incl.id)

            if (hasOwnProp(attachment[0], 'relationships')) {
              const sourceAttachment = includes
                .filter(incl => incl.type === 'File')
                .filter(incl => attachment[0].relationships.file.data.id === incl.id)
                .map(sourceAtt => Object.assign(sourceAtt.attributes, { id: sourceAtt.id }))

              statement.sourceAttachment = sourceAttachment[0]
            } else {
              statement.sourceAttachment = undefined
            }
          }
        } else {
          statement[relationKey] = []
        }

        return
      }
      // For 1-1 relations
      if (relation.data instanceof Object) {
        if (hasOwnProp(relation.data, 'id')) {
          const id = relation.data.id
          const type = relation.data.type
          statement[relationKey] = includes.find(incl => id === incl.id && type === incl.type)
          statement[relationKey] = Object.assign(statement[relationKey].attributes, { id: statement[relationKey].id })
        } else {
          statement[relationKey] = null
        }
      }
    })
  }

  return statement
}

function prepareStatement ({ el, includes, meta }) {
  const statement = transformStatementStructure({ el, includes, meta })
  return setStatementAssignee(statement)
}

function setStatementAssignee (statement) {
  // If there is no assignee we don't want an empty Object, so we build the object with all the props we need
  if (hasOwnProp(statement, 'assignee')) {
    if (hasOwnProp(statement.assignee, 'id') === false) {
      statement.assignee = { id: '', name: '', orgaName: '', uId: '' }
    }
  } else {
    statement.assignee = { id: '', name: '', orgaName: '', uId: '' }
  }

  return statement
}
export default {
  namespaced: true,

  name: 'Statement',

  state: {
    filterHash: '',
    statements: {},
    procedureId: '',
    selectedElements: {},
    pagination: {},
    persistStatementSelection: true,
    initStatements: [],
    statementGrouping: {}
  },

  mutations: {
    /**
     * @param {Object} element
     */
    addElementToSelection (state, element) {
      state.selectedElements[element.id] = element
    },

    /**
     * Add statement to store
     * @param {Object} statement
     */
    addStatement (state, statement) {
      // If there is no assignee we don't want an empty Object, so we build the object with all the props we need
      if (hasOwnProp(statement, 'assignee')) {
        if (hasOwnProp(statement.assignee, 'id') === false) {
          statement.assignee = { id: '', name: '', orgaName: '', uId: '' }
        }
      } else {
        statement.assignee = { id: '', name: '', orgaName: '', uId: '' }
      }

      if (hasOwnProp(state.selectedElements, statement.id) && state.persistStatementSelection) {
        const selectedEntries = JSON.parse(sessionStorage.getItem('selectedElements')) || {}
        selectedEntries[state.procedureId][statement.id].assignee = statement.assignee
        sessionStorage.setItem('selectedElements', JSON.stringify(selectedEntries))
      }

      state.statements = { ...state.statements, ...{ [statement.id]: statement } }
    },

    /**
     * @param {String} elementId
     */
    removeElementFromSelection (state, elementId) {
      delete state.selectedElements[elementId]
    },

    /**
     * Remove statement from store
     * @param {String} statementId
     */
    removeStatement (state, statementId) {
      delete state.statements[statementId]
    },

    /**
     *
     * @param {Object} elements
     */
    replaceElementSelection (state, elements) {
      state.selectedElements = elements
      const selectedEntries = JSON.parse(sessionStorage.getItem('selectedElements'))

      if (hasOwnProp(selectedEntries, state.procedureId) && state.persistStatementSelection) {
        selectedEntries[state.procedureId] = elements
        sessionStorage.setItem('selectedElements', JSON.stringify(selectedEntries))
      }
    },

    /**
     *
     * @param {String} oldStatementId
     * @param {Object} newStatement
     */
    replaceStatement (state, oldStatementId, newStatement) {
      state.statements[oldStatementId] = newStatement
    },

    resetSelection (state) {
      state.selectedElements = {}
    },

    resetStatements (state) {
      state.statements = {}
    },

    /**
     * @param value
     */
    setFilteredState (state, value) {
      state.isFiltered = value
    },

    /**
     * @param {Object} data
     */
    setInitStatements (state, data) {
      state.initStatements = data
    },

    /**
     * @param {String} value
     */
    setProcedureId (state, value) {
      state.procedureId = value
    },

    setSelectedElements (state, elements) {
      state.selectedElements = elements
    },

    /**
     * Set grouping of statements as displayed in the TOC
     */
    setStatementGrouping (state, grouping) {
      state.statementGrouping = grouping
    },

    setStatements (state, statements) {
      state.statements = statements
    },

    /**
     * @param value
     */

    updateFilterHash (state, value) {
      set(state, 'filterHash', value)
    },

    updatePagination (state, value) {
      state.pagination = Object.assign(state.pagination, value)
    },

    updatePersistStatementSelection (state, value) {
      state.persistStatementSelection = value
    },

    /**
     *
     * @param {Object} data
     */
    updateStatement (state, data) {
      //  Reject if no statement id is found in data
      if (hasOwnProp(data, 'id') === false) {
        return console.warn('statement/updateStatement expects to be called with data.id')
      }

      // If assignee was changed and statement is selected, we have to update selectedElements in store and sessionStorage
      if (hasOwnProp(data, 'assignee') && hasOwnProp(state.selectedElements, data.id)) {
        state.selectedElements[data.id].assignee = data.assignee
        state.selectedElements = { ...state.selectedElements }

        if (state.persistStatementSelection) {
          const selectedEntries = JSON.parse(sessionStorage.getItem('selectedElements')) || {}
          selectedEntries[state.procedureId][data.id].assignee = data.assigne
          sessionStorage.setItem('selectedElements', JSON.stringify(selectedEntries))
        }
      }

      //  Return early if no statements are found
      if (hasOwnProp(state.statements, data.id) === false) {
        return console.warn('statement/updateStatement could not find the requested statement')
      }

      /**
       * For reactivity reasons, we should merge the updated statement into statements and then reassign the whole statements object.
       */
      const newStatement = { [data.id]: { ...state.statements[data.id], ...data } }
      state.statements = { ...state.statements, ...newStatement }
    }
  },

  actions: {
    /**
     * Add an element to selectedElements and the sessionStorage
     * @param data : {Object} contains id, editable
     */
    addToSelectionAction ({ state, commit }, data) {
      performance.mark('selection-start')
      const selectedEntries = JSON.parse(sessionStorage.getItem('selectedElements')) || {}

      if (hasOwnProp(selectedEntries, state.procedureId) === false) {
        selectedEntries[state.procedureId] = {}
      }

      selectedEntries[state.procedureId][data.id] = { ...data }

      if (state.persistStatementSelection) {
        sessionStorage.setItem('selectedElements', JSON.stringify(selectedEntries))
      }
      commit('addElementToSelection', data)
      performance.mark('selection-end')
      performance.measure('selection-duration', 'selection-start', 'selection-end')
      return Promise.resolve(true)
    },

    /**
     *
     * @param {Object} data
     */
    copyStatementAction ({ state }, data) {
      return dpApi({
        method: 'POST',
        url: Routing.generate('dplan_api_statement_copy_to_procedure', {
          procedureId: state.procedureId,
          statementId: data.statementId,
          targetProcedureId: data.procedureId
        })
      })
        .then(this.api.checkResponse)
        .then(response => response)
    },

    /**
     *
     * @param {Object} data
     */
    createClusterAction ({ commit, dispatch, state }, data) {
      return dpApi({
        method: 'POST',
        url: Routing.generate('dplan_api_create_group_statement', {
          procedureId: state.procedureId
        }),
        data: { data },
        headers: {
          'Content-type': 'application/vnd.api+json',
          Accept: 'application/vnd.api+json'
        }
      })
        .then(this.api.checkResponse)
        .then(response => {
          dispatch('resetSelection') // The selected statements were deleted, so we can completely reset selection

          // delete all selected statements from store and sessionStorage because they are now in cluster
          data.relationships.statements.data.forEach(stn => dispatch('removeStatementAction', stn.id))

          // Transform newCluster from BE from JSON:API structure into our old structure
          const transformedStatement = transformStatementStructure({ el: response.data, includes: response.included, meta: response.meta })

          // Add new cluster to store with addStatement mutation
          commit('addStatement', transformedStatement)

          return response
        })
        .catch(e => {
          if (e.response && e.response.data && hasOwnProp(e.response.data, 'meta') && hasOwnProp(e.response.data.meta, 'messages')) {
            handleResponseMessages(e.response.data.meta)
          }
          return e
        })
    },

    /**
     * Get statements
     * attachments are `Originalstellungnahme-Anhang` and can be only one file
     * files are `weitere Anhänge`
     * @param {Object} data
     */
    getStatementAction ({ commit, state, rootState }, data) {
      const includes = [
        'elements',
        'paragraph',
        'document',
        'assignee',
        'attachments',
        'attachments.file',
        'files'
      ]

      /*
       * `tags`, `isSubmittedByCitizen`, `priorityAreas`, `counties` and `municipalities`
       * are available and readable with one of the following permissions
       */
      const statementFields = []
      const fields = {}

      if (hasAnyPermissions(['feature_segments_of_statement_list', 'area_statement_segmentation', 'area_admin_statement_list', 'area_admin_submitters'])) {
        statementFields.push('isSubmittedByCitizen')
      }

      if (hasPermission('field_statement_priority_area') && data.hasPriorityArea === true) {
        includes.push('priorityAreas')
        statementFields.push('priorityAreas')
        fields.PriorityArea = 'name'
      }

      if (hasPermission('field_statement_county')) {
        includes.push('counties')
        statementFields.push('counties')
        fields.County = 'name'
      }

      if (hasAnyPermissions(['field_statement_municipality', 'area_admin_assessmenttable'])) {
        includes.push('municipalities')
        statementFields.push('municipalities')
        fields.Municipality = 'name'
      }

      if (hasAnyPermissions(['feature_json_api_tag', 'area_statement_segmentation', 'feature_statements_tag'])) {
        includes.push('tags')
        statementFields.push('tags')
        fields.Tag = 'title'
      }

      return dpApi({
        method: 'GET',
        // @improve T12984
        url: Routing.generate('dplan_assessmentqueryhash_get_procedure_statement_list', {
          procedureId: data.procedureId,
          filterSetHash: data.filterHash,
          page: {
            number: data.pagination.current_page,
            size: data.pagination.count
          },
          view_mode: rootState.AssessmentTable.viewMode,
          sort: data.sort,
          // Size: data.pagination.size,
          fields: {
            ...fields,
            Statement: [
              ...statementFields,
              'anonymous',
              'assignee',
              'attachments',
              'authoredDate',
              'authorName',
              'document',
              'documentParentId',
              'elementId',
              'elements',
              'externId',
              'files',
              'filteredFragmentsCount',
              'formerExternId',
              'fragmentsCount',
              'fragmentsElements',
              'initialOrganisationDepartmentName',
              'initialOrganisationName',
              'isCluster',
              'likesNum',
              'movedStatementId',
              'movedFromProcedureId',
              'movedFromProcedureName',
              'movedToProcedureId',
              'movedToProcedureName',
              'name',
              'originalId',
              'paragraph',
              'paragraphParentId',
              'parentId',
              'phase',
              'polygon',
              'priority',
              'procedureId',
              'publicVerified',
              'publicVerifiedTranslation',
              'recommendation',
              'recommendationIsTruncated',
              'status',
              'submitDate',
              'submitName',
              'text',
              'textIsTruncated',
              'userGroup',
              'userOrganisation',
              'userPosition',
              'userState',
              'votePla',
              'votesNum',
              'voteStk'
            ].join(),
            Claim: [
              'name',
              'orgaName'
            ].join(),
            Elements: 'title',
            File: [
              'filename',
              'hash'
            ].join(),
            StatementFragmentsElements: [
              'elementTitle',
              'paragraphTitle'
            ].join(),
            SingleDocument: [
              'parentId',
              'title'
            ].join(),
            StatementAttachment: [
              'file',
              'attachmentType'
            ].join()
          },
          include: includes.join(',')
        })
      })
        .then(this.api.checkResponse)
        .then(response => {
          performance.mark('start')
          commit('updatePagination', response.meta.pagination)
          commit('resetStatements')
          commit('setFilteredState', response.meta.isFiltered)
          commit('setInitStatements', response.meta.statementAssignments)
          commit('setStatementGrouping', response.meta.grouping)
          commit('updateFilterHash', response.meta.filterHash)
          const refinedStatements = {}
          const sessionStorageUpdates = {}

          response.data.forEach(statement => {
            const transformedStatement = prepareStatement({ el: statement, includes: response.included, meta: response.meta })

            if (hasOwnProp(state.selectedElements, transformedStatement.id)) {
              sessionStorageUpdates[transformedStatement.id] = transformedStatement
            }

            refinedStatements[transformedStatement.id] = transformedStatement
          })

          if (state.persistStatementSelection) {
            const selectedEntries = JSON.parse(sessionStorage.getItem('selectedElements')) || {}
            selectedEntries[state.procedureId] = { ...selectedEntries[state.procedureId], ...sessionStorageUpdates }
            sessionStorage.setItem('selectedElements', JSON.stringify(selectedEntries))
          }

          commit('setStatements', refinedStatements)

          performance.mark('end')
          performance.measure('dur', 'start', 'end')

          return response
        })
        .catch(e => {
          console.error(e)
          dplan.notify.error(Translator.trans('error.api.generic'))
          return {}
        })
    },

    /**
     *
     * @param {Object} data
     */
    moveStatementAction ({ state }, data) {
      return dpApi({
        method: 'POST',
        url: Routing.generate('dplan_api_statement_move', {
          procedureId: state.procedureId,
          statementId: data.statementId,
          targetProcedureId: data.procedureId
        }),
        data: {
          deleteVersionHistory: data.deleteVersionHistory
        }
      })
        .then(this.api.checkResponse)
        .then(response => response)
    },

    /**
     * Remove one element from the selectedElements-List and the sessionStorage
     * @param {String} id
     */
    removeFromSelectionAction ({ state, commit }, id) {
      const selectedEntries = JSON.parse(sessionStorage.getItem('selectedElements')) || {}

      if (hasOwnProp(selectedEntries, state.procedureId)) {
        delete selectedEntries[state.procedureId][id]
        sessionStorage.setItem('selectedElements', JSON.stringify(selectedEntries))
      }
      commit('removeElementFromSelection', id)
      return Promise.resolve(true)
    },

    removeStatementAction ({ state, commit, dispatch }, statementId) {
      commit('removeStatement', statementId)
      if (hasOwnProp(state.selectedElements, statementId)) {
        dispatch('removeFromSelectionAction', statementId)
      }
    },

    /**
     *
     * @param {String} oldStatementId
     * @param {Object} newStatement
     */
    replaceStatementAction ({ state, commit, dispatch }, oldStatementId, newStatement) {
      commit('replaceStatement', { oldStatementId, newStatement })
      if (hasOwnProp(state.selectedElements, oldStatementId)) {
        dispatch('removeFromSelectionAction', oldStatementId)
        dispatch('addToSelectionAction', newStatement)
      }
    },

    /**
     * Remove all "selected" elements related to current procedureID from sessionStorage
     * and from store (state.selectedElements)
     */
    resetSelection ({ state, commit }) {
      const sessionStore = JSON.parse(sessionStorage.getItem('selectedElements'))
      if (sessionStore) {
        delete sessionStore[state.procedureId]
        sessionStorage.setItem('selectedElements', JSON.stringify(sessionStore))
      }
      commit('resetSelection')
      return true
    },

    /**
     * Update the assignment status of the entity
     * The user passed as parameter is set as new assignee. If we want to unassign the statement, userId should be an empty string
     * @param {String} statementId
     * @param {String} assigneeId
     */
    setAssigneeAction ({ commit }, { statementId, assigneeId }) {
      return dpApi({
        method: 'PATCH',
        url: Routing.generate('dplan_claim_statements_api', { statementId: statementId }),
        data: {
          data: {
            type: 'user',
            id: assigneeId
          }
        },
        headers: {
          'Content-type': 'application/vnd.api+json',
          Accept: 'application/vnd.api+json'
        }
      })
        .then(this.api.checkResponse)
        .then(response => {
          let assignee = {}
          if (assigneeId === '' || assigneeId == null) {
            assignee = { id: '', name: '', orgaName: '', uId: '' }
            commit('updateStatement', { id: statementId, assignee })
            return { id: statementId, assignee }
          } else {
            assignee = { id: response.data.id, uId: response.data.id, name: response.data.attributes.name, orgaName: response.data.attributes.orgaName }
            commit('updateStatement', { id: statementId, assignee })
            return { id: statementId, assignee }
          }
        })
    },

    setSelectionAction ({ state, commit }, { status, statements }) {
      let selectedElements = {}
      let currentSelection = {}

      if (state.persistStatementSelection) {
        selectedElements = JSON.parse(sessionStorage.getItem('selectedElements')) || {}
      }

      if (hasOwnProp(selectedElements, state.procedureId)) {
        currentSelection = selectedElements[state.procedureId]
      }

      if (status === true) {
        currentSelection = { ...currentSelection, ...statements }
      }

      if (status === false) {
        for (const statementId in statements) {
          delete currentSelection[statementId]
        }
      }

      if (state.persistStatementSelection) {
        selectedElements[state.procedureId] = currentSelection
        sessionStorage.setItem('selectedElements', JSON.stringify(selectedElements))
      }

      commit('setSelectedElements', currentSelection)

      return Promise.resolve(true)
    },

    /**
     *
     * @param {String} procedureId
     */
    setProcedureIdAction ({ commit }, procedureId) {
      commit('setProcedureId', procedureId)
      return Promise.resolve(true)
    },

    /**
     * Get all selected elements that are related to the current procedureId from sessionStorage
     * and set them in the store (state.selectedElements)
     *
     */
    setSelectedElementsAction ({ state, commit }) {
      const selectedItems = JSON.parse(sessionStorage.getItem('selectedElements')) || {}
      const updatedSelection = {}

      // We dont have to do it if there are no items for this procedure
      if (hasOwnProp(selectedItems, state.procedureId)) {
        for (const itemId in selectedItems[state.procedureId]) {
          const item = selectedItems[state.procedureId][itemId]

          // First we look for the stn in initStatements, where we have current assignees
          const initStatement = state.initStatements.find(stn => stn.id === itemId)

          // If initStatements are not loaded, the item should be added to selection; statements not in initStatements were probably deleted and should not be added to selection
          if (state.initStatements.length === 0 || typeof initStatement !== 'undefined') {
            // Update assignee info
            if (initStatement && hasOwnProp(initStatement, 'assigneeId')) {
              item.assignee = { id: initStatement.assigneeId ? initStatement.assigneeId : '' }
            }
            // Hidden elements are needed for submit form actions in ATabelle, to submit also the selected elements from other pages (if the element is on other page, the hiddenElement is true). it is used in selectedElementsFromOtherPages getter and then in assessment_table_view.twig
            item.hiddenElement = hasOwnProp(state.statements, itemId) === false

            // Add updated item to selection
            updatedSelection[itemId] = item
          }
        }

        selectedItems[state.procedureId] = updatedSelection
        sessionStorage.setItem('selectedElements', JSON.stringify(selectedItems))
        commit('setSelectedElements', updatedSelection)
      }
    },

    /**
     *
     * @param {Object} data
     */
    updateStatementAction ({ commit, state }, data) {
      const payload = JSON.parse(JSON.stringify(data))

      //  Reject if no statement id is found in data
      if (hasOwnProp(data.data, 'id') === false) {
        return Promise.reject(new Error('statement/updateStatementAction expects to be called with data.id'))
      }

      return dpApi({
        method: 'POST',
        url: Routing.generate('dplan_api_statement_edit', {
          statementId: data.data.id,
          procedureId: state.procedureId,
          include: [
            'assignee',
            'attachments',
            'attachments.file',
            'counties',
            'document',
            'elements',
            'files',
            'fragmentsElements',
            'municipalities',
            'paragraph',
            'priorityAreas',
            'tags'
          ].join(',')
        }),
        data: payload,
        headers: {
          'Content-type': 'application/json'
        }
      })
        .then(this.api.checkResponse)
        .then(response => {
          let dataToUpdate = {}
          const updatedData = setUpdatedProps(data)
          dataToUpdate = getDataFromResponse(response, dataToUpdate, updatedData)

          /*
           * If paragraph or file was deleted by choosing an element without paragraphs or file,
           * there is no field `paragraph` or `file` in responseRelationships, so it has to be updated manually
           * If element was removed, it also needs to be set here
           */

          dataToUpdate = {
            ...dataToUpdate,
            ...addIdAttr(dataToUpdate, data)
          }

          //  Keep id to find statement in mutation
          dataToUpdate.id = response.data.id

          commit('updateStatement', { ...dataToUpdate })

          //  Use this in TableCard.saveStatement()
          return dataToUpdate
        })
        .catch(err => {
          dplan.notify.error(Translator.trans('statement.change.failed'))
          return err
        })
    },

    /**
     *
     * @param {Object} data
     */
    updateClusterAction ({ commit, dispatch, state }, data) {
      return dpApi({
        method: 'PATCH',
        url: Routing.generate('dplan_api_update_group_statement', {
          procedureId: state.procedureId
        }),
        data: { data },
        headers: {
          'Content-type': 'application/vnd.api+json',
          Accept: 'application/vnd.api+json'
        }
      })
        .then(this.api.checkResponse)
        .then(response => {
          dispatch('resetSelection')

          // Delete all selected statements from store (except for the headStatement) because they are now in cluster
          data.relationships.statements.data.filter(stn => stn.id !== data.attributes.headStatementId).forEach(stn => dispatch('removeStatementAction', stn.id))

          commit('updateStatement', { id: response.data.attributes.id, isCluster: true })
          return response
        })
        .catch(e => {
          if (e.response && e.response.data && hasOwnProp(e.response.data, 'meta') && hasOwnProp(e.response.data.meta, 'messages')) {
            handleResponseMessages(e.response.data.meta)
          }
          return e
        })
    }
  },

  getters: {
    assigneeByStatementId: state => id => {
      return state.included.find(el => el.id === id && el.type === 'Claim') || { id: '', name: '', orgaName: '' }
    },

    elementByStatementId: state => id => {
      return state.included.filter(el => el.id === id && el.type === 'Element') || {}
    },

    filesByStatementId: state => id => {
      return state.included.filter(el => el.id === id && el.type === 'File') || []
    },

    getIncluded: state => data => {
      return state.included.find(el => el.id === data.id && el.type === data.type) || {}
    },

    getSelectionStateById: state => statementId => {
      return !!state.selectedElements[statementId]
    },

    getToc: state => state.statementGrouping,

    selectedElements: state => state.selectedElements,

    /**
     * Filter all elements that are selected but not on this page
     * so we can create a list of hidden Elements.
     * @returns array | [{id,editable}]
     */
    selectedElementsFromOtherPages: state => {
      const selectedItems = Object.keys(state.selectedElements).filter(statement => {
        return (state.selectedElements[statement].hiddenElement === true)
      })

      const elementsFromOtherPages = {}
      for (const itemId in selectedItems) {
        if (hasOwnProp(state.statements, selectedItems[itemId]) === false) {
          elementsFromOtherPages[selectedItems[itemId]] = state.selectedElements[selectedItems[itemId]]
        }
      }
      return elementsFromOtherPages
    },

    selectedElementsLength: state => {
      return Object.keys(state.selectedElements).length || 0
    },

    statements: state => state.statements,

    statementById: state => id => {
      return state.statements[id] || { meta: { state: 'not loaded' } }
    },

    statementsInOrder: state => ids => {
      return ids.map(id => state.statements[id])
    }
  }

}
