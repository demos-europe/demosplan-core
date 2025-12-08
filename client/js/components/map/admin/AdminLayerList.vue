<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--

  This Component creates a dragable List for all Gis-Layer.
  It gets its data from a JSON-Route and only needs the ProcedureID.

  It Handles Layers and Categories
  both are implicit in the "admin-layer-list-item"-Component
  - !! They can be nested recursivly !!
  - It uses vuedraggable.

  -->
  <usage>
    <admin-layer-list procedure-id="procedureId" />
  </usage>
</documentation>

<template>
  <div id="gisLayers">
    <div class="flex">
      <h2 class="w-1/4">
        {{ Translator.trans('gislayer') }}
      </h2>
      <div class="w-3/4 text-right">
        <dp-split-button>
          <a
            :class="{'has-dropdown': hasPermission('feature_map_category')}"
            class="btn btn--primary"
            data-cy="createNewGisLayer"
            :href="Routing.generate('DemosPlan_map_administration_gislayer_new',{ procedure: procedureId })"
          >
            {{ Translator.trans('gislayer.create') }}
          </a>
          <template
            v-if="hasPermission('feature_map_category')"
            v-slot:dropdown
          >
            <a :href="Routing.generate('DemosPlan_map_administration_gislayer_category_new',{ procedureId: procedureId })">
              {{ Translator.trans('maplayer.category.new') }}
            </a>
          </template>
        </dp-split-button>
      </div>
    </div>

    <div
      class="relative"
      :class="{'pointer-events-none': false === isEditable}"
    >
      <div class="mt-4 flex">
        <h3
          v-if="hasPermission('feature_map_baselayer')"
          class="flex-1 w-1/3"
        >
          {{ Translator.trans('map.overlays') }}
        </h3>
        <div
          v-if="canHaveCategories"
          class="flex-1 w-2/3 text-right"
        >
          <button
            class="btn--blank o-link--default"
            :class="{'o-link--active':currentTab === 'treeOrder'}"
            @click.prevent="setActiveTab('treeOrder')"
          >
            {{ Translator.trans('map.set.order.tree') }}
          </button>
          <button
            class="btn--blank o-link--default ml-4"
            :class="{'o-link--active':currentTab === 'mapOrder'}"
            @click.prevent="setActiveTab('mapOrder')"
          >
            {{ Translator.trans('map.set.order.map') }}
          </button>
        </div>
      </div>

      <!-- List-Head -->
      <div class="color--grey mb-1 mt-2 mr-2">
        <div class="c-at-item__row-icon pl-0">
          <!-- DragHandler -->
        </div>
        <div class="flex pl-2">
          <div class="flex-1">
            {{ Translator.trans('description') }}
          </div>
          <div
            v-if="hasPermission('feature_map_layer_visibility')"
            class="w-1/12 text-right"
          >
            <i
              v-tooltip="{ content: Translator.trans('explanation.gislayer.visibilitygroup'), classes: 'max-w-none' }"
              class="fa fa-link mr-2"
            />
          </div>
          <div
            v-if="hasPermission('feature_map_layer_visibility')"
            class="w-1/12 text-right"
          >
            <i
              v-tooltip="Translator.trans('explanation.gislayer.visibility')"
              class="fa fa-eye mr-2"
            />
          </div>

          <div class="w-2/12 text-right">
            {{ Translator.trans('edit') }}
          </div>
        </div>
      </div>
      <dp-draggable
        v-if="false === isLoading"
        :id="rootId"
        :class="{ 'color--grey': false === isEditable }"
        :content-data="currentList"
        :opts="draggableOptions"
        @end="updateChildren"
      >
        <admin-layer-list-item
          v-for="(item, idx) in currentList"
          :key="item.id"
          data-cy="overlaysMapLayerListItem"
          :element="item"
          :index="idx"
          :is-loading="(false === isEditable)"
          layer-type="overlay"
          :parent-order-position="1"
          :sorting-type="currentTab"
        />
      </dp-draggable>

      <dp-loading
        v-if="isLoading"
        class="list__item py-2 border--top"
      />

      <div
        v-if="(0 === currentList.length ) && false === isLoading"
        class="list__item py-2 border--top color--grey"
      >
        {{ Translator.trans('no.data') }}
      </div>

      <template v-if="hasPermission('feature_map_baselayer')">
        <h3 class="mt-4">
          {{ Translator.trans('map.bases') }}
        </h3>
        <!-- List-Head -->
        <div class="color--grey mb-1 mt-2 mr-2">
          <div class="c-at-item__row-icon pl-0">
            <!-- DragHandler -->
          </div>
          <div class="flex">
            <div class="flex-1 pl-2">
              {{ Translator.trans('description') }}
            </div>
            <div
              v-if="hasPermission('feature_map_layer_visibility')"
              class="w-1/12 text-right"
            >
              <i
                v-tooltip="Translator.trans('explanation.gislayer.visibility')"
                class="fa fa-eye mr-2"
              />
            </div>
            <div class="w-1/12 text-right">
              {{ Translator.trans('edit') }}
            </div>
          </div>
        </div>
        <dp-draggable
          v-if="false === isLoading"
          :opts="draggableOptionsForBaseLayer"
          :content-data="currentBaseList"
          :class="{'color--grey': false === isEditable}"
          @end="updateChildren"
        >
          <admin-layer-list-item
            v-for="(item, idx) in currentBaseList"
            :key="item.id"
            data-cy="baseMapLayerListItem"
            :element="item"
            :index="idx"
            :is-loading="(false === isEditable)"
            layer-type="base"
            :sorting-type="currentTab"
          />
        </dp-draggable>
        <div class="my-4">
          <h3>
            {{ Translator.trans('map.base.minimap') }}
          </h3>
          <div>
            <p class="font-size-small">
              {{ Translator.trans('map.base.minimap.hint') }}
            </p>
            <select
              v-model="currentMinimapLayer"
              class="o-form__control-select w-1/2"
              data-cy="adminLayerList:currentMinimapLayer"
            >
              <option :value="{id: '', attributes: { name: 'default' }}">
                {{ Translator.trans('selection.no') }}
              </option>
              <option
                v-for="item in mapBaseList"
                :key="item.id"
                :value="item"
              >
                {{ item.attributes.name }}
              </option>
            </select>
          </div>
        </div>
      </template>
      <div
        v-if="!isLoading"
        class="text-right mt-5 space-x-2"
      >
        <dp-button
          :busy="!isEditable"
          :text="Translator.trans('save')"
          data-cy="adminLayerList:save"
          rounded
          @click="saveOrder"
        />
        <dp-button
          :busy="!isEditable"
          :text="Translator.trans('save.and.return.to.list')"
          data-cy="adminLayerList:saveAndReturn"
          rounded
          @click="saveOrder(true)"
        />
        <dp-button
          :text="Translator.trans('reset.order')"
          color="secondary"
          data-cy="adminLayerList:resetOrder"
          rounded
          type="reset"
          @click.prevent="resetOrder"
        />
      </div>
    </div>
  </div>
