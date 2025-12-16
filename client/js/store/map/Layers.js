/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { checkResponse, dpApi, hasOwnProp } from '@demos-europe/demosplan-ui'
import { set } from 'vue'

const LayersStore = {

  namespaced: true,
  name: 'layers',

  state: {
    originalApiData: {},
    legends: [],
    loadedLegends: [],
    currentSorting: 'mapOrder',
    activeLayerId: '',
    hoverLayerId: '',
    hoverLayerIconIsHovered: false,
    apiData: {},
    procedureId: '',
    layerStates: {},
    visibilityGroups: {},
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

    saveOriginalState (state, data) {
      state.originalApiData = JSON.parse(JSON.stringify(data))
    },
    setProcedureId (state, data) {
      state.procedureId = data
    },
    setActiveLayerId (state, data) {
      state.activeLayerId = data
    },
    setHoverLayerId (state, data) {
      state.hoverLayerId = data
    },

    setHoverLayerIconIsHovered (state, data) {
      state.hoverLayerIconIsHovered = data
    },

    /**
     * Replace the whole entity
     */
    updateEntity (state, entity) {
      const index = state.apiData.included.findIndex(elem => elem.id === entity.id)
      state.apiData.included[index] = entity
    },

    setLegend (state, data) {
      state.legends.push(data)
    },

    markLegendAsLoaded (state, legendUrl) {
      if (!state.loadedLegends.includes(legendUrl)) {
        state.loadedLegends.push(legendUrl)
      }
    },

    /**
     *
     * @param state
     * @param data|Object {'id': LayerId, 'attribute':AttributeName, 'value':AttributeValue}
     */
    setAttributeForLayer (state, data) {
      const index = state.apiData.included.findIndex(elem => elem.id === data.id)
      if (index >= 0) {
        state.apiData.included[index].attributes[data.attribute] = data.value
      }
    },

    /**
     *
     * @param state
     * @param element|Object {'id': elementId, 'categoryId': parentId, 'relationshipType': categories|gisLayers }
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
     * @param data {'categoryId': null, 'data': value, 'orderType': 'treeOrder', 'parentOrder': this.parentOrderPosition}
     */
    setChildrenFromCategory (state, data) {
      let category = {}

      if (data.categoryId === null) {
        data.categoryId = state.apiData.data.id
        category = state.apiData.data
      } else {
        category = state.apiData.included.find(elem => elem.id === data.categoryId)
      }

      if (category.type === 'GisLayerCategory') {
        // Create new child-elements-arrays (ralationships) for the parent of the given List
        const categories = []
        const layers = []

        data.data.forEach((el, idx) => {
          set(el.attributes, data.orderType, (data.parentOrder * 100) + (idx + 1))
          if (data.orderType === 'treeOrder') {
            if (el.type === 'GisLayerCategory') {
              set(el.attributes, 'parentId', data.categoryId)
              categories.push(el)
            } else if (el.type === 'GisLayer') {
              set(el.attributes, 'categoryId', data.categoryId)
              if (el.attributes.isEnabled) {
                layers.push(el)
              }
            }
          }
        })

        // Update the store-state
        set(category.relationships.categories, 'data', categories)
        set(category.relationships.gisLayers, 'data', layers)
      }
    },

    resetOrder (state) {
    // We have to clone the original state because otherwise after the first reset the reactivity will bound these two objects and will cause changing of originalApiData anytime state.apiData changes
      state.apiData = JSON.parse(JSON.stringify(state.originalApiData))
      state.apiData.included.sort((a, b) => ('' + a.attributes.mapOrder).padEnd(21, 0) - ('' + b.attributes.mapOrder).padEnd(21, 0))
    },

    /**
     * Set state for a layer (visibility, opacity, etc.)
     * @param state
     * @param data|Object { id: string, key: string, value: any }
     */
    setLayerState (state, { id, key, value }) {
      const currentState = state.layerStates[id] || {}

      set(state.layerStates, id, { ...currentState, [key]: value })
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
      set(state, 'isMapLoaded', true)
    }
  },

  actions: {
    get ({ commit, dispatch }, procedureId) {
      commit('setProcedureId', procedureId)

      return dpApi({
        method: 'GET',
        url: Routing.generate('dplan_api_procedure_layer_list',
          {
            procedureId: procedureId,
            include: ['categories', 'gisLayers'].join()
          }
        )
      })
        .then(checkResponse)
        .then(data => {
          commit('updateApiData', data)
          commit('saveOriginalState', data)
          commit('setVisibilityGroups')
          dispatch('buildLegends')
        })
    },

    /**
     * Generate legend URLs for a given layer and commit them to the store
     * @param commit
     * @param layer - The layer object
     */
    generateLayerLegends ({ commit }, layer) {
      if (!layer.attributes.isEnabled) {
        return
      }

      const layerParam = layer.attributes.layers
      const delimiter = (layer.attributes.url.indexOf('?') === -1) ? '?' : '&'
      const legendUrlBase = layer.attributes.url + delimiter

      // Get layer layers
      const layerParamSplit = layerParam.split(',').map(function (item) {
        return item.trim()
      })

      // Add each layer to GetLegendGraphic request
      for (const layerParamItem of layerParamSplit) {
        const legendUrl = legendUrlBase + 'Layer=' + layerParamItem + '&Request=GetLegendGraphic&Format=image/png&version=1.1.1'
        const legend = {
          layerId: layer.id,
          treeOrder: layer.attributes.treeOrder,
          mapOrder: layer.attributes.mapOrder,
          defaultVisibility: layer.attributes.hasDefaultVisibility,
          url: legendUrl
        }

        commit('setLegend', legend)
      }
    },

    /**
     * Get layer legends. Legends need to be fetched for each single gisLayer layer
     * as some map services are not able to group legends
     * @param commit
     * @param dispatch
     * @param getters
     */
    buildLegends ({ commit, dispatch, getters }) {
      // Initialize visibility for overlay layers
      const overlayLayers = getters.gisLayerList('overlay')

      // Handle overlay layers
      for (const layer of overlayLayers) {
        const layerId = layer.id.replaceAll('-', '')

        commit('setLayerState', {
          id: layerId,
          key: 'isVisible',
          value: layer.attributes.hasDefaultVisibility
        })

        dispatch('generateLayerLegends', layer)
      }

      // Initialize visibility for base layers (only one can be active at a time)
      const baseLayers = getters.gisLayerList('base')
      const firstActiveBaseLayer = baseLayers.find(layer => layer.attributes.hasDefaultVisibility)

      baseLayers.forEach(layer => {
        const layerId = layer.id.replaceAll('-', '')
        const isVisible = firstActiveBaseLayer && layer.id === firstActiveBaseLayer.id

        commit('setLayerState', {
          id: layerId,
          key: 'isVisible',
          value: isVisible
        })

        dispatch('generateLayerLegends', layer)
      })
    },

    save ({ state, commit, dispatch }) {
      return dpApi({
        method: 'POST',
        url: Routing.generate('dplan_api_procedure_layer_update', { procedureId: state.procedureId }),
        data: { data: state.apiData }
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
     * Helper function to group legend images by layer type with optional filtering
     * @param state
     * @param getters
     * @param shouldIncludeLegend - Callback function to determine if a legend should be included
     * @returns {{ base: Array, overlay: Array }}
     */
    groupLegendsByLayerType: (state, getters) => (shouldIncludeLegend) => {
      if (state.apiData.included === undefined) {
        return { base: [], overlay: [] }
      }

      const legends = state.legends
      const includes = state.apiData.included

      const grouped = {
        base: [],
        overlay: []
      }

      legends.forEach(legend => {
        // Apply the filter callback
        if (!shouldIncludeLegend(legend)) {
          return
        }

        const layer = includes.find(el => el.id === legend.layerId)
        if (layer && layer.attributes) {
          const layerId = layer.id.replaceAll('-', '')
          const isVisible = getters.isLayerVisible(layerId)

          if (isVisible) {
            const layerType = layer.attributes.layerType
            if (layerType === 'base' || layerType === 'overlay') {
              grouped[layerType].push(legend)
            }
          }
        }
      })

      // Sort each group by treeOrder
      const sortByTreeOrder = (a, b) => {
        return a.treeOrder.toString().padEnd(21, '0') - b.treeOrder.toString().padEnd(21, '0')
      }

      grouped.base.sort(sortByTreeOrder)
      grouped.overlay.sort(sortByTreeOrder)

      return grouped
    },

    /**
     * Get complete object for stripped object containing element id and type
     * (both have to match the corresponding included-array)
     *
     * @param element|Object ( {id, type} )
     * @returns Object|element(gisLayer or gisLayerCategory)
     */
    element: state => element => {
      if (state.apiData.included === undefined) {
        return {}
      }
      if (element.type === 'ContextualHelp') {
        const helpText = state.apiData.included.filter(current => current.attributes.key === ('gislayer.' + element.id))

        if (helpText.length <= 0) {
          return { id: 'no-contextual-help', type: element.type, attributes: { text: '' } }
        } else {
          return helpText[0]
        }
      }
      return state.apiData.included.find(current => {
        return current.id === element.id && current.type === element.type
      })
    },

    /**
     * Get List of all gisLayers
     *
     * @returns Array|element(gisLayers or GisLayerCategory)
     */
    gisLayerList: state => type => {
      if (state.apiData.included === undefined) {
        return []
      }

      return state.apiData.included.filter(current => {
        const putInList = (type) ? (type === current.attributes.layerType) : true

        return (current.type === 'GisLayer' && putInList)
      }).sort((a, b) => (a.attributes.mapOrder).toString().padEnd(21, '0') - (b.attributes.mapOrder).toString().padEnd(21, '0'))
    },

    /**
     * Get List of all gisLayers
     *
     * @returns Array|element(gisLayers or GisLayerCategory)
     */
    elementsListByAttribute: state => attribute => {
      if (state.apiData.included === undefined) {
        return []
      }

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
     * @param categoryId|String
     * @param type|String ('overlay' | base')
     * @param withCategories|Boolean
     *
     * @returns: Array|gisLayers (and Categories)
     */
    elementListForLayerSidebar: state => (categoryId, type, withCategories) => {
      //  Return if there is no data
      if (state.apiData.data === undefined) {
        return []
      }

      //  When called without categoryId, set it to the id of the root category
      if (categoryId === null) {
        categoryId = state.apiData.data.id
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
      if (state.apiData.included === undefined) {
        return []
      }

      const legends = state.legends
      const includes = state.apiData.included
      const elementList = legends.filter(current => {
        return (includes.find(el => el.id === current.layerId) !== undefined)
      })
      /* Sort elements by treeOrder before returning the list */
      elementList.sort((a, b) => (a.treeOrder).toString().padEnd(21, '0') - (b.treeOrder).toString().padEnd(21, '0'))

      return elementList
    },

    /**
     * Get all visible legend images grouped by layer type (base/overlay)
     * Used for rendering legend items so they can attempt to load
     * @returns {{ base: Array, overlay: Array }}
     */
    allVisibleLegendImagesGroupedByLayerType: (state, getters) => {
      // Include all legends (no filtering by load status)
      return getters.groupLegendsByLayerType(() => true)
    },

    /**
     * Get successfully loaded legend images grouped by layer type (base/overlay)
     * Used for determining if heading should be shown
     * @returns {{ base: Array, overlay: Array }}
     */
    legendImagesGroupedByLayerType: (state, getters) => {
      // Only include legends that have successfully loaded
      return getters.groupLegendsByLayerType((legend) => {
        return state.loadedLegends.includes(legend.url)
      })
    },

    visibilityGroupSize: state => visibilityGroupId => {
      if (visibilityGroupId === '' || state.apiData.included === undefined) {
        return 0
      }

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
      if (state.apiData.included === undefined || data.id === '') {
        return ''
      }

      return state.apiData.included.find(current => {
        return current.id === data.id
      })?.attributes[data.attribute]
    },

    minimapLayer: state => {
      if (state.apiData.included === undefined) {
        return {}
      }

      const minimap = state.apiData.included.find(elem => elem.attributes.isMinimap === true)

      if (minimap) {
        return minimap
      } else {
        return {
          id: '',
          attributes: {
            name: 'default'
          }
        }
      }
    },

    /**
     * Check if a layer is currently visible
     * @param state
     * @param layerId {String} Layer ID (without dashes)
     * @returns function(*): *
     */
    isLayerVisible: state => layerId => {
      return state.layerStates[layerId]?.isVisible || false
    }
  }
}

export default LayersStore
