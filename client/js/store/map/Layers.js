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
  hasOwnProp,
  hasPermission
} from '@demos-europe/demosplan-ui'

/**
 * Sorts flat list by the given attribute
 * @param {array} list
 * @param {string} attr 'mapOrder' | 'treeOrder'
 * @param {Object} apiData
 * @return {*}
 */
const sortListByAttr = (list, attr, apiData) => {
  return list.sort((a, b) => {
      const formattedOrderA = a.attributes[attr]
        .toString()
        .padEnd(21, '0')

      const formattedOrderB = b.attributes[attr]
        .toString()
        .padEnd(21, '0')

      return formattedOrderA - formattedOrderB
    })
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
    list: [],
    mapList: [],
    mapBaseList: [],
    originalApiData: {},
    procedureId: '',
    removedItem: {},
    treeList: [],
    treeBaseList: [],
    visibilityGroups: {},
  },

  mutations: {
    /**
     * Adds all properties from `included` to an item in relationship format (type, id)
     * @param state
     * @param {String} listKey
     */
    addMissingProperties (state, { listKey }) {
      const completeItem = state.apiData.included
        .filter(el => el.type !== 'ContextualHelp')
        .find(el => el.id === state.removedItem.id)
      state.removedItem = completeItem
    },

    /**
     * Adds child to non-root category
     * @param state
     * @param {Object} data
     * @param {Number} data.newIndex
     * @param {String} data.orderType 'treeOrder' | 'mapOrder'
     * @param {String} data.relationshipType 'gisLayers' | 'categories'
     * @param {String} data.targetParentId
     */
    addToCategoryRelationships (state, { newIndex, orderType, relationshipType, targetParentId }) {
      // add item to relationships
      const targetParent = state.apiData.included.find(el => el.id === targetParentId)
      targetParent.relationships[relationshipType].data.push({ id: state.removedItem.id, type: state.removedItem.type })

      // update index attribute of all children of the targetParent
      let targetParentChildren = [...state.apiData.included.filter(current => {
        const parentKey = (current.type === 'GisLayerCategory') ? 'parentId' : 'categoryId'
        const isChild = current.attributes[parentKey] === targetParentId
        const isRemovedItem = current.id === state.removedItem.id

        return isChild && !isRemovedItem
      })]

      if (targetParentChildren.length > 0) {
        targetParentChildren = sortListByAttr(targetParentChildren, orderType, state.apiData)
      }

      targetParentChildren.splice(newIndex, 0, state.removedItem)

      targetParentChildren.forEach((child, idx) => {
        child.attributes.index = idx
      })
    },

    /**
     * Adds child to root category
     * @param state
     * @param {Object} data
     * @param {String} data.listKey 'mapBaseList' | 'mapList' | 'treeBaseList' | 'treeList'
     * @param {Number} data.newIndex
     * @param {String} data.relationshipType 'categories' | 'gisLayers'
     */
    addToRoot (state, { listKey, newIndex, relationshipType }) {
      const { id, type } = state.removedItem
      const relationshipItem = {
        type,
        id
      }

      // add item to root relationships
      state.apiData.data.relationships[relationshipType].data.splice(newIndex, 0, relationshipItem)

      // update index for all children of the root category
      state[listKey].splice(newIndex, 0, state.removedItem)
      state[listKey].forEach((el, idx) => {
        el.attributes.index = idx
      })
    },

    /**
     * Filters layers by type ('base' | 'overlay' )
     * @param state
     * @param data
     * @param data.listKey 'mapBaseList' | 'mapList'
     * @param data.type 'base' | 'overlay'
     * @return {T[]|*[]}
     */
    buildMapList (state, data) {
      if (typeof state.apiData.included === 'undefined') {
        return []
      }

      const { listKey, type } =  data

      const mapList = state.apiData.included
        .filter(current => {
          const putInList = type ? type === current.attributes.layerType : true

          return current.type === 'GisLayer' && putInList
        })

      if (mapList.length > 0) {
        state[listKey] = sortListByAttr(mapList, 'mapOrder', state.apiData)
      } else {
        state[listKey] = []
      }
    },

    /**
     * Removes child from non-root category
     * @param state
     * @param {Object} data
     * @param {String} data.listKey 'treeBaseList' | 'treeList'
     * @param {Number} data.oldIndex
     * @param {String} data.orderType 'treeOrder' | 'mapOrder'
     * @param {String} data.relationshipType 'gisLayers' | 'categories'
     * @param {String} data.sourceParentId
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
     * @param data
     * @param {String} data.listKey
     * @param {Number} data.oldIndex
     * @param {String} data.relationshipType 'categories' | 'gisLayers'
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

    saveOriginalState (state, data) {
      state.originalApiData = JSON.parse(JSON.stringify(data))
    },

    setActiveLayerId (state, data) {
      state.activeLayerId = data
    },

    setApiData (state, data) {
      state.apiData = data
    },

    setDraggableOptions (state, data) {
      state.draggableOptions = data
    },

    setDraggableOptionsForBaseLayer (state, data) {
      state.draggableOptionsForBaseLayer = data
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
     * Updates the categoryId/parentId as well as the 'treeOrder' or 'mapOrder' attribute of a list item
     * @param state
     * @param data
     * @param {String} data.layerType 'base' | 'overlay'
     * @param {String} data.sourceParentId the id of the category the item is moved from
     * @param {String} data.targetParentId the id of the category the item is moved to
     * @param {Number} data.targetParentOrder
     * @param {String} data.orderType 'treeOrder' | 'mapOrder'
     */
    updateItemAttrs (state, data) {
      const {
        layerType,
        orderType,
        sourceParentId,
        targetParentId,
        targetParentOrder
      } = data

      // update parentId (categoryId or parentId)
      if (orderType === 'treeOrder') {
        const parentId = !targetParentId ? state.apiData.data.id : targetParentId
        const attrKey = state.removedItem.type === 'GisLayerCategory' ? 'parentId' : 'categoryId'
        state.removedItem.attributes[attrKey] = parentId
      }

      // update treeOrder/mapOrder
      let oldParentId = sourceParentId
      const sourceIsRoot = !sourceParentId

      // if sourceParentId is null or undefined, take root id
      if (sourceIsRoot) {
        oldParentId = state.apiData.data.id
      }

      // get source parent treeOrder/mapOrder property
      const sourceParentOrder = sourceIsRoot
        ? 1
        : state.apiData.included.find(el => el.id === sourceParentId).attributes[orderType]

      // get all children of source category
      const sourceParent = sourceIsRoot ? state.apiData.data : state.apiData.included.find(el => el.id === sourceParentId)
      let sourceParentChildren = [
        ...sourceParent.relationships.categories.data,
        ...sourceParent.relationships.gisLayers.data
      ]

      // get complete item from apiData.included, then update treeOrder/mapOrder property
      sourceParentChildren.forEach(child => {
        const completeItem = state.apiData.included.find(el => el.id === child.id)
        const isChildOfGivenCategory = completeItem.attributes.categoryId === oldParentId || completeItem.attributes.parentId === oldParentId
        const isCategory = completeItem.type === 'GisLayerCategory'
        const isLayerOfGivenType = completeItem.type === 'GisLayer' && completeItem.attributes.layerType === layerType
        const isEnabledLayer = completeItem.type === 'GisLayer' && completeItem.attributes.isEnabled === true

        if (isChildOfGivenCategory && (isCategory || (isLayerOfGivenType && isEnabledLayer))) {
          completeItem.attributes[orderType] = (sourceParentOrder * 100) + (completeItem.attributes.index + 1)
        }
      })

      if (targetParentId !== sourceParentId) {
        let newParentId = targetParentId
        const targetIsRoot = !targetParentId

        // if targetParentId is null or undefined, take root id
        if (targetIsRoot) {
          newParentId = state.apiData.data.id
        }

        // get target parent treeOrder/mapOrder property
        const newParentOrder = targetIsRoot
          ? 1
          : targetParentOrder

        // get all children of target category
        const targetParent = targetIsRoot ? state.apiData.data : state.apiData.included.find(el => el.id === targetParentId)
        let targetParentChildren = [
          ...targetParent.relationships.categories.data,
          ...targetParent.relationships.gisLayers.data
        ]

        // get complete item from apiData.included, then update treeOrder/mapOrder property
        targetParentChildren.forEach(child => {
          const completeItem = state.apiData.included.find(el => el.id === child.id)
          const isChildOfGivenCategory = completeItem.attributes.categoryId === newParentId || completeItem.attributes.parentId === newParentId
          const isCategory = completeItem.type === 'GisLayerCategory'
          const isLayerOfGivenType = completeItem.type === 'GisLayer' && completeItem.attributes.layerType === layerType
          const isEnabledLayer = completeItem.type === 'GisLayer' && completeItem.attributes.isEnabled === true

          if (isChildOfGivenCategory && (isCategory || (isLayerOfGivenType && isEnabledLayer))) {
            completeItem.attributes[orderType] = (newParentOrder * 100) + (completeItem.attributes.index + 1)
          }
        })
      }
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
    addItem ({ state, commit }, { listKey, newIndex, orderType, relationshipType, targetIsRoot, targetParentId }) {
      if (targetIsRoot) {
        commit('addToRoot', {
          listKey,
          newIndex,
          relationshipType
        })
      }

      if (!targetIsRoot) {
        commit('addToCategoryRelationships', {
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
    buildChildrenListForTreeCategory ({ state, commit }, { categoryId = null, type, withCategories }) {
      if (typeof state.apiData.data === 'undefined') {
        return []
      }

      const listKey = type === 'base' ? 'treeBaseList' : 'treeList'
      let categoryOrRootId = categoryId
      //  If called without categoryId, set it to the id of the root category
      if (categoryId === null) {
        categoryOrRootId = state.apiData.data.id
      }

      // Get category children with all properties from included
      const navigationList = state.apiData.included.filter(current => {
        //  Only GisLayer has an attributes.layerType so this one will be false for categories + contextual help
        const isLayer = type === current.attributes.layerType
        const isCategory = current.type === 'GisLayerCategory'

        if (!isLayer && !isCategory) {
          return false
        }

        const shouldIncludeCategories = withCategories

        /*
         *  For categories, their parent category is determined by the field `parentId`
         *  while the parent category of layers is called `categoryId`
         */
        const parentKey = current.type === 'GisLayerCategory'
          ? 'parentId'
          : 'categoryId'
        const isInGivenCategory = current.attributes[parentKey] === categoryOrRootId

        return isInGivenCategory && ((shouldIncludeCategories && isCategory) || isLayer)
      })

      //  Sort elements by treeOrder and store them in state
      if (navigationList.length > 0) {
        commit('sortList', {
          listKey,
          orderType: 'treeOrder',
          data: navigationList
        })
      }

      // update item index, needed for updating treeOrder/mapOrder in updateItemAttrs
      state[listKey].forEach((el, idx) => {
        commit('setAttributeForLayer', {
          id: el.id,
          attribute: 'index',
          value: idx
        })
      })
    },

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

      if (listKey === 'mapList' || listKey === 'mapBaseList') {
        commit('buildMapList', {
          listKey: listKey,
          type: type
        })
      }

      if (listKey === 'treeList' || listKey === 'treeBaseList') {
        dispatch('buildChildrenListForTreeCategory', data)
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
          dispatch('buildLegends')

          const listTypes = ['map', 'tree', 'mapBase', 'treeBase']
          listTypes.forEach(type => dispatch('setListByType', { listType: type }))
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
      if (sourceIsRoot) {
        commit('removeFromRoot', {
          listKey,
          oldIndex,
          relationshipType
        })
      }

      if (!sourceIsRoot) {
        commit('removeFromCategoryRelationships', {
          listKey,
          oldIndex,
          orderType,
          relationshipType,
          sourceParentId
        })
      }
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
     *
     * @param state
     * @param commit
     * @param dispatch
     * @param {Object} data
     * @param {String} data.listKey 'mapBaseList' | 'mapList' | 'treeBaseList' | 'treeList'
     * @param {String} data.listType 'map' | 'tree'
     * @param {Number} data.newIndex
     * @param {Number} data.oldIndex
     * @param {String} data.orderType 'mapOrder' | 'treeOrder'
     * @param {String} data.parentOrder
     * @param {String} data.relationshipType
     * @param {String} data.sourceParentId
     * @param {String} data.targetParentId
     */
    updateListSort ({state, commit, dispatch}, data) {
      const {
        listKey,
        listType,
        newIndex,
        oldIndex,
        orderType,
        parentOrder,
        relationshipType,
        sourceParentId,
        targetParentId
      } = data
      const targetIsRoot = !targetParentId
      const sourceIsRoot = !sourceParentId

      dispatch('removeItem', {
        listKey,
        oldIndex,
        orderType,
        relationshipType,
        sourceIsRoot,
        sourceParentId
      })

      dispatch('addItem', {
        listKey,
        newIndex,
        orderType,
        relationshipType,
        targetIsRoot,
        targetParentId
      })

      const isCompleteItem = !!state.removedItem.attributes

      // if item has been moved from a non-root category, it only has properties `id` and `type`, but on root level, it
      // should have attributes, too
      if (!isCompleteItem) {
        commit('addMissingProperties', {
          listKey
        })
      }

      const targetParent = !targetParentId
        ? state.apiData.data
        : state.apiData.included.find(el => el.id === targetParentId)
      const layerType = listKey.includes('Base') ? 'base' : 'overlay'

      commit('updateItemAttrs', {
        layerType,
        orderType: `${listType}Order`,
        sourceParentId: sourceIsRoot ? null : sourceParentId,
        targetParentId: targetIsRoot ? null : targetParentId,
        targetParentOrder: targetParent.attributes.treeOrder
      })

      // @fixme
      // if (listType === 'map') {
      //   commit('setChildrenFromCategory',{
      //     categoryId: targetIsRoot ? null : targetParentId,
      //     data: state[listKey],
      //     orderType: listType === 'map' ? 'mapOrder' : 'treeOrder',
      //     parentOrder: parentOrder
      //   })
      // }

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
     * @param state
     * @param commit
     * @param {String} parentId
     * @param {String} orderType
     * @return {function(*, *): *}
     */
    directChildren: state => (parentId, orderType) => {
      let children = state.apiData.included.filter(current => {
        const parentKey = (current.type === 'GisLayerCategory') ? 'parentId' : 'categoryId'
        const isChild = current.attributes[parentKey] === parentId

        return isChild
      })

      if (children.length > 0) {
        children = sortListByAttr(children, orderType, state.apiData)
      }

      return children
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
     * @param data|Object {'elementId', 'attribute'}
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
    }
  }
}

export default LayersStore
