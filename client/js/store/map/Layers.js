/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import {
  checkResponse,
  dpApi,
  hasOwnProp
} from '@demos-europe/demosplan-ui'

/**
 * Sorts a flat list by the given attribute
 *
 * @param {[]} list
 * @param {string} attr e.g. 'mapOrder', 'treeOrder', 'index
 *
 * @return {object[]} list
 */
const sortListByAttr = (list, attr = 'treeOrder') => {
  return list.sort((a, b) => {
    const formattedOrderA = a.attributes[attr] // .toString().padEnd(21, '0')
    const formattedOrderB = b.attributes[attr] // .toString().padEnd(21, '0')

    return formattedOrderA - formattedOrderB
  })
}

/**
 * Returns a list a all children from a given LayerCategory
 *
 * @param {Array<Object>} elements - list of all layers and categories
 * @param {Object} el - the parent element
 * @param {string} listType - 'treeOrder' | 'mapOrder'
 * @param {string} layerType - 'overlay' | 'base'
 *
 * @returns {*[]}
 */
const getDirectChildren = (elements, el, listType = 'treeOrder', layerType = 'overlay') => {
  let children = el.relationships?.gisLayers?.data ?? []
  children = children
    .map(el => elements[el.id])
    .filter(layer => layer.attributes.layerType === layerType)

  if (layerType === 'overlay') {
    const categories = el.relationships?.categories?.data ?? []
    children = [
      ...children,
      ...categories.map(el => elements[el.id]) ?? []
    ]
  }

  return children
}

/**
 *
 * @param state
 * @param layerType
 *
 * @returns {Array|*[]}
 */
const getFlatChildren = (state, layerType) => {
  if (typeof state.apiData.included === 'undefined') return []

  const layer = state.apiData.included
    .filter(el => el.type === 'GisLayer' && el.attributes.layerType === layerType)

  return sortListByAttr(layer, 'index')
}

const sameParent = (el, parentId) => {
  return el.type === 'GisLayer'
    ? el.attributes.categoryId === parentId
    : el.attributes.parentId === parentId // Category has parentId instead of categoryId
}

