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
  - It uses vuedraggable.

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
          :class="{'o-link--active':currentTab === 'treeOrder'}">
          {{ Translator.trans('map.set.order.tree') }}
        </button>
        <button
          @click.prevent="setActiveTab('mapOrder')"
          class="btn--blank o-link--default u-ml"
          :class="{'o-link--active':currentTab === 'mapOrder'}">
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
      v-if="false === this.isLoading"
      :opts="draggableOptions"
      v-model="currentList"
      :class="{'color--grey': false === isEditable}">
      <dp-admin-layer-list-item
        v-for="(item, idx) in currentList"
        :key="item.id"
        :element="item"
        :sorting-type="currentTab"
        :is-loading="(false === isEditable)"
        layer-type="overlay"
        data-cy="overlaysMapLayerListItem"
        :index="idx"
        :parent-order-position="1" />
    </dp-draggable>

    <dp-loading
      v-if="isLoading"
      class="list__item u-pv-0_5 border--top" />

    <div
      v-if="(0 === currentList.length ) && false === isLoading"
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
      :opts="draggableOptionsForBaseLayer"
      v-model="currentBaseList"
      :class="{'color--grey': false === isEditable}">
      <dp-admin-layer-list-item
        v-for="(item, idx) in currentBaseList"
        :key="item.id"
        :element="item"
        :sorting-type="currentTab"
        :is-loading="(false === isEditable)"
        layer-type="base"
        data-cy="baseMapLayerListItem"
        :index="idx" />
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
    procedureId: {
      required: true,
      type: String
    },

    parentOrderPosition: {
      required: false,
      type: Number,
      default: 1
    }
  },

  data () {
    return {
      isLoading: true,
      currentTab: '',
      isEditable: true,
      drag: false
    }
  },

  computed: {
    /**
     *
     * Model to switch the Models but keeping the Markup lean
     * refers to mapList or treeList
     */
    currentList: {
      get () {
        return (this.currentTab === 'mapOrder') ? this.mapList : this.treeList
      },
      set ({ newOrder }) {
        if (this.currentTab === 'mapOrder') {
          this.mapList = newOrder
        } else {
          this.treeList = newOrder
        }
      }
    },

    currentBaseList: {
      get () {
        return (this.currentTab === 'mapOrder') ? this.mapBaseList : this.treeBaseList
      },
      set ({ newOrder }) {
        if (this.currentTab === 'mapOrder') {
          this.mapBaseList = newOrder
        } else {
          this.treeBaseList = newOrder
        }
      }
    },

    /*
     * Nested List which reflects layer-categories and its children
     * layerType overlay
     * mapList and treeList have different order-numbers
     */
    treeList: {
      get () {
        return this.elementListForLayerSidebar(null, 'overlay', true)
      },
      set (value) {
        this.setChildrenFromCategory({
          categoryId: null,
          data: value,
          orderType: 'treeOrder',
          parentOrder: this.parentOrderPosition
        })
      }
    },

    /*
     * MapList which ignores categories
     * layerType overlay
     * mapList and treeList have different order-numbers
     */
    mapList: {
      get () {
        return this.gisLayerList('overlay')
      },
      set (value) {
        this.setChildrenFromCategory({
          categoryId: null,
          data: value,
          orderType: 'mapOrder',
          parentOrder: this.parentOrderPosition
        })
        /* If there is just one order (map) -then the treeorder should match the map-order */
        if (this.canHaveCategories === false) {
          this.setChildrenFromCategory({
            categoryId: null,
            data: value,
            orderType: 'treeOrder',
            parentOrder: this.parentOrderPosition
          })
        }
      }
    },

    /*
     * Nested List which reflects layer-categories and its children
     * layerType base
     * mapList and treeList have different order-numbers
     */
    treeBaseList: {
      get () {
        return this.elementListForLayerSidebar(null, 'base', false)
      },
      set (value) {
        this.setChildrenFromCategory({
          categoryId: null,
          data: value,
          orderType: 'treeOrder',
          parentOrder: this.parentOrderPosition
        })
      }
    },

    /*
     * MapList which ignores categories
     * layerType base
     * mapList and treeList have different order-numbers
     */
    mapBaseList: {
      get () {
        return this.gisLayerList('base')
      },
      set (value) {
        this.setChildrenFromCategory({
          categoryId: null,
          data: value,
          orderType: 'mapOrder',
          parentOrder: this.parentOrderPosition
        })
        /* If there is just one order (map) -then the treeorder should match the map-order */
        if (this.canHaveCategories === false) {
          this.setChildrenFromCategory({
            categoryId: null,
            data: value,
            orderType: 'treeOrder',
            parentOrder: this.parentOrderPosition
          })
        }
      }
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
    },

    ...mapState('layers', ['draggableOptions', 'draggableOptionsForBaseLayer', 'draggableOptionsForCategorysWithHiddenLayers']),
    ...mapGetters('layers', ['gisLayerList', 'elementListForLayerSidebar', 'minimapLayer'])
  },

  methods: {
    saveOrder (redirect) {
      this.isEditable = false
      this.save().then(() => {
        this.isEditable = true
        if (redirect === true) {
          window.location.href = Routing.generate('DemosPlan_element_administration', { procedure: this.procedureId })
        }
      })
    },

    setActiveTab (sortOrder) {
      this.currentTab = sortOrder
      lscache.set('layerOrderTab', sortOrder, 300)
    },

    ...mapActions('layers', ['save', 'get']),
    ...mapMutations('layers', ['setChildrenFromCategory', 'resetOrder', 'setDraggableOptions', 'setDraggableOptionsForCategorysWithHiddenLayers', 'setDraggableOptionsForBaseLayer', 'setMinimapBaseLayer'])
  },

  mounted () {
    this.get(this.procedureId)
      .then(() => {
        this.isLoading = false
        this.currentMinimapLayer = this.minimapLayer
        if (window.location.hash === '#gislayers') {
          scrollTo('#gislayers', { offset: -10 })
        }
      })
    this.currentTab = lscache.get('layerOrderTab') || 'treeOrder'

    const basicOptions = {
      animation: 150,
      sort: true,
      disabled: (this.isEditable === false),
      handle: '.handle',
      ghostClass: 'o-sortablelist__ghost', // Class name for the drop placeholder
      chosenClass: 'o-sortablelist__chosen', // Class name for the chosen item
      dragClass: 'o-sortablelist__drag' // Class name for the dragging item
    }

    this.setDraggableOptions({
      ...basicOptions,
      ...{
        group: {
          name: 'treeList',
          revertClone: false,
          pull: ['treeList'],
          push: ['treeList']
        }
      }
    })

    this.setDraggableOptionsForBaseLayer(basicOptions)
  }
}
</script>
