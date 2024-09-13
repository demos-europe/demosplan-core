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
  both are implicit in the "dp-admin-layer-list-item"-Component
  - !! They can be nested recursivly !!
  -->
  <usage>
    <dp-admin-layer-list
      procedure-id="procedureId"
    ></dp-admin-layer-list>
  </usage>
</documentation>

<template>
  <div
    class="relative"
    :class="{'pointer-events-none': false === isEditable}">
    <div class="layout--flush u-mt">
      <h3 class="layout__item u-1-of-3">
        {{ Translator.trans('map.overlays') }}
      </h3><!--
   --><div
        class="layout__item u-2-of-3 text-right"
        v-if="canHaveCategories">
        <button
          @click.prevent="setActiveTab('treeOrder')"
          class="btn--blank o-link--default"
          :class="{'o-link--active': currentSorting === 'treeOrder'}">
          {{ Translator.trans('map.set.order.tree') }}
        </button>
        <button
          @click.prevent="setActiveTab('mapOrder')"
          class="btn--blank o-link--default u-ml"
          :class="{'o-link--active': currentSorting === 'mapOrder'}">
          {{ Translator.trans('map.set.order.map') }}
        </button>
      </div>
    </div>

    <!-- List-Head -->
    <div class="color--grey u-mb-0_25 u-mt-0_5 u-mr-0_5">
      <div class="c-at-item__row-icon layout__item u-pl-0">
        <!-- DragHandler -->
      </div><!--
   --><div class="layout--flush layout__item c-at-item__row u-pl-0_5">
        <div class="layout__item u-9-of-12">
          {{ Translator.trans('description') }}
        </div><!--
     --><div class="layout__item u-1-of-12 text-right">
            <i
              class="fa fa-link u-mr-0_5"
              v-tooltip="{ content: Translator.trans('explanation.gislayer.visibilitygroup'), classes: 'max-w-none' }" />
        </div><!--

     --><div class="layout__item u-1-of-12 text-right">
            <i
              class="fa fa-eye u-mr-0_5"
              v-tooltip="Translator.trans('explanation.gislayer.visibility')" />
        </div><!--

     --><div class="layout__item u-1-of-12 text-right">
          {{ Translator.trans('edit') }}
        </div>
      </div>
    </div>
    <dp-draggable
      v-if="!this.isLoading"
      :opts="draggableOptions"
      :content-data="currentList"
      :class="{'color--grey': false === isEditable}"
      group-id="layerlist"
      @end="(event, item) => changeManualSort(event, item)">
      <dp-admin-layer-list-item
        v-for="(item, idx) in currentList"
        :key="`currentList:${item.id}`"
        data-cy="overlaysMapLayerListItem"
        :element="item"
        group-id="layerlist"
        :index="idx"
        :is-loading="(false === isEditable)"
        layer-type="overlay"
        :list-type="currentSorting === 'mapOrder' ? 'map' : 'tree'"
        :parent-order-position="1"
        :sorting-type="currentSorting" />
    </dp-draggable>

    <dp-loading
      v-if="isLoading"
      class="list__item u-pv-0_5 border--top" />

    <div
      v-if="(currentList.length === 0) && !isLoading"
      class="list__item u-pv-0_5 border--top color--grey">
      {{ Translator.trans('no.data') }}
    </div>

    <h3 class="u-mt">
      {{ Translator.trans('map.bases') }}
    </h3>
    <!-- List-Head -->
    <div class="color--grey u-mb-0_25 u-mt-0_5 u-mr-0_5">
      <div class="c-at-item__row-icon layout__item u-pl-0">
        <!-- DragHandler -->
      </div><!--
   --><div class="layout--flush layout__item c-at-item__row">
          <div class="layout__item u-10-of-12 u-pl-0_5">
            {{ Translator.trans('description') }}
          </div><!--
       --><div class="layout__item u-1-of-12 text-right">
          <i
            class="fa fa-eye u-mr-0_5"
            v-tooltip="Translator.trans('explanation.gislayer.visibility')" />
          </div><!--
       --><div class="layout__item u-1-of-12 text-right">
              {{ Translator.trans('edit') }}
          </div>
      </div>
    </div>
    <dp-draggable
      v-if="false === this.isLoading"
      :class="{'color--grey': false === isEditable}"
      :content-data="currentBaseList"
      :opts="draggableOptionsForBaseLayer"
      group-id="baselist"
      @end="(event, item) => changeManualSort(event, item)">
      <dp-admin-layer-list-item
        v-for="(item, idx) in currentBaseList"
        :key="`currentBaseList:${item.id}`"
        data-cy="baseMapLayerListItem"
        :element="item"
        group-id="baselist"
        :index="idx"
        :is-loading="(false === isEditable)"
        layer-type="base"
        :list-type="currentSorting === 'mapOrder' ? 'mapBase' : 'treeBase'"
        :sorting-type="currentSorting" />
    </dp-draggable>
    <div class="layout--flush u-mt u-mb">
      <h3 class="layout__item u-1-of-3">
        {{ Translator.trans('map.base.minimap') }}
      </h3><!--
   --><div class="layout__item u-2-of-3">
        <select
          class="o-form__control-select"
          v-model="currentMinimapLayer">
          <option :value="{id: '', attributes: { name: 'default' }}">
            {{ Translator.trans('selection.no') }}
          </option>
          <option
            v-for="item in mapBaseList"
            :key="item.id"
            :value="item">
            {{ item.attributes.name }}
          </option>
        </select>
      </div>
      <p class="font-size-small">
        {{ Translator.trans('map.base.minimap.hint') }}
      </p>
    </div>

    <div
      class="text-right u-mv space-inline-s"
      v-if="false === isLoading">
      <dp-button
        :busy="!isEditable"
        :text="Translator.trans('save')"
        @click="saveOrder" />
      <dp-button
        :busy="!isEditable"
        :text="Translator.trans('save.and.return.to.list')"
        @click="saveOrder(true)" />
      <button
        class="btn btn--secondary"
        type="reset"
        @click.prevent="resetOrder">
        {{ Translator.trans('reset.order') }}
      </button>
    </div>
  </div>
