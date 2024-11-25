/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { dpApi, hasOwnProp } from '@demos-europe/demosplan-ui'

const AssessmentTable = {
  namespaced: true,

  name: 'AssessmentTable',

  state: {
    accessibleProcedureIds: [],
    assessmentBase: {
      appliedFilters: [],
      accessibleProcedures: {},
      adviceValues: {},
      agencies: {},
      counties: [],
      /**
       * Which state (a.k.a. 'Ansicht') should be toggled by default when loading the assessment table?
       * This value is set in parameters_default.yml as `assessment_table_default_toggle_view`.
       * Possible values are 'statement', 'collapsed'and 'fragments' (if fragments are available in a project), where
       * 'statement' = expanded statements, 'collapsed' = collapsed statements and fragments = expanded fragments
       */
      defaultToggleView: '',
      documents: {},
      elements: [],
      fragmentStatus: {},
      inaccessibleProcedures: {},
      initFilterHash: '',
      municipalities: [],
      paragraph: {},
      priorities: {},
      priorityAreas: [],
      procedureId: '',
      searchFields: [],
      status: {},
      tags: [],
      internalPhases: {},
      externalPhases: {}
    },
    assessmentBaseLoaded: false,
    boilerPlates: [],
    /**
     * Currently selected view mode
     * Possible values are 'statement', 'collapsed' and 'fragments' (if fragments are available in a project), where
     * 'statement' = expanded statements, 'collapsed' = collapsed statements and fragments = expanded fragments
     */
    currentTableView: '',
    currentUserId: '',
    currentUserName: '',
    exactSearch: false,
    filterSet: {},
    isLoading: true,
    isRefreshButtonVisible: false,
    modals: {
      /**
       * Visibility state and data to be displayed in the AssignEntityModal; contains show, entityId, entityType
       * ('statement' or 'fragment'), initialAssigneeId, and parentStatementId
       */
      assignEntityModal: {
        show: false
      },
      // Visibility state of ConsolidateModal
      consolidateModal: {
        show: false
      },
      // Visibility state of CopyStatementModal and statementId
      copyStatementModal: {
        show: false,
        statementId: null
      }
    },
    procedureStatementPriorityArea: false,
    publicParticipationPublicationEnabled: false,
    searchTerm: '',
    showFilterModal: false,
    showSearchModal: false,
    sort: '',
    statementFormDefinitions: {},
    viewMode: ''
  },

  mutations: {
    /**
     *
     * @param data
     */
    addBase (state, data) {
      state.assessmentBase = data
      state.assessmentBaseLoaded = true
    },

    /**
     *
     * @param data
     */
    addBoilerPlates (state, data) {
      state.boilerPlates = data
    },

    /**
     *
     * @param data
     */
    addOptionToProperty (state, data) {
      if (Array.isArray(state.assessmentBase[data.prop])) {
        state.assessmentBase[data.prop].unshift(data.value)
      } else if (typeof state.assessmentBase[data.prop] === 'object' && data.prop !== 'paragraph') {
        state.assessmentBase[data.prop][data.value.key] = data.value.title
      } else if (data.prop === 'paragraph') { // To paragraphs the empty option has to be passed differently because of the specific structure of data
        Object.entries(state.assessmentBase.paragraph).forEach(element => {
          element.forEach(array => {
            if (Array.isArray(array)) {
              array.unshift(data.value)
            }
          })
        })
      }
    },

    setRefreshButtonVisibility (state, isVisible) {
      state.isRefreshButtonVisible = isVisible
    },

    /*
     *  Cant use assessmentTable.getBaseData in the "Fachbehörde"-Views since this requires a procedureId
     *  which is not present there. So we have to work around this by manually setting the required values
     *  as props in the root component of those views and passing it to the store with setProperty().
     *
     * @TODO maybe extract stuff that is not related to a procedure into a more generic route/store
     */
    setAssessmentBaseProperty (state, data) {
      state.assessmentBase[data.prop] = data.val
      state.assessmentBaseLoaded = true
    },

    setModalProperty (state, data) {
      state.modals[data.prop] = data.val
    },

    /**
     *
     * @param data {Object<prop, val>}
     */
    setProperty (state, data) {
      state[data.prop] = data.val
    }
  },

  actions: {
    /**
     * @param {String} procedureId
     */
    async applyBaseData ({ commit, state }, procedureId) {
      const data = await dpApi({
        method: 'GET',
        url: Routing.generate('DemosPlan_assessment_base_ajax', { procedureId })
      })
        .then(this.api.checkResponse)
        .then(response => response.data)

      return new Promise((resolve, reject) => {
        // To prevent invalid type error missmatch of array and object
        if (Array.isArray(data.accessibleProcedures)) {
          data.accessibleProcedures = {}
        }
        // Large arrays that do not need to be reactive (enhances performance)
        const immutable = ['municipalities', 'priorityAreas', 'inaccessibleProcedures', 'accessibleProcedures']
        immutable.forEach(prop => {
          data[prop] = data[prop] && Object.freeze(data[prop])
        })
        data.procedureId = procedureId
        commit('addBase', data)
        commit('setProperty', { prop: 'currentTableView', val: data.defaultToggleView })
        const emptyOptions = ['adviceValues', 'priorities', 'paragraph', 'agencies']
        emptyOptions.forEach(field => commit('addOptionToProperty', { prop: field, value: { key: '', title: '-', name: '-', id: '' } }))

        return resolve(true)
      })
    }
  },

  getters: {
    accessibleProcedures: state => state.assessmentBase.accessibleProcedures,

    appliedFilters: state => state.assessmentBase.appliedFilters,

    assessmentBase: state => state.assessmentBase,

    assessmentBaseLoaded: state => state.assessmentBaseLoaded,

    /**
     * Visibility state and data to be displayed in the AssignEntityModal
     * @return {Object} contains showModal, entityId, entityType ('statement' or 'fragment'), initialAssigneeId, and parentStatementId
     */
    assignEntityModal: state => state.modals.assignEntityModal,

    /**
     *
     * @return {Array}
     */
    adviceValues: state => {
      const statusArray = []
      Object.entries(state.assessmentBase.adviceValues).forEach(
        ([key, value]) => statusArray.push({ id: key, name: Translator.trans(value), title: Translator.trans(value) })
      )
      //  Move empty option to beginning of array so it will be displayed as first option
      const result = statusArray.find(obj => {
        return obj.title === '-'
      })
      if (statusArray.indexOf(result) > 0) {
        statusArray.splice(statusArray.indexOf(result), 1)
        statusArray.splice(0, 0, result)
      }
      return statusArray
    },

    agencies: state => state.assessmentBase.agencies,

    consolidateModal: state => state.modals.consolidateModal,

    copyStatementModal: state => state.modals.copyStatementModal,

    /**
     *
     * @return {Array}
     */
    fragmentReviewer: state => {
      const reviewerArray = []

      for (const key in state.assessmentBase.agencies) {
        if (hasOwnProp(state.assessmentBase.agencies, key)) {
          if (state.assessmentBase.agencies[key] === '-') {
            reviewerArray.push({ id: key, title: '-', name: '-', orgaName: '' })
          } else {
            const title = state.assessmentBase.agencies[key].departmentName
            reviewerArray.push({ id: key, title: `${state.assessmentBase.agencies[key].orgaName}, ${Translator.trans('department')}: ${title}`, name: Translator.trans(title), orgaName: state.assessmentBase.agencies[key].orgaName })
          }
        }
      }
      //  Move empty option to beginning of array so it will be displayed as first option
      const result = reviewerArray.find(obj => {
        return obj.title === '-'
      })
      if (reviewerArray.indexOf(result) > 0) {
        reviewerArray.splice(reviewerArray.indexOf(result), 1)
        reviewerArray.splice(0, 0, result)
      }
      return reviewerArray
    },

    /**
     *
     * @return {Array}
     */
    counties: state => state.assessmentBase.counties,

    /**
     *
     * @return {Object}
     */
    documents: state => state.assessmentBase.documents,

    /**
     *
     * @return {Array}
     */
    elements: state => state.assessmentBase.elements,

    /**
     *
     * @return {Array}
     */
    fragmentStatus: state => {
      const statusArray = []
      for (const key in state.assessmentBase.fragmentStatus) {
        if (hasOwnProp(state.assessmentBase.fragmentStatus, key)) {
          const title = state.assessmentBase.fragmentStatus[key]
          statusArray.push({ id: key, title: Translator.trans(title), name: Translator.trans(title) })
        }
      }
      //  Move empty option to beginning of array so it will be displayed as first option
      const result = statusArray.find(obj => {
        return obj.title === '-'
      })
      if (statusArray.indexOf(result) > 0) {
        statusArray.splice(statusArray.indexOf(result), 1)
        statusArray.splice(0, 0, result)
      }
      return statusArray
    },

    inaccessibleProcedures: state => state.inaccessibleProcedures,

    initFilterHash: state => state.assessmentBase.initFilterHash,

    isLoading: state => state.isLoading,

    isRefreshButtonVisible: state => state.isRefreshButtonVisible,

    /**
     *
     * @return {Array}
     */
    municipalities: state => state.assessmentBase.municipalities,

    /**
     *
     * @return {Object}
     */
    paragraph: state => state.assessmentBase.paragraph,

    /**
     *
     * @return {Array}
     */
    priorities: state => {
      const priorityArray = []
      for (const key in state.assessmentBase.priorities) {
        if (hasOwnProp(state.assessmentBase.priorities, key)) {
          const title = state.assessmentBase.priorities[key]
          priorityArray.push({ id: key, title: Translator.trans(title), name: Translator.trans(title) })
        }
      }
      //  Move empty option to beginning of array so it will be displayed as first option
      const result = priorityArray.find(obj => {
        return obj.title === '-'
      })
      if (priorityArray.indexOf(result) > 0) {
        priorityArray.splice(priorityArray.indexOf(result), 1)
        priorityArray.splice(0, 0, result)
      }
      return priorityArray
    },

    /**
     *
     * @return {string}
     */
    procedureId: state => state.assessmentBase.procedureId,

    searchFields: state => state.assessmentBase.searchFields,

    /**
     *
     * @return {Array}
     */
    status: state => {
      const statusArray = []
      Object.entries(state.assessmentBase.status).forEach(
        ([key, value]) => statusArray.push({ id: key, name: Translator.trans(value), title: Translator.trans(value) })
      )
      //  Move empty option to beginning of array so it will be displayed as first option
      const result = statusArray.find(obj => {
        return obj.title === '-'
      })
      if (statusArray.indexOf(result) > 0) {
        statusArray.splice(statusArray.indexOf(result), 1)
        statusArray.splice(0, 0, result)
      }
      return statusArray
    },

    /**
     *
     * @return {Array}
     */
    priorityAreas: state => state.assessmentBase.priorityAreas,

    /**
     *
     * @return {Array}
     */
    tags: state => {
      const tagsArray = []
      state.assessmentBase.tags.forEach((entry) => {
        const topic = {}
        topic.title = Translator.trans(entry.name)
        if (hasOwnProp(entry, 'tags')) {
          topic.tags = entry.tags
        }
        tagsArray.push(topic)
      })
      return tagsArray
    },

    /**
     *
     * @return {Array}
     */
    procedurePhases: state => data => {
      let phases = []
      if (Object.keys(state.assessmentBase.internalPhases).length > 0 && data.internal) {
        phases = Object.values(state.assessmentBase.internalPhases)
      }
      if (Object.keys(state.assessmentBase.externalPhases).length > 0 && data.external) {
        phases = Object.values(state.assessmentBase.externalPhases)
      }
      return phases
    }
  }

}

export default AssessmentTable