const LayersStore = {
  namespaced: true,

  name: 'Layers',

  state: {
    activeLayerId: '',
    apiData: {},
    categoryId: null,
    currentSorting: 'mapOrder',
    draggableOptions: {},
    draggableOptionsForBaseLayer: {},
    elementListForLayerSidebar: [],
    gisLayerList: [],
    hoverLayerIconIsHovered: false,
    hoverLayerId: '',
    isMapLoaded: false,
    legends: [],
    layerList: {},
    originalApiData: {},
    procedureId: '',
    visibilityGroups: {}
  },

  mutations: {
    /**
     * Adds a relationship to the given parent
     * (Removing it from the old parent has to be handled separately)
     *
     * @param state
     * @param {string} id
     * @param {string} parentId
     * @param {boolean} isRoot
     *
     * @returns {void}
     */
    addRelationship  (state, { id, parentId, isRoot }) {
      const parent = (isRoot || !parentId || parentId === '') ? state.apiData.data : state.layerList[parentId]
      const type = state.layerList[id].type === 'GisLayerCategory' ? 'categories' : 'gisLayers'

      if (!parent.relationships) {
        parent.relationships = {}
      }

      if (!parent.relationships[type]) {
        parent.relationships[type] = { data: [] }
      }

      parent.relationships[type].data.push({
        type,
        id
      })
    },

    /**
     * Removes a relationship from the given parent
     *
     * @param state
     * @param {string} id
     * @param {string} parentId
     * @param {boolean} isRoot
     *
     * @returns {void}
     */
    removeRelationship  (state, { id, parentId, isRoot }) {
      const parent = (isRoot || !parentId || parentId === '') ? state.apiData.data : state.layerList[parentId]
      const type = state.layerList[id].type === 'GisLayerCategory' ? 'categories' : 'gisLayers'
      const index = parent.relationships[type].data.findIndex(el => el.id === id)

      parent.relationships[type].data.splice(index, 1)
    },

    /**
     * Saves the original state of the apiData
     * (As Copy to restore original order)
     *
     * @param state
     * @param {object} data - response from a json-api request
     *
     * @returns {void}
     */
    saveOriginalState (state, data) {
      state.originalApiData = JSON.parse(JSON.stringify(data))
    },

    /**
     * Sets the given key of the state to the given value
     *
     * @param state
     * @param {string} key - the key of the state
     * @param {Any} value - the value to set
     *
     * @returns {void}
     */
    set (state, { key, value }) {
      state[key] = value
    },

    /**
     * Sets the given key of the state to the given value
     *
     * @param state
     * @param {jsonApiResponse} data - response from a json-api request
     *
     * @returns {void}
     */
    setApiDataToList (state, data) {
      state.apiData = data

      data.included.forEach(el => {
        if (el.type !== 'ContextualHelp') {
          state.layerList[el.id] = el
        }
      })
    },

    /**
     * Fills Array of layer legends
     *
     * @param state
     * @param {object} data - layerLegendObject {'layerId', 'treeOrder', 'mapOrder', 'defaultVisibility', 'url'}
     *
     * @returns {void}
     */
    setLegend (state, data) {
      state.legends.push(data)
    },

    /**
     * Sets the given attribute of the given item (id) to the given value
     *
     * @param state
     * @param {Object} data {'id': LayerId, 'attribute':AttributeName, 'value':AttributeValue}
     * @param {String} attribute name of the attribute
     * @param {String} id
     * @param {any} value
     *
     * @returns {void}
     */
    setAttributeForLayer (state, { attribute, id, value }) {
      const index = state.apiData.included.findIndex(elem => elem.id === id)

      if (index >= 0) {
        state.apiData.included[index].attributes[attribute] = value
      }
    },

    /**
     * Removes the given element from the state
     *
     * @param state
     * @param {Object} element
     * @param {String} element.id
     * @param {String} element.categoryId
     * @param {String} element.relationshipType 'categories' | 'gisLayers'
     *
     * @returns {void}
     */
    removeElement (state, element) {
      const included = state.apiData.included
      let relationships
      let indexRelationships = []

      // Get the index to delete later on
      const indexIncluded = included.findIndex(elem => elem.id === element.id)

      // Get parent index to determine if this is in root category
      const indexParent = included.findIndex(elem => elem.id === element.categoryId)

      // If element is not in root category, the data structure needs a different treatment
      if (indexParent >= 0) {
        relationships = state.apiData.included[indexParent].relationships[element.relationshipType].data
      } else {
        relationships = state.apiData.data[0].relationships[element.relationshipType].data
      }

      // Get index of data in relationships based on above switch
      indexRelationships = relationships.findIndex(elem => elem.id === element.id)

      // Delete data
      included.splice(indexIncluded, 1)
      relationships.splice(indexRelationships, 1)
      delete state.layerList[element.id]
    },

    /**
     * Sets the given options for the draggable component
     *
     * @param state
     *
     * @returns {void}
     */
    setVisibilityGroups (state) {
      const elementsWithVisibilityGroups = state.apiData.included.filter(elem => elem.attributes.visibilityGroupId)

      elementsWithVisibilityGroups.forEach((element) => {
        const groupId = element.attributes.visibilityGroupId
        if (hasOwnProp(state.visibilityGroups, groupId) === false) {
          state.visibilityGroups[groupId] = state.visibilityGroups.length
        }
      })
    },

    /**
     * Sets the given options for the draggable component
     *
     * @param state
     * @param {string} id
     *
     * @returns {void}
     */
    setMinimapBaseLayer (state, id) { // Used in AdminLayerList component
      const previousMinimap = state.apiData.included.find(elem => elem.attributes.isMinimap === true)
      if (previousMinimap) { previousMinimap.attributes.isMinimap = false }

      if (id === '') { return }

      const newMinimap = state.apiData.included.find(elem => elem.id === id)
      newMinimap.attributes.isMinimap = true
    },

    /**
     * Sets the given options for the draggable component
     *
     * @param state
     *
     * @returns {void}
     */
    setIsMapLoaded (state) {
      state.isMapLoaded = true
    },

    /**
     * Sets the given options for the draggable component
     *
     * @param state
     * @param {string} id
     * @param {string} key - attribute name
     * @param {any} value - new value for the attribute
     *
     * @returns {void}
     */
    updateLayer (state, { id, key, value }) {
      state.layerList[id].attributes[key] = value
    }
  },

  actions: {
    /**
     * Changes the relationship of a layer to another parent
     *
     * @param state
     * @param commit
     * @param {string} id
     * @param {string} targetParentId
     *
     * @returns {void}
     */
    changeRelationship ({ state, commit }, { id, targetParentId }) {
      const layer = state.layerList[id]
      const sourceParentId = layer.attributes.categoryId ?? layer.attributes.parentId
      const targetIsRoot = !targetParentId || targetParentId === '' || targetParentId === state.apiData.data.id
      const sourceIsRoot = !sourceParentId || sourceParentId === '' || sourceParentId === state.apiData.data.id
      const targetPId = targetIsRoot ? state.apiData.data.id : targetParentId

      commit('removeRelationship', {
        id: id,
        parentId: sourceParentId,
        isRoot: sourceIsRoot
      })

      commit('addRelationship', {
        id: id,
        parentId: targetPId,
        isRoot: targetIsRoot
      })

      if (layer.type === 'GisLayerCategory') {
        commit('updateLayer', {
          id: id,
          key: 'parentId',
          value: targetPId
        })
      } else {
        commit('updateLayer', {
          id: id,
          key: 'categoryId',
          value: targetPId
        })
      }
    },

    /**
     * Fetches the layers for the given procedureId
     *
     * @param state
     * @param commit
     * @param dispatch
     *
     * @returns {PromiseLike<void>|Promise<never>}
     */
    fetchLayers ({ state, commit, dispatch }) {
      if (!state.procedureId) {
        return Promise.reject(new Error('No procedureId given'))
      }

      return dpApi.get(Routing.generate('api_resource_list', {
        resourceType: 'GisLayerCategory',
        include: 'gisLayers',
        fields: {
          GisLayerCategory: [
            'categories',
            'gisLayers',
            'hasDefaultVisibility',
            'isVisible',
            'name',
            'layerWithChildrenHidden',
            'parentId',
            'treeOrder',
          ].join(),
          GisLayer: [
            'canUserToggleVisibility',
            'categoryId',
            'hasDefaultVisibility',
            'isBaseLayer',
            'isBplan',
            'isEnabled',
            'isMinimap',
            'isScope',
            'layers',
            'layerType',
            'mapOrder',
            'name',
            'opacity',
            'treeOrder',
            'url',
            'visibilityGroupId'
          ].join()
        },
        filter: {
          name: {
            condition: {
              path: 'parentId',
              operator: 'IS NULL',
            }
          }
        }
      }))
        .then(checkResponse)
        .then(data => {
          commit('setApiDataToList', data)
          commit('saveOriginalState', data)
          commit('setVisibilityGroups')
          dispatch('createIndexes')
          dispatch('buildLegends')
        })
    },

    /**
     * Reset the order of the layers to the original order
     *
     * @param state
     * @param commit
     * @param dispatch
     *
     * @returns {void}
     */
    resetOrder ({ state, commit, dispatch }) {
      state.apiData = JSON.parse(JSON.stringify(state.originalApiData))
      commit('setApiDataToList', state.apiData)
      dispatch('createIndexes')
    },

    /**
     * Create a index the order of the layers by the currentSorting
     *
     * @param state
     * @param commit
     * @param dispatch
     *
     * @returns {void}
     */
    createIndexes ({ state, commit, dispatch }) {
      const orderType = state.currentSorting
      const parentId = state.apiData.data.id
      const layerArray = Object.values(state.layerList)

      if (orderType === 'treeOrder') {
        layerArray
          .filter(el => {
            if (el.type === 'GisLayerCategory') {
              return el.attributes.parentId === parentId
            }

            return el.attributes.layerType === 'overlay' && el.attributes.categoryId === parentId
          })
          .sort((a, b) => a.attributes[orderType] - b.attributes[orderType])
          .forEach((el, idx) => {
            commit('updateLayer', { id: el.id, key: 'index', value: idx })
            dispatch('createIndexForChildren', { parentId: el.id })
          })
      } else {
        layerArray
          .filter(el => el.attributes.layerType === 'overlay')
          .sort((a, b) => a.attributes[orderType] - b.attributes[orderType])
          .forEach((el, idx) => {
            commit('updateLayer', { id: el.id, key: 'index', value: idx })
          })
      }

      layerArray
        .filter(el => el.attributes.layerType === 'base')
        .sort((a, b) => a.attributes[orderType] - b.attributes[orderType])
        .forEach((el, idx) => {
          commit('updateLayer', { id: el.id, key: 'index', value: idx })
        })
    },

    /**
     * Create a index the order of the layers for nested Lists (overlay treeOrder)
     *
     * @param state
     * @param commit
     * @param dispatch
     * @param {string} parentId
     *
     * @returns {void}
     */
    createIndexForChildren ({ state, commit, dispatch }, { parentId }) {
      const parent = state.layerList[parentId]
      const children = getDirectChildren(state.layerList, parent, 'treeOrder')

      children
        .sort((a, b) => a.attributes.treeOrder - b.attributes.treeOrder)
        .forEach((el, idx) => {
          commit('updateLayer', { id: el.id, key: 'index', value: idx })
          dispatch('createIndexForChildren', { parentId: el.id })
        })
    },

    /**
     * Get layer legends. Legends needs to be fetched for each single gisLayer layer
     * as some map services are not able to group legends
     *
     * @param commit
     * @param getters
     *
     * @returns {void}
     */
    buildLegends ({ commit, getters }) {
      const layers = getters.gisLayerList('overlay')
      for (let i = 0; i < layers.length; i++) {
        const layer = layers[i]
        const layerParam = layer.attributes.layers
        const delimiter = (layer.attributes.url.indexOf('?') === -1) ? '?' : '&'
        const legendUrlBase = layer.attributes.url + delimiter
        // Get layer layers
        const layerParamSplit = layerParam.split(',').map(function (item) {
          return item.trim()
        })
        // Add each layer to GetLegendGraphic request
        for (let j = 0; j < layerParamSplit.length; j++) {
          if (layer.attributes.isEnabled) {
            const legendUrl = legendUrlBase + 'Layer=' + layerParamSplit[j] + '&Request=GetLegendGraphic&Format=image/png&version=1.1.1'
            const legend = {
              layerId: layer.id,
              treeOrder: layer.attributes.treeOrder,
              mapOrder: layer.attributes.mapOrder,
              defaultVisibility: layer.attributes.hasDefaultVisibility,
              url: legendUrl
            }
            commit('setLegend', legend)
          }
        }
      }
    },

    saveAll ({ state, dispatch }) {
      /* save each GIS layer and GIS layer category with its relationships */
      dispatch('save', state.apiData)

      Object.values(state.layerList).forEach(el => {
        dispatch('save', el)
      })
    },

    save ({ state, commit, dispatch }, resource) {
      let payload
      const { attributes, id, type } = resource

      const {
        categoryId,
        parentId,
        hasDefaultVisibility,
        isMinimap,
        mapOrder,
        treeOrder,
        visibilityGroupId
      } = attributes

      if (resource.type === 'GisLayer') {
        payload = {
          data: {
            id,
            type,
            attributes: {
              hasDefaultVisibility,
              isMinimap,
              mapOrder,
              treeOrder,
              visibilityGroupId
            },
            relationships: {
              parentCategory: {
                data: {
                  id: categoryId,
                  type: 'GisLayerCategory'
                }
              }
            }
          }
        }
      }

      if (resource.type === 'GisLayerCategory') {
        payload = {
          data: {
            id,
            type,
            attributes: {
              treeOrder,
              hasDefaultVisibility,
            },
            relationships: {
              parentCategory: {
                data: {
                  id: parentId,
                  type: 'GisLayerCategory'
                }
              }
            }
          }
        }
      }

      return dpApi.patch(Routing.generate('api_resource_update', { resourceType: resource.type, resourceId: resource.id }), {}, payload)
        .then(checkResponse)
        .then(resonse => {
          commit('set', { key: 'activeLayerId', value: '' })

          return resonse
        })
        .catch(err => {
          console.error('Error: save layer', err)
        })
    },

    /**
     * Increeses the index by 1 and calls his next sibling to do the same.
     * Depending if the element comes from top or bottom (or other list), the index will be increased or decreased.
     *
     * @param state
     * @param {string} id
     * @param {int} index
     * @param {string} targetParentId
     * @param {int} oldIndex
     * @param {string} sourceParentId
     *
     * @returns {void}
     */
    setNewIndex ({ state, commit, dispatch }, { id, index, targetParentId, oldIndex, sourceParentId }) {
      // Check if parent is root
      const parentId = (targetParentId === '' || !targetParentId) ? state.apiData.data.id : targetParentId
      const isGislayer = state.layerList[id].type === 'GisLayer'
      const layerType = isGislayer ? state.layerList[id].attributes.layerType : 'overlay'
      // Check if the item is from another (nested/sub) list
      const fromOtherList = isGislayer ? state.layerList[id].attributes.categoryId !== parentId : state.layerList[id].attributes.parentId !== sourceParentId

      commit('updateLayer', {
        id,
        key: 'index',
        value: index
      })

      const nextSibling = Object.values(state.layerList).filter(el => {
        let sameListType = false
        let sameList = true // If its a mapOrder, this is always true, only for the overlay-treelist we have to check the parents

        if (state.currentSorting === 'treeOrder') {
          sameList = layerType === 'base' ? true : sameParent(el, parentId)

          if (el.type === 'GisLayerCategory' && state.currentSorting === 'treeOrder') {
            sameListType = layerType === 'overlay' // We only for want to get categories for the overlay-Tree-list (they don't have a attribute to compare with)
          }
        } else {
          sameListType = el.attributes.layerType === layerType // If its a layer, we compare the layerType to get the right list
        }

        return el.attributes.index === index && // Ee want the get the element from the position we want to place our current element
          sameList && // Since we got the same index per (nested) list, just give us the one with the same parent
          el.id !== id &&
          sameListType
      })

      if (nextSibling.length > 0) {
        const newIndex = (fromOtherList || index < oldIndex) ? index + 1 : index - 1

        if (newIndex >= 0) {
          dispatch('setNewIndex', {
            id: nextSibling[0].id,
            index: newIndex,
            oldIndex: fromOtherList ? 99 : oldIndex,
            targetParentId: parentId,
            sourceParentId: parentId
          })
        }
      }
    },

    /**
     * Updates the sort order (treeOrder | mapOrder) of the layers and their children by taking the index.
     * The index is calculated by the parentOrder * 100 + index
     * The parentOrder has to start with 100 in the root category. Otherwise the Sorting for the legend may not work as expected.
     * (the legend is sorted by treeOrder but as plain list)
     *
     * @param state
     * @param commit
     * @param dispatch
     * @param {string} parentId
     * @param {int} parentOrder
     *
     * @returns {void}
     */
    updateSortOrder ({ state, commit, dispatch }, { parentId, parentOrder = 100 }) {
      const pId = parentId ?? state.apiData.data.id
      let layersToSort = []

      if (state.currentSorting === 'treeOrder') {
        layersToSort = Object.values(state.layerList)
          .filter(el => {
            const isCategoryorOverlay = el.type === 'GisLayerCategory' || el.attributes.layerType === 'overlay'

            return isCategoryorOverlay && sameParent(el, pId)
          })
      }

      if (state.currentSorting === 'mapOrder') {
        layersToSort = Object.values(state.layerList)
          .filter(el => el.attributes.layerType === 'overlay')
      }

      layersToSort
        .forEach(el => {
          commit('updateLayer', {
            id: el.id,
            key: state.currentSorting,
            value: parentOrder + el.attributes.index
          })

          if (state.currentSorting === 'treeOrder') {
            dispatch('updateSortOrder', {
              parentId: el.id,
              parentOrder: parentOrder * 100
            })
          }
        })

      // Always sort base layers. There is no nested sorting because the base doesn't have categories
      Object.values(state.layerList)
        .filter(el => el.type === 'GisLayer' && el.attributes.layerType === 'base')
        .forEach(el => {
          commit('updateLayer', {
            id: el.id,
            key: state.currentSorting,
            value: parentOrder + el.attributes.index
          })
        })
    },

    /**
     * Deletes a layer or category
     *
     * @param state
     * @param commit
     * @param element
     *
     * @returns {PromiseLike<void>}
     */
    deleteElement ({ state, commit }, element) {
      let currentType = 'GisLayer'
      let id = element.id

      if (element.route === 'layer_category') {
        currentType = 'GisLayerCategory'
        id = element.categoryId
      }

      return dpApi.delete(Routing.generate('api_resource_delete', { resourceType: currentType, resourceId: id }))
        .then(this.api.checkResponse)
        .then(() => {
          commit('removeElement', {
            id: element.id,
            categoryId: element.categoryId,
            relationshipType: element.relationshipType
          })
        })
    }

  },

  getters: {
    /**
     * Returns direct children of a cagetory with all their properties sorted by treeOrder/mapOrder property
     *
     * @param state
     * @param {String} parentId
     * @param {String} orderType
     *
     * @return {Array}
     */
    directChildren: state => (parentId, orderType) => {
      const parent = parentId
        ? Object.values(state.layerList).filter(el => el.id === parentId)[0]
        : state.apiData.data // Root

      let children = [...parent.relationships?.categories?.data ?? [], ...parent.relationships?.gisLayers?.data ?? []]
      children = children
        .map(child => state.layerList[child.id])
        .filter(child => !!child)
      return sortListByAttr(children, orderType) || []
    },

    /**
     * Get complete object for stripped object containing element-Id and Type
     * (both have to match the corresponding included-array)
     *
     * @param element|Object ( {id, type} )
     * @returns Object|element(gisLayers or GisLayerCategory)
     */
    element: state => element => {
      if (typeof state.apiData.included === 'undefined') {
        return {}
      }

      if (element.type === 'ContextualHelp') {
        const helpText = state.apiData.included.filter(current => current.attributes.key === ('gislayer.' + element.id))

        if (helpText.length <= 0) {
          return {
            id: 'no-contextual-help',
            type: element.type,
            attributes: {
              text: ''
            }
          }
        } else {
          return helpText[0]
        }
      }

      return state.apiData.included
        .find(current => current.id === element.id && current.type === element.type)
    },

    /**
     * Get List of all gisLayers
     *
     * @returns Array|element(gisLayers or GisLayerCategory)
     */
    gisLayerList: state => type => {
      if (typeof state.apiData.included === 'undefined') return []

      return state.apiData.included
        .filter(current => {
          const putInList = type ? type === current.attributes.layerType : true

          return (current.type === 'GisLayer' && putInList)
        })
        .sort((a, b) => (a.attributes.mapOrder).toString().padEnd(21, '0') - (b.attributes.mapOrder).toString().padEnd(21, '0'))
    },

    /**
     * Get List of all gisLayers filtered by a given attributes value
     * (e.g. all layers with visibilityGroupId = 'someId')
     *
     * @param state
     * @param {Object} attribute {'type', 'value'}
     *
     * @returns Array|element(gisLayers or GisLayerCategory)
     */
    elementsListByAttribute: state => attribute => {
      return Object.values(state.layerList).filter(current => current.attributes[attribute.type] === attribute.value)
    },

    /**
     * Categories and layers mapped to one list and ordered by treeOrder
     *
     * @param state
     * @param {string} categoryId
     * @param {string} type ('overlay' | 'base')
     * @param {Boolean} withCategories
     *
     * @returns: Array|gisLayers (and Categories)
     */
    elementListForLayerSidebar: state => (categoryId, type, withCategories) => {
      //  Return if there is no data
      if (typeof state.apiData.data === 'undefined') {
        return []
      }

      //  When called without categoryId, set it to the id of the root category
      if (categoryId === null) {
        categoryId = state.apiData.data[0].id
      }

      /*
       * Create either list of overlay layers (can have categories) or list of base layers
       * Filter api response by layer type + categories
       */
      const elementList = state.apiData.included.filter(current => {
        //  Only GisLayer has an attributes.layerType so this one will be false for categories + contextual help
        const putLayerInList = type === current.attributes.layerType

        //  Check if categories should be included and this one is a category
        const putCategoriesInList = withCategories === true ? current.type === 'GisLayerCategory' : false

        /*
         *  For categories, their parent category is determined by the field `parentId`
         *  while the parent category of layers is called `categoryId`
         */
        const parentId = (current.type === 'GisLayerCategory') ? 'parentId' : 'categoryId'

        return current.attributes[parentId] === categoryId && (putCategoriesInList || putLayerInList)
      })

      //  Sort elements by treeOrder before returning the list
      return elementList.sort((a, b) => (a.attributes.treeOrder).toString().padEnd(21, '0') - (b.attributes.treeOrder).toString().padEnd(21, '0'))
    },

    /**
     * Get the root id of the current layer list
     *
     * @returns {string} id - id of the root category or empty string
     */
    rootId: state => {
      return state.apiData?.data?.id ?? ''
    },

    /**
     * Categories and layers mapped to one list and ordered by treeOrder
     *
     * @returns {object[]} legendList
     */
    elementListForLegendSidebar: state => {
      if (typeof state.apiData.included === 'undefined') return []

      const legends = state.legends
      const includes = state.apiData.included
      const elementList = legends.filter(current => typeof includes.find(el => el.id === current.layerId) !== 'undefined')

      /* Sort elements by treeOrder before returning the list */
      elementList.sort((a, b) => (a.treeOrder).toString().padEnd(21, '0') - (b.treeOrder).toString().padEnd(21, '0'))

      return elementList
    },

    /**
     * Get the visibilityGroupId of the given layer
     *
     * @param {string} visibilityGroupId
     *
     * @returns {(function(*): (number|number))|*}
     */
    visibilityGroupSize: state => visibilityGroupId => {
      if (visibilityGroupId === '' || typeof state.apiData.included === 'undefined') return 0
      return state.apiData.included.filter(current => {
        return current.attributes.visibilityGroupId === visibilityGroupId
      }).length
    },

    /**
     * LocationPoint
     *
     * @param data|Object {'id', 'attribute'}
     * @returns mixed | depending on the attribute
     */
    attributeForElement: state => data => {
      if (typeof state.apiData.included === 'undefined' || data.id === '') return ''
      return state.apiData.included.filter(current => {
        return current.id === data.id
      })[0].attributes[data.attribute]
    },

    /**
     * Get the minimap layer
     *
     * @returns {attributes: {name: string}, id: string} miniMapLayer
     */
    minimapLayer: state => {
      if (typeof state.apiData.included === 'undefined') { return {} }

      const minimap = state.apiData.included.find(elem => elem.attributes.isMinimap === true)

      if (minimap) {
        return minimap
      } else {
        return { id: '', attributes: { name: 'default' } }
      }
    },

    /**
     * Get a list of all base layers in treeOrder sorted
     *
     * @returns {layerObject[]}
     */
    baseList: state => {
      return getFlatChildren(state, 'base')
    },

    /**
     * Get a list of the root level overlay layers and categories in treeOrder sorted
     *
     * @param state
     * @returns {layerObject[]}
     */
    treeList: state => {
      if (typeof state.apiData.included === 'undefined') return []

      const children = getDirectChildren(state.layerList, state.apiData.data, 'treeOrder', 'overlay')

      return sortListByAttr(children.map(el => state.layerList[el.id]), 'index')
    },

    /**
     * Get a list of all overlay layers in mapOrder sorted
     *
     * @param state
     * @returns {layerObject[]}
     */
    mapList: state => {
      return getFlatChildren(state, 'overlay')
    }
  }
}

export default LayersStore