</template>

<script>
import { DpButton, DpDraggable, DpLoading } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import DpAdminLayerListItem from './DpAdminLayerListItem'
import lscache from 'lscache'
import { scrollTo } from 'vue-scrollto'

export default {
  name: 'DpAdminLayerList',

  components: {
    DpAdminLayerListItem,
    DpDraggable,
    DpButton,
    DpLoading
  },

  props: {
    parentOrderPosition: {
      required: false,
      type: Number,
      default: 1
    },

    procedureId: {
      required: true,
      type: String
    }
  },

  data () {
    return {
      isLoading: true,
      isEditable: true,
      drag: false
    }
  },

  computed: {
    ...mapState('layers', [
      'currentSorting',
      'draggableOptions',
      'draggableOptionsForBaseLayer'
    ]),

    ...mapGetters('layers', [
      'minimapLayer',
      'baseList',
      'mapList',
      'treeList',
      'rootId'
    ]),

    /**
     *
     * Model to switch the Models but keeping the Markup lean
     * refers to mapList or treeList
     */
    currentList () {
      return this.currentSorting === 'mapOrder'
        ? this.mapList
        : this.treeList
    },

    currentBaseList () {
      return this.baseList // It gets re-sorted on Tab-Change, so we get always the List we want (map/tree)
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
      }
    }
  },

  methods: {
    ...mapActions('layers', [
      'changeRelationship',
      'createIndexes',
      'fetchLayers',
      'saveLayerList',
      'setNewIndex',
      'updateSortOrder'
    ]),

    ...mapMutations('layers', [
      'resetOrder',
      'set',
      'setMinimapBaseLayer'
    ]),

    changeManualSort (event, item) {
      const id = event.item.id
      const { newIndex, oldIndex } = event
      const targetParentId = event.to.parentElement.id ?? null
      const sourceParentId = event.from.parentElement.id ?? this.rootId

      if (this.currentSorting === 'treeOrder' && targetParentId !== sourceParentId) {
        this.changeRelationship({
          id,
          targetParentId
        })
      }

      this.setNewIndex({
        id,
        index: newIndex,
        oldIndex,
        targetParentId,
        sourceParentId
      })
    },

    async saveOrder (redirect) {
      this.isEditable = false

      await this.updateSortOrder({ parentId: this.rootId, parentOrder: 100 })

      this.saveLayerList()
        .then(() => {
          this.isEditable = true

          if (redirect === true) {
            window.location.href = Routing.generate('DemosPlan_element_administration', { procedure: this.procedureId })
          }
        })
    },

    async setActiveTab (sortOrder) {
      await this.updateSortOrder({ parentId: this.rootId, parentOrder: 100 })

      this.set({ key: 'currentSorting', value: sortOrder })
      lscache.set('layerOrderTab', sortOrder, 300)

      this.createIndexes()
    }
  },

  mounted () {
    const currentTab = lscache.get('layerOrderTab') || 'treeOrder'
    this.set({ key: 'currentSorting', value: currentTab })
    this.set({ key: 'procedureId', value: this.procedureId })

    this.fetchLayers()
      .then(() => {
        this.isLoading = false
        this.currentMinimapLayer = this.minimapLayer
        if (window.location.hash === '#gislayers') {
          scrollTo('#gislayers', { offset: -10 })
        }

        this.createIndexes()
      })

    const basicOptions = {
      animation: 150,
      sort: true,
      disabled: (this.isEditable === false),
      handle: '.handle',
      ghostClass: 'o-sortablelist__ghost', // Class name for the drop placeholder
      chosenClass: 'o-sortablelist__chosen', // Class name for the chosen item
      dragClass: 'o-sortablelist__drag' // Class name for the dragging item
    }

    this.set({
      key: 'draggableOptions',
      value: {
        ...basicOptions,
        ...{
          group: {
            name: 'treeList',
            revertClone: false,
            pull: ['treeList'],
            push: ['treeList']
          }
        }
      }
    })

    this.set({ key: 'draggableOptionsForBaseLayer', value: basicOptions })
  }
}
</script>
