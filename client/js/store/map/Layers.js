/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { checkResponse, dpApi, hasOwnProp } from '@demos-europe/demosplan-ui'

const LayersStore = {

  namespaced: true,

  name: 'Layers',

  state: {
    originalApiData: {},
    legends: [],
    currentSorting: 'mapOrder',
    activeLayerId: '',
    hoverLayerId: '',
    hoverLayerIconIsHovered: false,
    apiData: {},
    procedureId: '',
    layerStates: {},
    visibilityGroups: {},
    visibleVisibilityGroups: [],
    draggableOptions: {},
    draggableOptionsForBaseLayer: {},
    isMapLoaded: false
  },

  mutations: {

    updateApiData (state, data) {
      state.apiData = data
    },

    setDraggableOptions (state, data) {
      state.draggableOptions = data
    },

    setDraggableOptionsForBaseLayer (state, data) {
      state.draggableOptionsForBaseLayer = data
    },

    setProcedureId (state, data) {
      state.procedureId = data
    },
    setActiveLayerId (state, data) {
      state.activeLayerId = data
    },

    /**
     * Removes an element (layer or category) from the store and its parent relationships
     *
     * @param {Object} element - Element to remove
     * @param {string} element.id - ID of the element to remove
     * @param {string} element.categoryId - ID of the parent category
     * @param {string} element.relationshipType - Type of relationship ('categories' or 'gisLayers')
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
    },

    /**
     * Sets a specific state property for a layer
     *
     * @param {Object} payload - Payload object
     * @param {string} payload.id - Layer ID
     * @param {string} payload.key - Property key to set
     * @param {*} payload.value - Value to set
     *
     * @returns {void}
     */
    setLayerState (state, { id, key, value }) {
      if (!state.layerStates[id]) {
        state.layerStates[id] = { }
      }

      state.layerStates[id][key] = value
    },

    /**
     * Updates a top-level state property
     *
     * @param {Object} payload - Payload object
     * @param {string} payload.key - State property key
     * @param {*} payload.value - New value
     *
     * @returns {void}
     */
    updateState (state, { key, value }) {
      state[key] = value
    },

    /**
     * Saves a deep copy of the original API data for reset functionality
     *
     * @param {Object} data - API data to save
     *
     * @returns {void}
     */
    saveOriginalState (state, data) {
      state.originalApiData = JSON.parse(JSON.stringify(data))
    },

    /**
     * Sets the ID of the currently hovered layer
     *
     * @param {string} id - Layer ID
     *
     * @returns {void}
     */
    setHoverLayerId (state, id) {
      state.hoverLayerId = id
    },

    /**
     * Sets whether the hover layer icon is currently being hovered
     *
     * @param {boolean} isHovered - Hover state
     *
     * @returns {void}
     */
    setHoverLayerIconIsHovered (state, isHovered) {
      state.hoverLayerIconIsHovered = isHovered
    },

    /**
     * Adds a legend object to the legends array
     *
     * @param {Object} data - Legend object with layerId, treeOrder, mapOrder, defaultVisibility, url
     *
     * @returns {void}
     */
    setLegend (state, data) {
      state.legends.push(data)
    },

    /**
     * Sets a specific attribute value for a layer
     *
     * @param {Object} data - Data object
     * @param {string} data.id - Layer ID
     * @param {string} data.attribute - Attribute name to set
     * @param {*} data.value - Attribute value
     *
     * @returns {void}
     */
    setAttributeForLayer (state, data) {
      const index = state.apiData.included.findIndex(elem => elem.id === data.id)

      if (index >= 0) {
        state.apiData.included[index].attributes[data.attribute] = data.value
      }
    },

    /**
     * Replaces an entire entity in the included array
     *
     * @param {Object} entity - New entity object with id property
     *
     * @returns {void}
     */
    updateEntity (state, entity) {
      const index = state.apiData.included.findIndex(elem => elem.id === entity.id)
      state.apiData.included[index] = entity
    },

    /**
     * Updates the children of a category including their order positions and parent relationships
     *
     * @param {Object} data - Data object
     * @param {string|null} data.oldCategoryId - old Category ID (null for root category)
     * @param {string|null} data.newCategoryId - new Category ID (null for root category)
     * @param {string} data.orderType - Order type ('treeOrder')
     * @param {number} data.parentOrder - Parent order position
     * @param {Object} [data.movedElement] - Optional moved element data for drag & drop
     *
     * @returns {void}
     */
    setChildrenFromCategory (state, data) {
      if (!data.movedElement) {
        console.error('No movedElement provided, cannot update order')

        return
      }

      const rootEl = state.apiData.data[0]
      // Get the old and new categories
      const oldCategory = (data.oldCategoryId === null || data.oldCategoryId === rootEl.id) ? rootEl : state.apiData.included.find(elem => elem.id === data.oldCategoryId)
      const newCategory = (data.newCategoryId === null || data.newCategoryId === rootEl.id) ? rootEl : state.apiData.included.find(elem => elem.id === data.newCategoryId)
      const currentElement = state.apiData.included.find(el => el.id === data.movedElement.id)
      const { parentIdKey, relationshipKey } = currentElement.type === 'GisLayerCategory' ? { parentIdKey: 'parentId', relationshipKey: 'categories' } : { parentIdKey: 'categoryId', relationshipKey: 'gisLayers' }
      const isBaseLayer = currentElement.attributes.layerType === 'base'

      if (!oldCategory || !newCategory || !currentElement) {
        console.error('Invalid categories or current element, cannot update order')

        return
      }

      // List all elements with the givven categoryId
      const childElements = state.apiData.included
        .filter(el => {
          let isInList = 0

          if (el.type === 'GisLayerCategory' ? el.attributes.parentId === oldCategory.id : el.attributes.categoryId === oldCategory.id) {
            isInList++
          }

          /* We want only Layer from the same kind as the current element in the List.
           * And Categories are always for the overlay layers
           * This is necessary because both base and overlay layers have the same root Category
           */
          if (isBaseLayer === (el.attributes.layerType === 'base') || (!isBaseLayer && el.attributes.layerType === undefined)) {
            isInList++
          }

          return isInList > 1
        })
        .sort((a, b) => a.attributes[data.orderType] - b.attributes[data.orderType])

      // If Element is not in the list, we have to remove it from the old parent ...
      if (oldCategory.id !== newCategory.id) {
        oldCategory.relationships[relationshipKey].data.splice(data.movedElement.oldIndex, 1)
        // And add it to the new List ...
        newCategory.relationships[relationshipKey].data.splice(data.movedElement.newIndex, 0, ({ id: currentElement.id, type: currentElement.type }))
        // ... And set the new parentId or categoryId for the current element
        currentElement.attributes[parentIdKey] = newCategory.id
        // ... Otherwise we have to move it
      } else if (childElements.find(el => el.id === data.movedElement.id) !== undefined) {
        childElements.splice(data.movedElement.newIndex, 0, childElements.splice(data.movedElement.oldIndex, 1)[0])
      }

      // Set new order positions for all child elements
      childElements.forEach((el, idx) => {
        el.attributes[data.orderType] = (data.parentOrder * 100) + idx + 1
      })
    },

    /**
     * Resets the layer order to the original API data and sorts by mapOrder
     *
     * @returns {void}
     */
    resetOrder (state) {
    // We have to clone the original state because otherwise after the first reset the reactivity will bound these two objects and will cause changing of originalApiData anytime state.apiData changes
      state.apiData = JSON.parse(JSON.stringify(state.originalApiData))
      state.apiData.included.sort((a, b) => ('' + a.attributes.mapOrder).padEnd(21, 0) - ('' + b.attributes.mapOrder).padEnd(21, 0))
    },

    /**
     * Builds the visibility groups map from elements that have visibilityGroupId
     *
     * @returns {void}
     */
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

    /**
     * Sets which layer should be used as the minimap base layer
     *
     * @param {string} id - Layer ID to set as minimap (empty string to unset)
     *
     * @returns {void}
     */
    setMinimapBaseLayer (state, id) {
      const previousMinimap = state.apiData.included.find(elem => elem.attributes.isMinimap === true)
      if (previousMinimap) { previousMinimap.attributes.isMinimap = false }

      if (id === '') { return }

      const newMinimap = state.apiData.included.find(elem => elem.id === id)
      newMinimap.attributes.isMinimap = true
    },

    /**
     * Marks the map as loaded
     *
     * @returns {void}
     */
    setIsMapLoaded (state) {
      state.isMapLoaded = true
    }

  },

  actions: {
    /**
     * Fetches layer data from the API for a specific procedure
     *
     * @param {string} procedureId - Procedure ID to fetch layers for
     *
     * @returns {Promise} API response promise
     */
    get ({ commit, dispatch }, procedureId) {
      commit('setProcedureId', procedureId)

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
            'treeOrder'
          ].join(),
          GisLayer: [
            'canUserToggleVisibility',
            'categoryId',
            'hasDefaultVisibility',
            'isBaseLayer',
            'isBplan',
            'isEnabled',
            'isMinimap',
            'isPrint',
            'isScope',
            'layers',
            'layerType',
            'mapOrder',
            'name',
            'opacity',
            'projectionLabel',
            'treeOrder',
            'url',
            'visibilityGroupId'
          ].join()
        },
        filter: {
          name: {
            condition: {
              path: 'parentId',
              operator: 'IS NULL'
            }
          }
        }
      }))
        .then(checkResponse)
        .then(data => {
          commit('updateApiData', data)
          commit('saveOriginalState', data)
          commit('setVisibilityGroups')
          dispatch('buildLegends')
        })
    },

    /**
     * Builds legend URLs for all overlay layers using GetLegendGraphic requests
     *
     * @returns {void}
     */
    buildLegends ({ commit, getters }) {
      const layers = getters.gisLayerList('overlay')

      for (const layer of layers) {
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

    /**
     * Saves all layers and categories to the API
     *
     * @returns {void}
     */
    saveAll ({ state, dispatch }) {
      /* Save each GIS layer and GIS layer category with its relationships */
      state.apiData.included.forEach(el => {
        dispatch('save', el)
      })
    },

    /**
     * Saves a single layer or category resource to the API
     *
     * @param {Object} resource - Resource to save (GisLayer or GisLayerCategory)
     *
     * @returns {Promise} API response promise
     */
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
              hasDefaultVisibility
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
     * Deletes a layer or category element via API and removes it from store
     *
     * @param {Object} element - Element to delete
     * @param {string} element.id - Element ID
     * @param {string} element.route - Route type ('layer_category' for categories)
     *
     * @returns {Promise} API response promise
     */
    deleteElement ({ state, commit }, element) {
      let currentType = 'GisLayer'
      let id = element.id

      if (element.route === 'layer_category') {
        currentType = 'GisLayerCategory'
        id = element.categoryId
      }

      return dpApi.delete(Routing.generate('api_resource_delete', {
        resourceType: currentType,
        resourceId: id
      }))
        .then(response => this.api.checkResponse(response, {
          204: {
            text: Translator.trans('confirm.gislayer.delete'),
            type: 'confirm'
          }
        }))
        .then(() => {
          commit('removeElement', {
            id: element.id,
            categoryId: element.categoryId,
            relationshipType: element.relationshipType
          })
        })
    },

    /**
     * Recursively finds the topmost parent category for a given layer
     *
     * @param {Object} payload - Payload object
     * @param {Object} payload.layer - Layer object to find parent for
     *
     * @returns {string} Root category ID
     */
    findMostParentCategory ({ dispatch, state }, { layer }) {
      const rootId = state.apiData[0].id
      const parentId = layer.attributes.categoryId

      if (parentId === rootId) {
        return parentId
      } else {
        const parent = state.apiData.included.find(el => el.id === parentId)

        if (!parent) {
          console.error('Parent category not found for layer:', layer.id)
          return rootId
        }

        return dispatch('findMostParentCategory', parent)
      }
    },

    /**
     * Recursively toggles visibility of a category and all its children
     *
     * @param {Object} payload - Payload object
     * @param {string} payload.id - Category ID
     * @param {boolean} payload.value - Visibility value
     *
     * @returns {void}
     */
    toggleCategoryAndItsChildren ({ dispatch, commit, state }, { id, value }) {
      const el = state.apiData.included.find(el => el.id === id)

      commit('setLayerState', { id: el.id, key: 'isVisible', value })

      if (el.type === 'GisLayerCategory') {
        el.relationships?.categories?.data.forEach(cat => {
          dispatch('toggleCategoryAndItsChildren', { id: cat.id, value })
        })

        el.relationships?.gisLayers?.data.forEach(layer => {
          dispatch('toggleCategoryAndItsChildren', { id: layer.id, value })
        })
      }
    },

    /**
     * Toggles base layer visibility (only one base layer can be visible at a time)
     *
     * @param {Object} payload - Payload object
     * @param {string} payload.id - Base layer ID
     * @param {boolean} payload.value - Visibility value
     *
     * @returns {void}
     */
    toggleBaselayer ({ dispatch, state, commit }, { id, value }) {
      // You can't toggle a base layer if it is already visible
      if (!value) {
        state.apiData.included.forEach(potetialBaseLayer => {
          if (potetialBaseLayer.attributes.layerType === 'base' && potetialBaseLayer.id !== id) {
            commit('setLayerState', { id: potetialBaseLayer.id, key: 'isVisible', value: false })
          }

          if (potetialBaseLayer.attributes.layerType === 'base' && potetialBaseLayer.id === id) {
            commit('setLayerState', { id: potetialBaseLayer.id, key: 'isVisible', value: true })
          }
        })
      }
    },

    /**
     * Toggles visibility for all layers in a visibility group
     *
     * @param {Object} payload - Payload object
     * @param {string} payload.visibilityGroupId - Visibility group ID
     * @param {boolean} payload.value - Visibility value
     *
     * @returns {void}
     */
    toggleVisiblityGroup ({ dispatch, state, commit }, { visibilityGroupId, value }) {
      state.apiData.included.forEach(potetialGroupMember => {
        if (potetialGroupMember.attributes.visibilityGroupId === visibilityGroupId) {
          commit('setLayerState', { id: potetialGroupMember.id, key: 'isVisible', value })
        }
      })
    },

    /**
     * Updates layer visibility with various logic modes (exclusive, grouped, etc.)
     *
     * @param {Object} payload - Payload object
     * @param {string} payload.id - Layer ID
     * @param {boolean} payload.value - Visibility value
     * @param {boolean} payload.layerGroupsAlternateVisibility - Whether to use alternate visibility mode
     * @param {boolean} payload.exclusively - Whether this is exclusive (base layer) mode
     *
     * @returns {void}
     */
    updateLayerVisibility ({ dispatch, state, commit }, { id, value, _layerGroupsAlternateVisibility, exclusively }) {
      const layerGroupsAlternateVisibility = true
      const layer = state.apiData.included.find(layer => layer.id === id)

      if (exclusively) {
        dispatch('toggleBaselayer', { id, value })
      } else if (layer.attributes.visibilityGroupId) {
        dispatch('toggleVisiblityGroup', { visibilityGroupId: layer.attributes.visibilityGroupId, value })
      } else if (layerGroupsAlternateVisibility && value && layer.attributes.layerType === 'overlay') {
        /*
         * If the layer is an overlay and the flag hasAlternateVisibility is set, we need to hide all other categories and category-members,
         * that don't belong to the category of the current layer
         */
        const toggledCat = dispatch('findMostParentCategory', layer) || {}

        dispatch('toggleCategoryAndItsChildren', { id: toggledCat.id, value: true })

        state.apiData.data[0].relationships.categories.data
          .filter(cat => cat.id !== toggledCat.id)
          .forEach(cat => {
            dispatch('toggleCategoryAndItsChildren', { id: cat.id, value: false })
          })
      } else {
        commit('setLayerState', { id, key: 'isVisible', value })
      }
    }
  },

  getters: {
    /**
     * Gets the complete object for an element by ID and type
     *
     * @param {Object} element - Element identifier
     * @param {string} element.id - Element ID
     * @param {string} element.type - Element type
     *
     * @returns {Object} Complete element object or empty object if not found
     */
    element: state => element => {
      if (typeof state.apiData.included === 'undefined') return {}
      if (element.type === 'ContextualHelp') {
        const helpText = state.apiData.included.filter(current => current.attributes.key === ('gislayer.' + element.id))
        if (helpText.length <= 0) {
          return { id: 'no-contextual-help', type: element.type, attributes: { text: '' } }
        } else {
          return helpText[0]
        }
      }
      return state.apiData.included.filter(current => {
        return current.id === element.id && current.type === element.type
      })[0]
    },

    /**
     * Gets a filtered and sorted list of GIS layers
     *
     * @param {string} [type] - Layer type filter ('overlay', 'base', etc.)
     *
     * @returns {Array} Array of GisLayer objects sorted by mapOrder
     */
    gisLayerList: state => type => {
      if (typeof state.apiData.included === 'undefined') return []
      return state.apiData.included.filter(current => {
        const putInList = (type) ? (type === current.attributes.layerType) : true
        return (current.type === 'GisLayer' && putInList)
      }).sort((a, b) => (a.attributes.mapOrder).toString().padEnd(21, '0') - (b.attributes.mapOrder).toString().padEnd(21, '0'))
    },

    /**
     * Gets elements filtered by a specific attribute value
     *
     * @param {Object} attribute - Attribute filter
     * @param {string} attribute.type - Attribute name
     * @param {*} attribute.value - Attribute value to match
     *
     * @returns {Array} Array of matching elements
     */
    elementsListByAttribute: state => attribute => {
      if (typeof state.apiData.included === 'undefined') return []
      return state.apiData.included.filter(current => {
        return current.attributes[attribute.type] === attribute.value
      })
    },

    /**
     * Gets the visibility state of a layer
     *
     * @param {string} layerId - Layer ID
     *
     * @returns {boolean} Layer visibility state
     */
    isLayerVisible: state => layerId => {
      return state.layerStates[layerId]?.isVisible || false
    },

    /**
     * Gets the visibility state of a visibility group
     *
     * @param {string} visibilityGroupId - Visibility group ID
     *
     * @returns {boolean} Visibility group state
     */
    isVisibilityGroupVisible: state => visibilityGroupId => {
      return state.visibleVisibilityGroups.includes(visibilityGroupId)
    },

    /**
     * Gets the procedure ID originally used to fill the store
     *
     * @returns {string} Procedure ID
     */
    procedureId: state => {
      return state.procedureId
    },

    /**
     * Gets elements for layer sidebar, filtered and sorted by treeOrder
     *
     * @param {string|null} categoryId - Category ID (null for root)
     * @param {string} type - Layer type ('overlay' or 'base')
     * @param {boolean} withCategories - Whether to include categories
     *
     * @returns {Array} Array of elements sorted by treeOrder
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

      //  Filter api response by layer type + categories
      const elementList = state.apiData.included.filter(current => {
        //  Only GisLayer has an attributes.layerType so this one will be false for categories + contextual help
        const putLayerInList = (type === current.attributes.layerType)

        //  Check if categories should be included and this one is a category
        const putCategoriesInList = (withCategories === true) ? (current.type === 'GisLayerCategory') : false

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
     * Gets the root category ID
     *
     * @returns {string} Root category ID or empty string
     */
    rootId: state => {
      if (hasOwnProp(state.apiData, 'data')) {
        return state.apiData.data[0].id
      }
      return ''
    },

    /**
     * Gets legend elements for the legend sidebar, sorted by treeOrder
     *
     * @returns {Array} Array of legend objects sorted by treeOrder
     */
    elementListForLegendSidebar: state => {
      if (typeof state.apiData.included === 'undefined') return []
      const legends = state.legends
      const includes = state.apiData.included

      const elementList = legends.filter(current => {
        return (includes.find(el => el.id === current.layerId) !== 'undefined')
      })
      /* Sort elements by treeOrder before returning the list */
      elementList.sort((a, b) => (a.treeOrder).toString().padEnd(21, '0') - (b.treeOrder).toString().padEnd(21, '0'))
      return elementList
    },

    /**
     * Gets the number of elements in a visibility group
     *
     * @param {string} visibilityGroupId - Visibility group ID
     *
     * @returns {number} Number of elements in the group
     */
    visibilityGroupSize: state => visibilityGroupId => {
      if (visibilityGroupId === '' || typeof state.apiData.included === 'undefined') return 0
      return state.apiData.included.filter(current => {
        return current.attributes.visibilityGroupId === visibilityGroupId
      }).length
    },

    /**
     * Gets a specific attribute value for an element
     *
     * @param {Object} data - Data object
     * @param {string} data.id - Element ID
     * @param {string} data.attribute - Attribute name
     *
     * @returns {*} Attribute value or empty string if not found
     */
    attributeForElement: state => data => {
      if (typeof state.apiData.included === 'undefined' || data.id === '') return ''
      return state.apiData.included.filter(current => {
        return current.id === data.id
      })[0].attributes[data.attribute]
    },

    /**
     * Gets the layer designated as the minimap layer
     *
     * @returns {Object} Minimap layer object or default object if none found
     */
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
