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
 * Sorts flat list by the given attribute
 * @param {array} list
 * @param {string} attr 'mapOrder' | 'treeOrder'
 *
 * @return {array} list
 */
const sortListByAttr = (list, attr = 'treeOrder') => {
  return list.sort((a, b) => {
    const formattedOrderA = a.attributes[attr].toString().padEnd(21, '0')
    const formattedOrderB = b.attributes[attr].toString().padEnd(21, '0')

    return formattedOrderA - formattedOrderB
  })
}

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

const getFlatChildren = (state, layerType, orderType) => {
  if (typeof state.apiData.included === 'undefined') return []

  const layer = state.apiData.included
    .filter(el => el.type === 'GisLayer' && el.attributes.layerType === layerType)

  return sortListByAttr(layer, orderType)
}

const sameParent = (el, parentId) => {
  return el.type === 'GisLayer'
    ? el.attributes.categoryId === parentId
    : el.attributes.parentId === parentId // Category has parentId instead of categoryId
}

const LayersStore = {
  namespaced: true,

  name: 'layers',

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
    removedItem: {},
    visibilityGroups: {}
  },

  mutations: {
    /**
     * Adds all properties from `included` to an item in relationship format (type, id)
     * @param state
     * @param {String} listKey
     */
    addMissingProperties (state, { listKey }) {
      state.removedItem = state.layerList[state.removedItem.id]
    },

    /**
     * Adds child to non-root category
     * @param state
     * @param {Number} newIndex
     * @param {String} orderType 'treeOrder' | 'mapOrder'
     * @param {String} relationshipType 'gisLayers' | 'categories'
     * @param {String} targetParentId
     */
    addToCategoryRelationships (state, { newIndex, orderType, relationshipType, targetParentId }) {
      // add item to relationships
      const targetParent = state.layerList[targetParentId]
      targetParent.relationships[relationshipType].data.push({ id: state.removedItem.id, type: state.removedItem.type })

      // update index attribute of all children of the targetParent
      let targetParentChildren = [...Object.values(state.layerList).filter(current => {
        const parentKey = (current.type === 'GisLayerCategory') ? 'parentId' : 'categoryId'
        const isChild = current.attributes[parentKey] === targetParentId
        const isRemovedItem = current.id === state.removedItem.id

        return isChild && !isRemovedItem
      })]

      if (targetParentChildren.length > 0) {
        targetParentChildren = sortListByAttr(targetParentChildren, orderType, state.apiData)
      }

      // targetParentChildren.splice(newIndex, 0, state.removedItem)

      targetParentChildren
        .filter(el => el.attributes.layerType === 'overlay')
        .forEach((el, idx) => {
          el.attributes.index = idx
        })

      targetParentChildren
        .filter(el => el.attributes.layerType === 'base')
        .forEach((el, idx) => {
          el.attributes.index = idx
        })
    },

    /**
     * Adds child to root category
     *
     * @param state
     * @param {String} id
     * @param {Number} newIndex
     * @param {String} relationshipType 'categories' | 'gisLayers'
     * @param {String} orderType 'treeOrder' | 'mapOrder'
     */
    addToRoot (state, { listKey, newIndex, relationshipType }) {
      const { id, type } = state.removedItem
      const relationshipItem = {
        type,
        id
      }

      // add item to root relationships
      // state.apiData.data.relationships[relationshipType].data.splice(newIndex, 0, relationshipItem)

      // Update index for all children of the root category
      // state.layerList.splice(newIndex, 0, state.removedItem)
      Object.values(state.layerList)
        .filter(el => el.attributes.layerType === 'overlay' &&
          (el.attributes.parentId === null || el.attributes.categoryId === null))
        .forEach((el, idx) => {
          el.attributes.index = idx
        })
    },

    /**
     * Builds flat list of layers
     * @param state
     * @param listKey 'mapBaseList' | 'mapList'
     * @param type 'base' | 'overlay'
     * @return {T[]|*[]}
     */
    // buildMapList (state, { listKey, type }) {
    //   if (typeof state.apiData.included === 'undefined') {
    //     return []
    //   }
    //
    //   const mapList = state.apiData.included
    //     .filter(current => {
    //       const putInList = type ? type === current.attributes.layerType : true
    //
    //       return current.type === 'GisLayer' && putInList
    //     })
    //
    //   if (mapList.length > 0) {
    //     state[listKey] = sortListByAttr(mapList, 'mapOrder', state.apiData)
    //   } else {
    //     state[listKey] = []
    //   }
    // },

    /**
     * Removes child from non-root category
     * @param state
     * @param {String} listKey 'treeBaseList' | 'treeList'
     * @param {Number} oldIndex
     * @param {String} orderType 'treeOrder' | 'mapOrder'
     * @param {String} relationshipType 'gisLayers' | 'categories'
     * @param {String} sourceParentId
     */
    removeFromCategoryRelationships (state, { listKey, oldIndex, orderType, relationshipType, sourceParentId }) {
      let sourceParent = Object.values(state[listKey]).find(el => el.id === sourceParentId)

      if (!sourceParent) {
        sourceParent = state.apiData.included.find(el => el.id === sourceParentId)
      }

      let sourceParentChildren = [...state.apiData.included.filter(current => {
        const parentKey = (current.type === 'GisLayerCategory') ? 'parentId' : 'categoryId'
        const isChild = current.attributes[parentKey] === sourceParentId

        return isChild
      })]

      if (sourceParentChildren.length > 0) {
        sourceParentChildren = sortListByAttr(sourceParentChildren, orderType, state.apiData)
      }

      state.removedItem = sourceParentChildren.splice(oldIndex, 1)[0]
      sourceParent.relationships[relationshipType].data = sourceParent.relationships[relationshipType].data.filter(el => el.id !== state.removedItem.id)

      sourceParentChildren.forEach((child, idx) => {
        child.attributes.index = idx
      })
    },

    /**
     * Removes child from root category
     * @param state
     * @param {String} listKey
     * @param {Number} oldIndex
     * @param {String} relationshipType 'categories' | 'gisLayers'
     */
    removeFromRoot (state, { listKey, oldIndex, relationshipType }) {
      // remove item from root relationships
      state.apiData.data.relationships[relationshipType].data.splice(oldIndex, 1)

      // set removedItem
      state.removedItem = state[listKey].splice(oldIndex, 1)[0]

      // update index for all remaining children of the root category
      state[listKey].forEach((el, idx) => {
        el.attributes.index = idx
      })
    },

    removeRelationship  (state, { id, parentId, isRoot }) {
      const parent = (isRoot || !parentId || parentId === '') ? state.apiData.data : state.layerList[parentId]
      const type = state.layerList[id].type === 'GisLayerCategory' ? 'categories' : 'gisLayers'
      const index = parent.relationships[type].data.findIndex(el => el.id === id)

      parent.relationships[type].data.splice(index, 1)
    },

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

    saveOriginalState (state, data) {
      state.originalApiData = JSON.parse(JSON.stringify(data))
    },

    setActiveLayerId (state, data) {
      state.activeLayerId = data
    },

    set (state, { key, value }) {
      state[key] = value
    },

    setApiData (state, data) {
      state.apiData = data
      console.log('set apiData', data)
      data.included.forEach(el => {
        if (el.type !== 'ContextualHelp') {
          state.layerList[el.id] = el
        }
      })
    },

    setHoverLayerIconIsHovered (state, data) {
      state.hoverLayerIconIsHovered = data
    },

    setHoverLayerId (state, data) {
      state.hoverLayerId = data
    },

    setProcedureId (state, data) {
      state.procedureId = data
    },

    /**
     * Updates treeOrder or mapOrder attribute of a given item
     * @param state
     * @param {String} id
     * @param {String} layerType 'base' | 'overlay'
     * @param {String} orderType 'treeOrder' | 'mapOrder'
     * @param {String} parentId
     * @param {Number} parentOrder
     */
    updateOrderAttribute (state, { id, layerType, orderType, parentId, parentOrder }) {
      const completeItem = state.layerList[id]
      const isChildOfGivenCategory = completeItem.attributes.categoryId === parentId || completeItem.attributes.parentId === parentId
      const isCategory = completeItem.type === 'GisLayerCategory'
      const isLayerOfGivenType = completeItem.type === 'GisLayer' && completeItem.attributes.layerType === layerType
      const isEnabledLayer = completeItem.type === 'GisLayer' && completeItem.attributes.isEnabled === true

      if (isChildOfGivenCategory && (isCategory || (isLayerOfGivenType && isEnabledLayer))) {
        console.log('updateOrderAttribute', id, layerType, orderType, parentId, parentOrder)
        completeItem.attributes[orderType] = (parentOrder * 100) + (completeItem.attributes.index + 1)
      }
    },

    updateIndex (state) {
      Object.values(state.layerList)
        .filter(el => el.attributes.layerType === 'overlay')
        .sort((a, b) => a.attributes[state.currentSorting] - b.attributes[state.currentSorting])
        .forEach((el, idx) => {
          el.attributes.index = idx
        })

      Object.values(state.layerList)
        .filter(el => el.attributes.layerType === 'base')
        .sort((a, b) => a.attributes[state.currentSorting] - b.attributes[state.currentSorting])
        .forEach((el, idx) => {
          el.attributes.index = idx
        })
    },

    /**
     * Updates parentId (if category) or categoryId (if layer) of the item that was moved
     * @param state
     * @param attr
     * @param value
     */
    updateParentId (state, { attr, value }) {
      state.removedItem.attributes[attr] = value
    },

    /**
     *
     * @param state
     * @param data
     * @param {Array} data.data
     * @param {String} data.listKey
     * @param {String} data.orderType 'treeOrder' | 'mapOrder'
     */
    sortList (state, data) {
      const { listKey, orderType } = data
      state[listKey] = sortListByAttr(data.data, orderType, state.apiData)
    },

    /**
     * Replace the whole entity
     */
    updateEntity (state, entity) {
      const index = state.apiData.included.findIndex(elem => elem.id === entity.id)
      state.apiData.included[index] = entity
    },

    /**
     * Update treeOrder/mapOrder property for all direct children of a category
     * @param state
     * @param data
     * @param data.orderType
     * @param data.parentId
     * @param data.parentOrder
     */
    updateOrderPropInApiData (state, data) {
      const { orderType, parentId, parentOrder } = data
      const children = state.apiData.included.filter(current => {
        const parentKey = (current.type === 'GisLayerCategory') ? 'parentId' : 'categoryId'
        const isChild = current.attributes[parentKey] === parentId

        return isChild
      })

      children.forEach((child, idx) => {
        child.attributes[orderType] = (parentOrder * 100) + (idx + 1)
      })
    },

    setLegend (state, data) {
      state.legends.push(data)
    },

    /**
     * Sets the given attribute of the given item (id) to the given value
     * @param state
     * @param {Object} data {'id': LayerId, 'attribute':AttributeName, 'value':AttributeValue}
     * @param {String} attribute name of the attribute
     * @param {String} id
     * @param {Any} value
     */
    setAttributeForLayer (state, { attribute, id, value }) {
      const index = state.apiData.included.findIndex(elem => elem.id === id)

      if (index >= 0) {
        state.apiData.included[index].attributes[attribute] = value
      }
    },

    /**
     *
     * @param state
     * @param {Object} element
     * @param {String} element.id
     * @param {String} element.categoryId
     * @param {String} element.relationshipType 'categories' | 'gisLayers'
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
        relationships = state.apiData.data.relationships[element.relationshipType].data
      }

      // Get index of data in relationships based on above switch
      indexRelationships = relationships.findIndex(elem => elem.id === element.id)

      // Delete data
      included.splice(indexIncluded, 1)
      relationships.splice(indexRelationships, 1)
    },

    /**
     *
     * Updates the children of a category (the root-level is a category too)
     * (elements and order-position of them incl parentId)
     *
     * @param state
     * @param {Object} data
     * @param {string|null} data.categoryId
     * @param {array} data.data
     * @param {string} data.orderType 'treeOrder' | 'mapOrder'
     * @param {number} data.parentOrder
     */
    setChildrenFromCategory (state, data) {
      let category = {}
      const isRootCategory = data.categoryId === null

      if (isRootCategory) {
        data.categoryId = state.apiData.data.id
        category = state.apiData.data
      } else {
        category = state.apiData.included.find(elem => elem.id === data.categoryId)
      }

      if (category.type === 'GisLayerCategory') {
        // Create new child-elements-arrays (relationships) for the parent of the given List
        const categories = []
        const layers = []

        data.data.forEach((el, idx) => {
          el.attributes[data.orderType] = (data.parentOrder * 100) + (idx + 1)

          if (data.orderType === 'treeOrder') {
            if (el.type === 'GisLayerCategory') {
              el.attributes.parentId = data.categoryId
              categories.push(el)
            } else if (el.type === 'GisLayer') {
              el.attributes.categoryId = data.categoryId
              if (el.attributes.isEnabled) {
                layers.push(el)
              }
            }
          }
        })

        // Update the store-state
        category.relationships.categories.data = categories
        category.relationships.gisLayers.data = layers
      }
    },

    resetOrder (state) {
      // We have to clone the original state because otherwise after the first reset the reactivity will bind these two
      // objects and will cause changing of originalApiData anytime state.apiData changes
      state.apiData = JSON.parse(JSON.stringify(state.originalApiData))
      state.apiData.included.sort((a, b) => ('' + a.attributes.mapOrder).padEnd(21, 0) - ('' + b.attributes.mapOrder).padEnd(21, 0))
    },

    setVisibilityGroups (state) {
      const elementsWithVisibilityGroups = state.apiData.included.filter(elem => {
        return (typeof elem.attributes.visibilityGroupId !== 'undefined' && elem.attributes.visibilityGroupId !== '')
      })

      elementsWithVisibilityGroups.forEach((element) => {
        const groupId = element.attributes.visibilityGroupId
        if (hasOwnProp(state.visibilityGroups, groupId) === false) {
          state.visibilityGroups[groupId] = state.visibilityGroups.length
        }
      })
    },

    setMinimapBaseLayer (state, id) { // Used in DpAdminLayerList component
      const previousMinimap = state.apiData.included.find(elem => elem.attributes.isMinimap === true)
      if (previousMinimap) { previousMinimap.attributes.isMinimap = false }

      if (id === '') { return }

      const newMinimap = state.apiData.included.find(elem => elem.id === id)
      newMinimap.attributes.isMinimap = true
    },

    setIsMapLoaded (state) {
      state.isMapLoaded = true
    },

    updateLayer (state, { id, key, value }) {
      console.log('updateLayer', state.layerList[id].attributes.name, key, value)
      state.layerList[id].attributes[key] = value
    }
  },

  actions: {
    /**
     * Add item to root or other category
     * @param state
     * @param commit
     * @param {Object} data
     * @param {String} listKey
     * @param {Number} newIndex
     * @param {String} orderType 'treeOrder' | 'mapOrder'
     * @param {String} relationshipType 'categories' | 'gisLayers'
     * @param {Boolean} targetIsRoot
     * @param {String} targetParentId
     */
    addItem ({ state, commit }, { id, newIndex, orderType, targetIsRoot, targetParentId }) {
      const relationshipType = state.layerList[id].type === 'GisLayerCategory' ? 'categories' : 'gisLayers'

      if (targetIsRoot) {
        commit('addToRoot', {
          id,
          newIndex,
          relationshipType,
          orderType
        })
      } else {
        commit('addToCategoryRelationships', {
          id,
          newIndex,
          orderType,
          relationshipType,
          targetParentId
        })
      }
    },

    /**
     * Builds list of direct children (layers or categories) of a category
     * @param state
     * @param commit
     * @param data
     * @param categoryId
     * @param type 'base' | 'overlay'
     * @param {boolean} withCategories
     * @return {*[]}
     */
    // buildChildrenListForTreeCategory ({ state, commit }, { categoryId = null, type, withCategories }) {
    //   if (typeof state.apiData.data === 'undefined') {
    //     return []
    //   }
    //
    //   const listKey = type === 'base' ? 'treeBaseList' : 'treeList'
    //   let categoryOrRootId = categoryId
    //   //  If called without categoryId, set it to the id of the root category
    //   if (categoryId === null) {
    //     categoryOrRootId = state.apiData.data.id
    //   }
    //
    //   // Get category children with all properties from included
    //   const navigationList = state.apiData.included.filter(current => {
    //     //  Only GisLayer has an attributes.layerType so this one will be false for categories + contextual help
    //     const isLayer = type === current.attributes.layerType
    //     const isCategory = current.type === 'GisLayerCategory'
    //
    //     if (!isLayer && !isCategory) {
    //       return false
    //     }
    //
    //     const shouldIncludeCategories = withCategories
    //
    //     /*
    //      *  For categories, their parent category is determined by the field `parentId`
    //      *  while the parent category of layers is called `categoryId`
    //      */
    //     const parentKey = current.type === 'GisLayerCategory'
    //       ? 'parentId'
    //       : 'categoryId'
    //     const isInGivenCategory = current.attributes[parentKey] === categoryOrRootId
    //
    //     return isInGivenCategory && ((shouldIncludeCategories && isCategory) || isLayer)
    //   })
    //
    //   //  Sort elements by treeOrder and store them in state
    //   if (navigationList.length > 0) {
    //     commit('sortList', {
    //       listKey,
    //       orderType: 'treeOrder',
    //       data: navigationList
    //     })
    //   }
    //
    //   // update item index, needed for updating treeOrder/mapOrder in updateItemAttrs
    //   state[listKey].forEach((el, idx) => {
    //     commit('setAttributeForLayer', {
    //       id: el.id,
    //       attribute: 'index',
    //       value: idx
    //     })
    //   })
    // },

    /**
     * Build list of (a) just layers (mapList, mapBaseList) or (b) only direct children (layers and categories) of a
     * category (treeList, treeBaseList)
     * @param state
     * @param commit
     * @param dispatch
     * @param data
     * @param {string} data.categoryId
     * @param {string} data.listKey 'map' | 'mapBaseList' | 'tree' | 'treeBaseList'
     * @param {string} data.type 'base' | 'overlay'
     * @param {boolean} data.withCategories
     */
    buildList ({ state, commit, dispatch }, data) {
      const {
        listKey,
        type
      } = data




      // if (listKey === 'mapList' || listKey === 'mapBaseList') {
      //   commit('buildMapList', {
      //     listKey: listKey,
      //     type: type
      //   })
      // }

      // if (listKey === 'treeList') {
      //   dispatch('buildChildrenListForTreeCategory', data)
      // }
    },

    changeRelationship ({ state, commit }, { id, sourceParentId, targetParentId }) {
      const layer = state.layerList[id]
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

    get ({ commit, dispatch }, procedureId) {
      commit('setProcedureId', procedureId)

      return dpApi({
        method: 'GET',
        url: Routing.generate('dplan_api_procedure_layer_list', {
          procedureId: procedureId,
          include: [
            'categories',
            'gisLayers'
          ].join()
        }),
        responseType: 'json'
      })
        .then(checkResponse)
        .then(data => {
          commit('setApiData', data)
          commit('saveOriginalState', data)
          commit('setVisibilityGroups')
          commit('updateIndex')
          dispatch('buildLegends')

          // const listTypes = ['map', 'tree', 'mapBase', 'treeBase']
          // listTypes.forEach(type => dispatch('setListByType', { listType: type }))
        })
    },

    createIndexes ({ state, commit, dispatch }, { overlay }) {
      const orderType = state.currentSorting
      const parentId = state.apiData.data.id

      if (overlay && orderType === 'treeOrder') {
        Object.values(state.layerList)
          .filter(el => {
            if (el.type === 'GisLayerCategory') {
              return el.attributes.parentId === parentId
            }

            return el.attributes.layerType === 'overlay' && el.attributes.categoryId === parentId
          })
          .sort((a, b) => a.attributes[orderType] - b.attributes[orderType])
          .forEach((el, idx) => {
            commit('updateLayer', { id: el.id, key: 'index', value: idx })
            dispatch('createIndexForChildren', { parentId: el.id, orderType: orderType })
          })
      } else {
        Object.values(state.layerList)
          .filter(el => el.attributes.layerType === overlay ? 'overlay' : 'base')
          .sort((a, b) => a.attributes[orderType] - b.attributes[orderType])
          .forEach((el, idx) => {
            commit('updateLayer', { id: el.id, key: 'index', value: idx })
          })
      }
    },

    createIndexForChildren ({ state, commit, dispatch }, { parentId, orderType }) {
      const parent = state.layerList[parentId]
      const children = getDirectChildren(state.layerList, parent, orderType)

      children
        .sort((a, b) => a.attributes[orderType] - b.attributes[orderType])
        .forEach((el, idx) => {
          commit('updateLayer', { id: el.id, key: 'index', value: idx })
          dispatch('createIndexForChildren', { parentId: el.id, orderType: 'treeOrder' })
        })
    },
    /**
     * Get layer legends. Legends needs to be fetched for each single gislayer layer
     * as some map services are not able to group legends
     * @param commit
     * @param getters
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
        // Add each layer layer to GetLegendGraphic request
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

    /**
     * Remove list item from root or other category
     * @param state
     * @param commit
     * @param data
     * @param {String} listKey
     * @param {Number} oldIndex
     * @param {String} orderType 'treeOrder' | 'mapOrder'
     * @param {String} relationshipType 'categories' | 'gisLayers'
     * @param {Boolean} sourceIsRoot
     * @param {String} sourceParentId
     */
    removeItem ({ state, commit }, { listKey, oldIndex, orderType, relationshipType, sourceIsRoot, sourceParentId }) {
      // if (sourceIsRoot) {
      //   commit('removeFromRoot', {
      //     listKey,
      //     oldIndex,
      //     relationshipType
      //   })
      // }

      // if (!sourceIsRoot) {
      //   commit('removeFromCategoryRelationships', {
      //     listKey,
      //     oldIndex,
      //     orderType,
      //     relationshipType,
      //     sourceParentId
      //   })
      // }
    },

    save ({ state, commit, dispatch }) {

      return dpApi({
        method: 'POST',
        url: Routing.generate('dplan_api_procedure_layer_update', {
          procedureId: state.procedureId
        }),
        responseType: 'json',
        data: {
          data: state.apiData
        }
      })
        .then(checkResponse)
        .then(() => {
          dispatch('get', state.procedureId)
            .then(() => {
              commit('setActiveLayerId', '')
            })
            .catch(err => {
              console.error('Error: save layer', err)
            })
        })
        .catch(err => {
          console.error('Error: save layer', err)
        })
    },

    /**
     * Sets a state list property (mapList, mapBaseList, treeList, treeBaseList) based on the given listType
     * @param state
     * @param commit
     * @param dispatch
     * @param getters
     * @param {string} listType 'map' | 'tree' | 'mapBase' | 'treeBase'
     * @param {number|null} targetParentId
     */
    setListByType ({ state, commit, dispatch, getters }, { listType, targetParentId = null }) {
      switch (listType) {
        case 'map':
          dispatch('buildList', {
            listKey: `${listType}List`,
            type: 'overlay'
          })
          break
        case 'mapBase':
          dispatch('buildList', {
            listKey: `${listType}List`,
            type: 'base'
          })
          break
        case 'tree':
          dispatch('buildList', {
            categoryId: targetParentId,
            listKey: `${listType}List`,
            type: 'overlay',
            withCategories: true
          })
          break
        case 'treeBase':
          dispatch('buildList', {
            categoryId: targetParentId,
            listKey: `${listType}List`,
            type: 'base',
            withCategories: false
          })
          break
      }
    },

    /**
     * Increeses the index by 1 and calls his next sibling to do the same
     *
     * @param state
     * @param index
     * @param parentId
     */
    setNewIndex ({ state, commit, dispatch }, { id, index, targetParentId, oldIndex }) {
      // Check if parent is root
      const parentId = (targetParentId === '' || !targetParentId) ? state.apiData.data.id : targetParentId
      const isGislayer = state.layerList[id].type === 'GisLayer'
      const layerType = state.layerList[id].attributes.layerType
      // Check if the item is from another (nested/sub) list
      const fromOtherList = isGislayer ? state.layerList[id].attributes.categoryId !== parentId : state.layerList[id].attributes.parentId !== parentId

      commit('updateLayer', {
        id,
        key: 'index',
        value: index
      })

      const nextSibling = Object.values(state.layerList).filter(el => {
        const sameList = el.type === 'GisLayerCategory'
          ? layerType === 'overlay'
          : el.attributes.layerType === layerType

        return el.attributes.index === index &&
          sameParent(el, parentId) &&
          el.id !== id &&
          sameList
      })

      console.log('setNewIndex', nextSibling, parentId)
      if (nextSibling.length > 0) {
        const newIndex = (fromOtherList || index < oldIndex) ? index + 1 : index - 1

        dispatch('setNewIndex', {
          id: nextSibling[0].id,
          index: newIndex,
          oldIndex,
          parentId
        })
      }
    },

    /**
     * Updates the categoryId/parentId as well as the 'treeOrder' or 'mapOrder' attribute of a list item
     * @param state
     * @param commit
     * @param {String} layerType 'base' | 'overlay'
     * @param {String} sourceParentId the id of the category the item is moved from
     * @param {String} targetParentId the id of the category the item is moved to
     * @param {String} orderType 'treeOrder' | 'mapOrder'
     */
    updateItemAttrs ({ state, commit }, { layerType, sourceParentId, targetParentId, orderType }) {
      console.log('updateItemAttrs', layerType, sourceParentId, targetParentId, orderType)
      // update parentId (categoryId or parentId)
      if (orderType === 'treeOrder') {
        const targetCategoryId = targetParentId ?? state.apiData.data.id
        const attrKey = state.removedItem.type === 'GisLayerCategory' ? 'parentId' : 'categoryId'

        commit('updateParentId', {
          attr: attrKey,
          value: targetCategoryId
        })
      }

      const parents = ['sourceParent']

      if (targetParentId !== sourceParentId) {
        parents.push('targetParent')
      }

      // update treeOrder/mapOrder attribute for each child of the category
      parents.forEach(p => {
        let parentId = p === 'sourceParent' ? sourceParentId : targetParentId
        let parent = state.layerList[parentId]
        let parentOrder = parent?.attributes[orderType]
        const parentIsRoot = !parentId

        if (parentIsRoot) {
          parentId = state.apiData.data.id
          parentOrder = 1
          parent = state.apiData.data
        }

        const children = [
          ...parent.relationships.categories.data,
          ...parent.relationships.gisLayers.data
        ]

        children.forEach(child => {
          commit('updateOrderAttribute', {
            id: child.id,
            layerType,
            orderType,
            parentId,
            parentOrder
          })
        })
      })
    },

    updateSortOrder ({ state, commit, dispatch }, { parentId, parentOrder }) {
      const pId = parentId ?? state.apiData.data.id
      if (state.currentSorting === 'treeOrder') {
        Object.values(state.layerList)
          .filter(el => {
            const isCategoryorOverlay = el.type === 'GisLayerCategory' || el.attributes.layerType === 'overlay'

            return isCategoryorOverlay && sameParent(el, pId)
          })
          .forEach((el, idx) => {
            commit('updateLayer', {
              id: el.id,
              key: 'treeOrder',
              value: parentOrder + el.attributes.index
            })
          })
      }

      if (state.currentSorting === 'mapOrder') {
        Object.values(state.layerList)
          .filter(el => el.attributes.layerType === 'overlay')
          .forEach(el => {
            commit('updateLayer', {
              id: el.id,
              key: 'mapOrder',
              value: parentOrder + el.attributes.index
            })
          })
      }

      // Always sort base layers. They are not sorted by treeOrder and don't have categories
      Object.values(state.layerList)
        .filter(el => el.type === 'GisLayer' && el.attributes.layerType === 'base')
        .forEach(el => {
          commit('updateLayer', {
            id: el.id,
            key: 'mapOrder',
            value: parentOrder + el.attributes.index
          })
        })
    },

    /**
     * Updates the order of a list item after moving it
     * - removes it from the old position in the source category
     * - adds it to the new position in the target category
     * - updates the parentId/categoryId attribute of the moved item
     * - updates the order attribute of all children of the source and target category
     * @param state
     * @param commit
     * @param dispatch
     * @param {String} listKey 'mapBaseList' | 'mapList' | 'treeBaseList' | 'treeList'
     * @param {String} listType 'map' | 'tree'
     * @param {Number} newIndex
     * @param {Number} oldIndex
     * @param {String} orderType 'mapOrder' | 'treeOrder'
     * @param {String} relationshipType
     * @param {String} sourceParentId
     * @param {String} targetParentId
     */
    updateListSort ({ state, commit, dispatch }, { id, newIndex, oldIndex, orderType, sourceParentId, targetParentId }) {
      const targetIsRoot = !targetParentId
      const sourceIsRoot = !sourceParentId

      // if (orderType === 'treeOrder') {
      dispatch('removeItem', {
        id,
        oldIndex,
        orderType,
        sourceIsRoot,
        sourceParentId
      })

      dispatch('addItem', {
        id,
        newIndex,
        orderType,
        targetIsRoot,
        targetParentId
      })

        // const isCompleteItem = !!state.removedItem.attributes

        /* If item has been moved from a non-root category, it only has properties `id` and `type`, but we need the
           complete item with all its properties */
        // if (!isCompleteItem) {
        //   commit('addMissingProperties', {
        //     listKey
        //   })
        // }
      // }

      // dispatch('updateItemAttrs', {
      //   layerType: listKey.includes('Base') ? 'base' : 'overlay',
      //   orderType: `${listType}Order`,
      //   sourceParentId: sourceIsRoot ? null : sourceParentId,
      //   targetParentId: targetIsRoot ? null : targetParentId
      // })

      // @fixme
      /* If there is just one order (map), the tree order should match the map order */
      // if (listType === 'map' && !hasPermission('feature_map_category')) {
      //   commit('setChildrenFromCategory', {
      //     categoryId: targetIsRoot ? null : targetParentId,
      //     data: state[listKey],
      //     orderType: 'treeOrder',
      //     parentOrder: parentOrder
      //   })
      // }
    },

    deleteElement ({ state, commit }, element) {
      let url = Routing.generate('dplan_api_procedure_layer_delete', {
        layerId: element.id,
        procedureId: state.procedureId
      })

      if (element.route === 'layer_category') {
        url = Routing.generate('dplan_api_procedure_layer_category_delete', {
          layerCategoryId: element.categoryId
        })
      }

      return dpApi.delete(url)
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
     * Get complete object for stripped object containing id and type
     * (both have to match the corresponding 'included' array)
     *
     * @param {Object} element
     * @param {String} element.id
     * @param {String} element.type
     * @returns {Object} element (GisLayer or GisLayerCategory)
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

      return state.apiData.included.find(current => current.id === element.id && current.type === element.type)
    },

    /**
     * Get List of all gisLayers
     *
     * @returns Array|element(gislayers or GisLayerCategory)
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
     * Get List of all gisLayers
     *
     * @returns Array|element(gislayers or GisLayerCategory)
     */
    elementsListByAttribute: state => attribute => {
      if (typeof state.apiData.included === 'undefined') return []

      return state.apiData.included.filter(current => {
        return current.attributes[attribute.type] === attribute.value
      })
    },

    /**
     * Get procedureId originally send to fill the store
     *
     * @returns String|ProcedureId
     */
    procedureId: state => {
      return state.procedureId
    },

    /**
     * Categories and layers mapped to one list and ordered by treeOrder
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
        categoryId = state.apiData.data.id
      }

      // create either list of overlay layers (can have categories) or list of base layers
      //  Filter api response by layer type + categories
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

    //  @TODO check how response looks when no layers or categories exist in a procedure!
    rootId: state => {
      if (hasOwnProp(state.apiData, 'data')) {
        return state.apiData.data.id
      }
      return ''
    },

    /**
     * Categories and layers mapped to one list and ordered by treeOrder
     * @returns: Array|legendList
     */
    elementListForLegendSidebar: state => {
      if (typeof state.apiData.included === 'undefined') return []

      const legends = state.legends
      const includes = state.apiData.included

      const elementList = legends.filter(current => {
        return typeof includes.find(el => el.id === current.layerId) !== 'undefined'
      })

      /* Sort elements by treeOrder before returning the list */
      elementList.sort((a, b) => (a.treeOrder).toString().padEnd(21, '0') - (b.treeOrder).toString().padEnd(21, '0'))
      return elementList
    },

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
     * @param state
     * @returns {Array|*[]}
     */
    treeBaseList: state => {
      return getFlatChildren(state, 'base', 'treeOrder')
    },

    /**
     * Get a list of the root level overlay layers and categories in treeOrder sorted
     *
     * @param state
     * @returns {Array|*[]}
     */
    treeList: state => {
      if (typeof state.apiData.included === 'undefined') return []

      console.log('treeList', state.layerList)

      const children = getDirectChildren(state.layerList, state.apiData.data, 'treeOrder', 'overlay')
      return sortListByAttr(children.map(el => state.layerList[el.id]), 'treeOrder')
    },

    /**
     * Get a list of all base layers in mapOrder sorted
     *
     * @param state
     * @returns {Array|*[]}
     */
    mapBaseList: state => {
      return getFlatChildren(state, 'base', 'mapOrder')
    },

    /**
     * Get a list of all overlay layers in mapOrder sorted
     *
     * @param state
     * @returns {Array|*[]}
     */
    mapList: state => {
      return getFlatChildren(state, 'overlay', 'mapOrder')
    }
  }
}

export default LayersStore
