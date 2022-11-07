/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { checkResponse, dpApi, dpRpc } from '@DemosPlanCoreBundle/plugins/DpApi'
import { transformJsonApiToPi, transformPiToJsonApi } from './storeHelpers/SplitStatementStore/PiTagsToJSONApi'
import { hasOwnProp } from 'demosplan-utils'
import { transformHTMLPositionsToProsemirrorPositions } from './storeHelpers/SplitStatementStore/HTMLIdxToProsemirrorIdx'

const SplitStatementStore = {
  namespaced: true,
  name: 'splitstatement',
  state: {
    assignableUsers: [],
    availablePlaces: [],
    availableTags: [],
    categorizedTags: [],
    currentlyHighlightedSegmentId: null,
    editModeActive: false,
    // Segment currently being edited
    editingSegment: null,
    initialData: null,
    initialSegments: null,
    initText: '',
    // Loading state for save+finish button
    isBusy: false,
    procedureId: '',
    /**
     *If the new selection in editor intersects with existing segments we set recalculatedSegments to
     *currentSegments, but with recalculated ranges. Then later, if a user wants to save it, the
     *state.segments will be replaced by these segments with new range values.
     */
    recalculatedSegments: null,
    segments: [],
    segmentsWithText: null,
    statement: null,
    statementText: null,
    statementId: '',
    tagTopics: [],
    uncategorizedTags: []
  },

  mutations: {
    deleteSegment (state, id) {
      const index = state.segments.findIndex((el) => el.id === id)
      if (index >= 0) {
        Vue.delete(state.segments, index)
      }
    },

    locallyDeleteSegments (state, deletedSegmentIds) {
      const updatedSegments = state.segments.filter(segment => !deletedSegmentIds.includes(segment.id))
      state.segments = updatedSegments
    },

    locallyUpdateSegments (state, updatedSegments) {
      const segments = JSON.parse(JSON.stringify(state.segments))

      // We want to update all segments at once to avoid triggering multiple view updates.
      const segmentsAfterUpdate = segments.map(segment => {
        // Use the updated segment if it was updated. Otherwise, use the old segment.
        const updatedIdx = updatedSegments.findIndex(uSeg => uSeg.id === segment.id)
        const updatedSegment = updatedIdx !== -1 ? updatedSegments[updatedIdx] : segment

        // Remove the updated segment so that we can see later, which segments were created.
        if (updatedIdx !== -1) {
          updatedSegments.splice(updatedIdx, 1)
        }

        return updatedSegment
      })

      // Segments remaining in updated segments are new segments.
      const segmentsToCreate = updatedSegments

      state.segments = segmentsAfterUpdate.concat(segmentsToCreate)
    },

    recalculatePositionsInText (state) {
      // Calculate tiptap positions based on data from PI.
      state.segments = transformHTMLPositionsToProsemirrorPositions(state.segments, state.initText)
    },

    replaceSegment (state, { id, newSegment }) {
      const oldSegmentIndex = state.segments.findIndex((el) => el.id === id)
      if (oldSegmentIndex >= 0) {
        Vue.set(state.segments, oldSegmentIndex, newSegment)
      }
    },

    resetSegments (state) {
      state.segments = state.initialSegments
    },

    setProperty (state, data) {
      Vue.set(state, data.prop, data.val)
    },

    setStatementSegmentDraftList (state, segmentDraftList) {
      Vue.set(state.statement.attributes, 'segmentDraftList', segmentDraftList || null)
    },

    /**
     **
     * Add, replace or delete a property
     * @param data  {Object}  needs this format: { prop: 'propToUpdate', obj: { resourceObject } }
     */
    updateProperty (state, data) {
      const titleIdx = state[data.prop].findIndex(el => el.attributes.title === data.obj.attributes.title)
      const idIdx = state[data.prop].findIndex(el => el.id === data.obj.id)

      // If neither id nor title exist, add element
      if (idIdx < 0 && titleIdx < 0) {
        Vue.set(state[data.prop], state[data.prop].length, data.obj)
      } else if (idIdx < 0 && titleIdx >= 0) {
        // If title exists, but id doesn't, replace element
        Vue.set(state[data.prop], titleIdx, data.obj)
      } else if (idIdx >= 0 && titleIdx >= 0) {
        // If id and title exist, delete element
        Vue.delete(state[data.prop], idIdx)
      }
    }
  },

  actions: {
    acceptSegmentProposal ({ state, commit, dispatch }) {
      const id = state.currentlyHighlightedSegmentId
      if (id) {
        const segment = state.segments.find((el) => el.id === id)
        if (typeof segment !== 'undefined') {
          const segmentCopy = JSON.parse(JSON.stringify(segment))

          // Set segment status to confirmed
          segmentCopy.status = 'confirmed'
          commit('replaceSegment', { id, newSegment: segmentCopy })
          dispatch('saveSegmentsDrafts', false)

          commit('setProperty', { prop: 'currentlyHighlightedSegmentId', val: null })
          commit('setProperty', { prop: 'isBubbleVisible', val: false })
        }
      }
    },

    closeEditMode ({ commit }) {
      commit('setProperty', { prop: 'editModeActive', val: false })
      commit('setProperty', { prop: 'editingSegment', val: null })
    },

    /**
     */
    createTagAction ({ state, commit }, { tag, topicId }) {
      const payload = JSON.parse(JSON.stringify({
        data: {
          type: 'Tag',
          attributes: {
            title: tag.title
          },
          relationships: {
            topic: {
              data: {
                id: topicId,
                type: 'TagTopic'
              }
            }
          }
        }
      }))

      return dpApi.post(Routing.generate('api_resource_create', { resourceType: 'Tag' }), {}, { data: payload.data })
    },

    createTopicAction ({ state, dispatch }, topic) {
      const payload = {
        data: {
          type: 'TagTopic',
          attributes: {
            title: topic.title
          },
          relationships: {
            procedure: {
              data: {
                id: state.procedureId,
                type: 'Procedure'
              }
            }
          }
        }
      }

      return dpApi.post(Routing.generate('api_resource_create', { resourceType: 'TagTopic' }), {}, { data: payload.data })
    },

    deleteSegmentAction ({ state, commit, dispatch }, id) {
      // Remove segment from all segments (styling deletion is handled in textSegment component)
      commit('deleteSegment', id)
      dispatch('saveSegmentsDrafts', false)
    },

    async fetchInitialData ({ state, commit, dispatch }, doUpdate = true) {
      await dispatch('fetchStatement')

      const segments = dpApi.get(Routing.generate('api_resource_get', {
        resourceType: 'Statement',
        resourceId: state.statementId,
        fields: {
          Statement: [
            'segmentDraftList'
          ].join()
        }
      }))
        .then(({ data }) => {
          if (!hasOwnProp(data.data.attributes.segmentDraftList, 'data')) {
            return []
          }
          const initialData = data.data.attributes.segmentDraftList.data
          const segments = initialData.attributes.segments

          commit('setProperty', { prop: 'initialData', val: initialData })
          commit('setProperty', { prop: 'initialSegments', val: segments })

          // This should not be neccessary once the BE always sends a place
          segments.forEach((segment, idx) => {
            if (hasOwnProp(segment, 'place') === false) {
              if (state.availablePlaces.length > 0) {
                segments[idx].place = { id: state.availablePlaces[0].value, name: state.availablePlaces[0].label }
              } else {
                segments[idx].place = { id: '', name: '' }
              }
            }
          })
          commit('setProperty', { prop: 'segments', val: segments })
          commit('setProperty', { prop: 'initText', val: initialData.attributes.textualReference })

          /**
           * Recalculating the indexing of segments should only be done once. As soon as their boundary positions
           * have been adjusted to the Prosemirror indexing scheme, we don't want to reindex them because this would
           * cause misalignment of segments.
           */
          const haveProsemirrorIndexing = typeof segments.find(segment => segment.hasProsemirrorIndex === true) !== 'undefined'
          if (haveProsemirrorIndexing === false && doUpdate === true) {
            commit('recalculatePositionsInText')
            dispatch('persistProsemirrorIndexing')
          }

          const segmentTags = state.segments.reduce((acc, seg) => {
            const tagNames = seg.tags || []
            return [...acc, ...tagNames]
          }, [])

          const uniqueSegmentTags = []
          const uniqueSegmentTagNames = []

          segmentTags.forEach(tag => {
            if (uniqueSegmentTagNames.indexOf(tag.tagName) === -1) {
              uniqueSegmentTags.push(tag)
              uniqueSegmentTagNames.push(tag.tagName)
            }
          })

          return uniqueSegmentTags
        })

      // Get tag list
      const url = Routing.generate('api_resource_list', { resourceType: 'Tag' })
      const tags = dpApi.get(url, { include: 'topic' }, { serialize: true })
        .then(response => {
          const tags = response.data
          commit('setProperty', { prop: 'availableTags', val: tags.data })

          const tagTopics = tags.included.filter((el) => el.type === 'TagTopic')
          commit('setProperty', { prop: 'tagTopics', val: tagTopics })

          const { uncategorizedTags, categorizedTags } = tags.data.reduce((acc, tag) => {
            if (tag.relationships && tag.relationships.topic) {
              return { ...acc, categorizedTags: [...acc.categorizedTags, tag] }
            } else if (tag.relationships) {
              return { ...acc, uncategorizedTags: [...acc.uncategorizedTags, tag] }
            }
            return acc
          }, { uncategorizedTags: [], categorizedTags: [] })

          commit('setProperty', { prop: 'uncategorizedTags', val: uncategorizedTags })
          commit('setProperty', { prop: 'categorizedTags', val: categorizedTags })

          return uncategorizedTags.concat(categorizedTags).map(tag => tag.attributes.title)
        })

      return Promise.all([segments, tags]).then(([segmentTags, tags]) => {
        let pendingPiTags = []
        segmentTags.forEach(tag => {
          if (tags.indexOf(tag.tagName) === -1) {
            pendingPiTags.push(tag)
          }
        })

        const segments = JSON.parse(JSON.stringify(state.segments)).map(segment => {
          // We need to replace PI generated tag ids with dplan tag ids
          segment.tags = segment.tags.map(tag => {
            const dplanTag = state.categorizedTags.find(t => t.attributes.title === tag.tagName)
            if (dplanTag) {
              return {
                ...tag,
                id: dplanTag.id
              }
            } else {
              return tag
            }
          })
          return segment
        })

        commit('setProperty', { prop: 'segments', val: segments })
        pendingPiTags = transformPiToJsonApi(pendingPiTags)

        const mergedTags = [...state.uncategorizedTags, ...pendingPiTags]
        const availableTags = [...state.availableTags, ...pendingPiTags]
        commit('setProperty', { prop: 'uncategorizedTags', val: mergedTags })
        commit('setProperty', { prop: 'availableTags', val: availableTags })
      })
    },

    fetchStatement ({ state, commit }) {
      return dpApi.get(Routing.generate('api_resource_get', {
        resourceType: 'Statement',
        resourceId: state.statementId,
        fields: {
          Statement: [
            'authoredDate',
            'authorName',
            'isSubmittedByCitizen',
            'initialOrganisationCity',
            'initialOrganisationDepartmentName',
            'initialOrganisationHouseNumber',
            'initialOrganisationName',
            'initialOrganisationPostalCode',
            'initialOrganisationStreet',
            'internId',
            'isManual',
            'memo',
            'segmentDraftList',
            'submitDate',
            'submitName',
            'submitType'
          ].join()
        }
      }))
        .then((response) => {
          commit('setProperty', { prop: 'statement', val: response.data.data })
        })
    },

    fetchStatementSegmentDraftList ({ state, commit }, statementId) {
      return dpApi.get(Routing.generate('api_resource_get', {
        resourceType: 'Statement',
        resourceId: statementId || state.statementId,
        fields: {
          Statement: [
            'segmentDraftList'
          ].join()
        }
      }))
    },

    persistProsemirrorIndexing ({ commit, dispatch, state }) {
      const segments = JSON.parse(JSON.stringify(state.segments))
      const indexedSegments = segments.map(segment => {
        segment.hasProsemirrorIndex = true
        return segment
      })

      indexedSegments.forEach(segment => {
        commit('replaceSegment', { id: segment.id, newSegment: segment })
      })

      dispatch('saveSegmentsDrafts')
    },

    saveSegmentsDrafts ({ state, dispatch }, triggerNotifications = false) {
      const dataToSend = JSON.parse(JSON.stringify(state.initialData))
      dataToSend.attributes.segments = state.segments
      const payload = {
        id: state.statementId,
        type: 'Statement',
        attributes: {}
      }
      payload.attributes.segmentDraftList = JSON.stringify({
        data: dataToSend
      })
      return dpApi.patch(Routing.generate('api_resource_update', {
        resourceType: 'Statement',
        resourceId: state.statementId
      }), {}, { data: payload })
        .then((response) => {
          dispatch('fetchInitialData', false)
          if (triggerNotifications) {
            if (response.status === 204) {
              dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
            } else {
              dplan.notify.notify('error', Translator.trans('error.api.generic'))
            }
          }
        })
    },

    saveSegmentsFinal ({ dispatch, state, commit }) {
      const dataToSend = JSON.parse(JSON.stringify(state.initialData))
      dataToSend.attributes.segments = state.segmentsWithText
      dataToSend.attributes.statementText = state.statementText

      return dpApi.post(Routing.generate('dplan_drafts_list_confirm', { statementId: state.statementId, procedureId: state.procedureId }), {}, { data: dataToSend })
        .then(checkResponse)
        .then((response) => {
          if (response.data.nextStatementId !== '') {
            const form = document.createElement('form')
            const path = Routing.generate('dplan_drafts_list_claim', { statementId: response.data.nextStatementId, procedureId: state.procedureId })
            form.setAttribute('action', path)
            form.setAttribute('method', 'post')
            document.body.appendChild(form)
            form.submit()
          } else {
            window.location.href = Routing.generate('DemosPlan_procedure_dashboard', { procedure: state.procedureId })
          }
          return Promise.resolve(true)
        })
        .catch((err) => {
          // Reset view to last saved data - set segments from last initial data
          commit('setProperty', { prop: 'segments', val: state.initialSegments })
          return Promise.reject(err)
        })
    },

    /**
     * Update currently selected tags on editingSegment
     * @param tag {Object} needs tagName and id
     */
    updateCurrentTags ({ state, commit }, tag) {
      const transformedTag = transformJsonApiToPi(JSON.parse(JSON.stringify(tag)))
      const titleIdx = state.editingSegment.tags.findIndex(el => el.tagName === transformedTag.tagName)
      const idIdx = state.editingSegment.tags.findIndex(el => el.id === transformedTag.id)
      const newEditingSegment = JSON.parse(JSON.stringify(state.editingSegment))

      // If neither id nor tagName exist, add tag
      if (idIdx < 0 && titleIdx < 0) {
        newEditingSegment.tags = [...newEditingSegment.tags, transformedTag]
      } else if (idIdx < 0 && titleIdx >= 0) {
        // If tagName exists, but id doesn't, replace tag
        newEditingSegment.tags[titleIdx] = transformedTag
      } else if (idIdx >= 0 && titleIdx >= 0) {
        // If id and tagName exist, delete tag
        const tags = [...newEditingSegment.tags]
        tags.splice(idIdx, 1)
        newEditingSegment.tags = tags
      }
      commit('setProperty', { prop: 'editingSegment', val: newEditingSegment })
      commit('locallyUpdateSegments', [newEditingSegment])
    },

    /**
     * Request segment and tag suggestions from PI
     * @param id {String} statement id
     */
    splitStatementAction ({ state }, id) {
      dpRpc('segment.statement', { statementId: id })
        .catch(err => console.error(err))
    }
  },

  getters: {
    availableTags: (state) => state.availableTags,
    assignableUsers: (state) => state.assignableUsers,
    availablePlaces: (state) => state.availablePlaces,
    categorizedTags: (state) => state.categorizedTags,
    currentlyHighlightedSegmentId: (state) => state.currentlyHighlightedSegmentId,
    editModeActive: (state) => state.editModeActive,
    editingSegment: (state) => state.editingSegment,
    editingSegmentId: (state) => state.editingSegment ? state.editingSegment.id : null,
    initialData: (state) => state.initialData,
    initText: (state) => state.initText,
    isBusy: (state) => state.isBusy,
    procedureId: (state) => state.procedureId,
    segments: (state) => state.segments,
    sortedSegments: (state) => state.segments.concat().sort((a, b) => a.charStart - b.charStart),
    statement: (state) => state.statement,
    statementSegmentDraftList: (state) => state.statement?.attributes.segmentDraftList || '',
    segmentById: (state) => (id) => state.segments.find((el) => el.id === id),
    tagById: (state) => (id) => state.availableTags.find((el) => el.id === id),
    tagTopics: (state) => state.tagTopics,
    tagsByCategoryId: (state) => (categoryId) => state.categorizedTags.filter(tag => tag.relationships.topic.data.id === categoryId),
    uncategorizedTagById: (state) => (id) => state.uncategorizedTags.find((el) => el.id === id),
    uncategorizedTags: (state) => state.uncategorizedTags
  }
}

export default SplitStatementStore