</template>

<script>
import { DpButton, DpDraggable, DpLoading, DpSplitButton } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import AdminLayerListItem from './AdminLayerListItem'
import lscache from 'lscache'
import { scrollTo } from 'vue-scrollto'

export default {
  name: 'AdminLayerList',

  components: {
    AdminLayerListItem,
    DpDraggable,
    DpButton,
    DpLoading,
    DpSplitButton,
  },

  props: {
    procedureId: {
      required: true,
      type: String,
    },

    parentOrderPosition: {
      required: false,
      type: Number,
      default: 1,
    },
  },

  data () {
    return {
      isLoading: true,
      currentTab: '',
      isEditable: true,
      drag: false,
    }
  },

  computed: {
    ...mapState('Layers', [
      'draggableOptions',
      'draggableOptionsForBaseLayer',
    ]),

    ...mapGetters('Layers', [
      'element',
      'elementsListByAttribute',
      'gisLayerList',
      'elementListForLayerSidebar',
      'minimapLayer',
      'rootId',
    ]),

    /**
     *
     * Model to switch the Models but keeping the Markup lean
     * refers to mapList or treeList
     */
    currentList () {
      return (this.currentTab === 'mapOrder') ? this.mapList : this.treeList
    },

    currentBaseList () {
      return (this.currentTab === 'mapOrder') ? this.mapBaseList : this.treeBaseList
    },

    /*
     * Nested List which reflects layer-categories and its children
     * layerType overlay
     * mapList and treeList have different order-numbers
     */
    treeList () {
      return this.elementListForLayerSidebar(null, 'overlay', true)
    },

    /*
     * MapList which ignores categories
     * layerType overlay
     * mapList and treeList have different order-numbers
     */
    mapList () {
      return this.gisLayerList('overlay')
    },

    /*
     * Nested List which reflects layer-categories and its children
     * layerType base
     * mapList and treeList have different order-numbers
     */
    treeBaseList () {
      return this.elementListForLayerSidebar(null, 'base', false)
    },

    /*
     * MapList which ignores categories
     * layerType base
     * mapList and treeList have different order-numbers
     */
    mapBaseList () {
      return this.gisLayerList('base')
    },

    canHaveCategories () {
      return hasPermission('feature_map_category')
    },

    currentMinimapLayer: {
      get () {
        return this.minimapLayer
      },
      set (value) {
        this.setMinimapBaseLayer(value.id)
      },
    },
  },

  methods: {
    ...mapActions('Layers', {
      saveLayers: 'saveAll',
      getLayers: 'get',
    }),

    ...mapMutations('Layers', [
      'setAttributeForLayer',
      'setChildrenFromCategory',
      'resetOrder',
      'setMinimapBaseLayer',
      'updateState',
    ]),

    /**
     * Dissolves a visibility group if it has only 1 member remaining
     *
     * @param {string} groupId - The visibility group ID to check
     */
    cleanUpOrphanedVisibilityGroup (groupId) {
      const remainingMembers = this.elementsListByAttribute({
        type: 'visibilityGroupId',
        value: groupId,
      })

      // If only 1 member left, dissolve the group
      if (remainingMembers.length === 1) {
        this.setAttributeForLayer({
          id: remainingMembers[0].id,
          attribute: 'visibilityGroupId',
          value: null,
        })
      }
    },

    saveOrder (redirect) {
      this.isEditable = false

      this.saveLayers()
        .then(() => {
          this.isEditable = true

          if (redirect === true) {
            window.location.href = Routing.generate('DemosPlan_element_administration', { procedure: this.procedureId })
          }
        })
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.saved'))
        })
        .catch(err => {
          dplan.notify.error(Translator.trans('error.changes.not.saved'))
          console.error(err)
        })
    },

    setActiveTab (sortOrder) {
      this.currentTab = sortOrder
      lscache.set('layerOrderTab', sortOrder, 300)
    },

    /**
     * Synchronizes all children of a category that appears as a layer
     * Recursively updates hasDefaultVisibility and clears visibilityGroupId
     *
     * @param {string} categoryId - ID of the parent category
     * @returns {Object} Object with ungroupedLayers array and affectedGroupIds Set
     */
    syncChildrenOfCategoryThatAppearsAsLayer (categoryId) {
      const category = this.element({
        id: categoryId,
        type: 'GisLayerCategory',
      })

      if (!category) {
        return { ungroupedLayers: [], affectedGroupIds: new Set() }
      }

      const children = this.elementListForLayerSidebar(categoryId, 'overlay', true)
      const ungroupedLayers = []
      const affectedGroupIds = new Set()

      children.forEach((child) => {
        // Match parent's default visibility
        this.setAttributeForLayer({
          id: child.id,
          attribute: 'hasDefaultVisibility',
          value: category.attributes.hasDefaultVisibility,
        })

        // Clear visibility group for child layers
        if (child.type === 'GisLayer') {
          // Track layers that were in a visibility group
          if (child.attributes.visibilityGroupId) {
            ungroupedLayers.push(child)
            affectedGroupIds.add(child.attributes.visibilityGroupId)
          }

          this.setAttributeForLayer({
            id: child.id,
            attribute: 'visibilityGroupId',
            value: null,
          })
        }

        // If child is also a category, recursively sync its children
        if (child.type === 'GisLayerCategory') {
          const nestedResult = this.syncChildrenOfCategoryThatAppearsAsLayer(child.id)
          ungroupedLayers.push(...nestedResult.ungroupedLayers)
          nestedResult.affectedGroupIds.forEach(groupId => affectedGroupIds.add(groupId))
        }
      })

      return { ungroupedLayers, affectedGroupIds }
    },

    updateChildren (ev) {
      this.setChildrenFromCategory({
        newCategoryId: ev.to.id,
        oldCategoryId: ev.from.id,
        movedElement: {
          id: ev.item.id,
          newIndex: ev.newIndex,
          oldIndex: ev.oldIndex,
        },
        orderType: this.currentTab,
        parentOrder: this.parentOrderPosition,
      })

      // If there is just one order (map) -then the treeorder should match the map-order
      if (this.currentTab === 'mapOrder' && !this.canHaveCategories) {
        this.setChildrenFromCategory({
          newCategoryId: ev.to.id,
          oldCategoryId: ev.from.id,
          movedElement: {
            id: ev.item.id,
            newIndex: ev.newIndex,
            oldIndex: ev.oldIndex,
          },
          orderType: 'treeOrder',
          parentOrder: this.parentOrderPosition,
        })
      }

      // If dropped into a category that hides children, sync all its children
      const newCategory = this.element({
        id: ev.to.id,
        type: 'GisLayerCategory',
      })

      if (newCategory && newCategory.attributes.layerWithChildrenHidden) {
        this.$nextTick(() => {
          const { ungroupedLayers, affectedGroupIds } = this.syncChildrenOfCategoryThatAppearsAsLayer(ev.to.id)

          // Clean up orphaned visibility groups (groups with only 1 member left)
          affectedGroupIds.forEach(groupId => {
            this.cleanUpOrphanedVisibilityGroup(groupId)
          })

          // Notify user if any layers were removed from visibility groups
          if (ungroupedLayers.length > 0) {
            const layerNames = ungroupedLayers.map(layer => layer.attributes.name).join(', ')

            dplan.notify.notify('warning', Translator.trans('gislayer.removed.from.visibility.group', { layers: layerNames }))
          }
        })
      }
    },
  },

  mounted () {
    const payload = {
      procedureId: this.procedureId,
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
          'isPrint',
          'isScope',
          'layers',
          'layerType',
          'mapOrder',
          'name',
          'treeOrder',
          'url',
          'visibilityGroupId',
        ].join(),
      },
    }
    this.getLayers(payload)
      .then(() => {
        this.isLoading = false
        this.currentMinimapLayer = this.minimapLayer
        if (window.location.hash === '#gislayers') {
          scrollTo('#gislayers', { offset: -10 })
        }
      })
    if (this.canHaveCategories) {
      this.currentTab = lscache.get('layerOrderTab') || 'treeOrder'
    } else {
      this.currentTab = 'mapOrder'
    }

    const basicOptions = {
      animation: 150,
      sort: true,
      disabled: (this.isEditable === false),
      handle: '.handle',
      ghostClass: 'o-sortablelist__ghost', // Class name for the drop placeholder
      chosenClass: 'o-sortablelist__chosen', // Class name for the chosen item
      dragClass: 'o-sortablelist__drag', // Class name for the dragging item
    }

    this.updateState({
      key: 'draggableOptions',
      value: {
        ...basicOptions,
        ...{
          group: {
            name: 'treeList',
            revertClone: false,
            pull: ['treeList'],
            push: ['treeList'],
          },
        },
      },
    })

    this.updateState({ key: 'draggableOptionsForBaseLayer', value: basicOptions })
  },
}
</script>
