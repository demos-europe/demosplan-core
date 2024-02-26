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
  name: 'layers',

  state: {
    originalApiData: {},
    legends: [],
    currentSorting: 'mapOrder',
    activeLayerId: '',
    hoverLayerId: '',
    hoverLayerIconIsHovered: false,
    apiData: {},
    procedureId: '',
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
    // We have to clone the original state because otherwise after the first reset the reactivity will bound these two objects and will cause changing of originalApiData anytime state.apiData changes
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
    get ({ commit, dispatch }, procedureId) {
      commit('setProcedureId', procedureId)

      return dpApi({
        method: 'get',
        url: Routing.generate(
          'dplan_api_procedure_layer_list',
          {
            procedureId: procedureId,
            include: ['categories', 'gisLayers'].join()
          }
        ),
        responseType: 'json'
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

    save ({ state, commit, dispatch }) {
      return dpApi({
        method: 'post',
        url: Routing.generate(
          'dplan_api_procedure_layer_update',
          {
            procedureId: state.procedureId
          }
        ),
        responseType: 'json',
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
     * Get complete object for striped opbject containing element-Id and Type
     * (both have to match the coresponding included-array)
     *
     * @param element|Object ( {id, type} )
     * @returns Object|element(gislayers or GisLayerCategory)
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
     * Get List of all gisLayers
     *
     * @returns Array|element(gislayers or GisLayerCategory)
     */
    gisLayerList: state => type => {
      if (typeof state.apiData.included === 'undefined') return []
      return state.apiData.included.filter(current => {
        const putInList = (type) ? (type === current.attributes.layerType) : true
        return (current.type === 'GisLayer' && putInList)
      }).sort((a, b) => (a.attributes.mapOrder).toString().padEnd(21, '0') - (b.attributes.mapOrder).toString().padEnd(21, '0'))
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
     * @param categoryId|String
     * @param type|String ('overlay' | base')
     * @param withCategories|Boolean
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
