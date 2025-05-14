<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
<!--

  This Component is the Child of "AdminLayerList"
  go there for Details

-->
</documentation>
<template>
  <div
    :id="layer.id"
    class="o-sortablelist__item py-2 pl-2 border--top"
    :class="{
      'is-active' : isActive,
      'cursor-pointer' : (false === layer.attributes.isBaseLayer && 'GisLayerCategory' !== layer.type && false === isChildOfCategoryThatAppearsAsLayer),
    }"
    data-cy="adminLayerListItem:setLayerActive"
    @click="setActiveState"
    @mouseover="mouseOverElement"
    @mouseout="mouseOutElement">
    <div class="c-at-item__row-icon inline-block">
      <i
        class="fa fa-bars handle w-[20px] cursor-grab"
        aria-hidden="true"
        :title="Translator.trans('move')" />
    </div><!--
 --><div
    class="inline-block layout--flush c-at-item__row"
    data-cy="mapLayerListItem">
    <div
      class="inline-block"
      :class="hasPermission('feature_map_layer_visibility') ? 'w-9/12 ' : 'w-11/12'">
      <!-- regular categories -->
      <i
        v-if="layer.type === 'GisLayerCategory' && false === layer.attributes.layerWithChildrenHidden"
        aria-hidden="true"
        class="fa u-mr-0_125"
        :class="[childElements.length > 0 ? (showChildren ? 'fa-folder-open' : 'fa-folder') :'fa-folder-o']"
        @click="toggleChildren" />
      {{ layer.attributes.name }}
      <span
        v-if="isChildOfCategoryThatAppearsAsLayer && 'mapOrder' === sortingType"
        class="font-size-smaller mr-2">
          <!-- children of categories that should appear as Layer
                    only in map-list (where no categories are shown)
                    -->
        <br>{{ Translator.trans('maplayer.hidden.child.of.category') }}
      </span>
      <span
        v-if="layer.attributes.layerWithChildrenHidden"
        class="font-size-smaller mr-2">
          <!-- categories that should appear as Layer -->
        <br>{{ Translator.trans('maplayer.category.with.hidden.children') }}
      </span>
      <span
        v-if="layer.attributes.description"
        class="font-size-smaller mr-2">
        <br>{{ layer.attributes.description }}
      </span>
      <span
        v-if="layer.attributes.isBplan"
        class="font-size-smaller mr-2">
        <br>{{ Translator.trans('explanation.gislayer.useas.bplan') }}
      </span>
      <span
        v-if="layer.attributes.isScope"
        class="font-size-smaller mr-2">
        <br>{{ Translator.trans('explanation.gislayer.useas.scope') }}
      </span>
      <span
        v-if="false === layer.attributes.isEnabled"
        class="font-size-smaller">
        <br>{{ Translator.trans('explanation.gislayer.useas.invisible') }}
      </span>
      <span
        v-if="layer.attributes.isPrint"
        class="font-size-smaller">
        <br>{{ Translator.trans('explanation.gislayer.useas.print') }}
      </span>
    </div><!--
            Show this Stuff (Visibility-group / show initially on load) only for layer, not for Categories
 --><template v-if="(layer.type === 'GisLayer') && hasPermission('feature_map_layer_visibility')">
<!--
    --><div class="inline-block w-1/12 text-right">
      <a
        v-if="layer.attributes.isBaseLayer === false && isChildOfCategoryThatAppearsAsLayer === false"
        data-cy="adminLayerListItem:toggleVisibilityGroup"
        class="w-full flex items-center justify-center"
        :title="hintTextForLockedLayer"
        @click.stop.prevent="toggleVisibilityGroup"
        @mouseover="setIconHoverState"
        @mouseout="unsetIconHoverState">
        <i
          :aria-label="Translator.trans('gislayer.visibilitygroup.toggle')"
          :class="[iconClass,showGroupableIcon]" />
      </a>
    </div><!--
    --><div class="inline-block w-1/12 text-right">
      <input
        type="checkbox"
        data-cy="adminLayerListItem:toggleDefaultVisibility"
        :disabled="'' !== layer.attributes.visibilityGroupId || (true === isChildOfCategoryThatAppearsAsLayer)"
        @change.prevent="toggleHasDefaultVisibility"
        :checked="hasDefaultVisibility"
        :class="[iconClass, 'o-sortablelist__checkbox']">
      </div><!--
  -->
</template><!--
          Show this Stuff for 'special category that looks like an Layer and hides all his children'
 --><template v-if="(layer.type === 'GisLayerCategory' && layer.attributes.layerWithChildrenHidden)">
<!--
   --><div class="inline-block w-2/12 text-right">
        <input
          type="checkbox"
          data-cy="adminLayerListItem:toggleDefaultVisibility"
          :checked="hasDefaultVisibility"
          :class="[iconClass, 'o-sortablelist__checkbox']"
          @change.prevent="toggleHasDefaultVisibility">
      </div><!--
     -->
    </template><!--
  --><div
      v-if="(layer.type !== 'GisLayer' && (false === layer.attributes.layerWithChildrenHidden))"
      class="inline-block w-2/12 text-right">
      <!-- spacer for groups -->
    </div><!--
  --><div class="inline-block w-1/12 text-right">
      <a
        :href="editLink"
        data-cy="editLink">
        <i
          class="fa fa-pencil mr-2"
          aria-hidden="true"
          :title="Translator.trans('edit')" /><span class="sr-only">{{ Translator.trans('edit') }}</span>
      </a>
      <button
        v-if="childElements.length <= 0"
        class="btn--blank o-link--default mr-2 align-bottom"
        data-cy="adminLayerListItem:deleteElement"
        :title="Translator.trans('delete')"
        @click.prevent="deleteElement">
        <i
          class="fa fa-trash"
          aria-hidden="true" /><span class="sr-only">{{ Translator.trans('delete') }}</span>
        </button>
      </div>
    </div>

    <!-- recursive nesting inside -->
    <dp-draggable
      v-if="(layer.type === 'GisLayerCategory' && false === layer.attributes.layerWithChildrenHidden) && showChildren"
      class="layout ml-4 mt-1"
      :class="[childElements.length <= 0 ? 'o-sortablelist__empty' :'']"
      :opts="draggableOptions"
      v-model="childElements">
      <admin-layer-list-item
        v-for="(item, idx) in childElements"
        :key="item.id"
        :element="{ id: item.id, type: item.type }"
        :sorting-type="sortingType"
        :layer-type="layerType"
        :parent-order-position="layer.attributes[sortingType]"
        :index="idx" />
      <div
        v-if="childElements.length <= 0"
        class="o-sortablelist__spacer" />
    </dp-draggable>

    <!-- if special category that looks like an Layer and hides all his children -->
    <dp-draggable
      v-if="(layer.type === 'GisLayerCategory' && layer.attributes.layerWithChildrenHidden) && showChildren"
      class="layout ml-4 mt-1"
      :class="[childElements.length <= 0 ? 'o-sortablelist__empty' :'']"
      :opts="draggableOptions"
      v-model="childElements"
      @add="onAddToCategoryWithChildrenHidden">
      <admin-layer-list-item
        v-for="(item, idx) in childElements"
        :key="item.id"
        :element="item"
        :sorting-type="sortingType"
        :layer-type="layerType"
        :parent-order-position="orderPosition"
        :index="idx" />
      <div
        v-if="childElements.length <= 0"
        class="o-sortablelist__spacer" />
    </dp-draggable>
  </div>
</template>

<script>
import { DpDraggable, hasOwnProp } from '@demos-europe/demosplan-ui'
import { mapGetters, mapMutations, mapState } from 'vuex'
import AdminLayerListItem from './AdminLayerListItem'
import { v4 as uuid } from 'uuid'

export default {
  name: 'AdminLayerListItem',

  components: {
    DpDraggable
  },

  props: {
    element: {
      required: true,
      type: Object
    },

    sortingType: {
      required: false,
      type: String,
      default: 'treeOrder'
    },

    layerType: {
      required: false,
      type: String,
      default: ''
    },

    isLoading: {
      required: false,
      type: Boolean,
      default: false
    },

    parentOrderPosition: {
      required: false,
      type: Number,
      default: 1
    },

    index: {
      required: false,
      type: Number,
      default: 0
    }
  },

  data () {
    return {
      drag: false,
      preventActiveFromToggeling: false,
      iconClass: 'mb-0 ml-4',
      showChildren: true
    }
  },

  computed: {
    parentCategory () {
      // Get parentLayer and check if if it hides his children
      const parentLayer = this.$store.getters['Layers/element']({
        id: this.layer.attributes.categoryId,
        type: 'GisLayerCategory'
      })
      if (typeof parentLayer !== 'undefined') {
        return parentLayer
      }
      return {}
    },

    isChildOfCategoryThatAppearsAsLayer () {
      if (hasOwnProp(this.parentCategory, 'attributes')) {
        return this.parentCategory.attributes.layerWithChildrenHidden
      }
      return false
    },

    orderPosition () {
      return this.layer.attributes[this.sortingType]
    },

    layer () {
      return this.$store.getters['Layers/element']({ id: this.element.id, type: this.element.type })
    },

    hasDefaultVisibility () {
      return this.layer.attributes.hasDefaultVisibility
    },

    showGroupableIcon () {
      /**
       * If this is active
       *
       * - groupable
       *  -- grouped
       *  (---- when hover over hovered elements icon and groupsize < 2: inverse icon state to show what will happen)
       *  -- ungrouped
       *  (----  when hover over hovered elements icon: inverse icon state to show what will happen)
       *  - locked
       *
       *
       * if hover and there is no active-element
       *  - groupable
       *  -- grouped
       *  -- ungrouped
       *  - locked
       *  ---- help texts
       *
       * if grouped with hovered (when there is no active)
       * show chain icon
       *
       * if there is an active-element show related state of me
       * - grouped
       *  (----  when hover over hovered elements icon: inverse icon state to show what will happen)
       * - groupable
       *  (----  when hover over hovered elements icon: inverse icon state to show what will happen)
       * - locked
       *
       *
       */
      const toggleMyIconInSameGroup = (this.isLinkedWithCurrentlyHovered && this.showCurrentIconState)
      const toggleMyIconWithoutGroup = (this.showCurrentIconState && this.visibilityGroupIdOfHoveredLayer === '')

      if (this.isActive) {
        if (this.hasSettingsThatPreventGrouping) {
          return 'fa fa-lock color--grey cursor-help'
        } else if (this.hasGroupId) {
          if (toggleMyIconInSameGroup && this.currentGroupSize <= 2) {
            return 'fa fa-unlink color-highlight'
          } else {
            if (toggleMyIconWithoutGroup) {
              return 'fa fa-link cursor-default color-highlight'
            } else {
              return 'fa fa-link color--grey cursor-default'
            }
          }
        } else {
          if (this.isHovered === false && toggleMyIconWithoutGroup) {
            return 'fa fa-link color-highlight'
          } else {
            return 'fa fa-unlink color--grey'
          }
        }
      }

      if (this.isHovered && this.thereIsAnActiveElement === false) {
        if (this.hasSettingsThatPreventGrouping) {
          return 'fa fa-lock color--grey cursor-help'
        }
        if (this.hasGroupId) {
          if (this.showCurrentIconState) {
            return 'fa fa-unlink color-highlight'
          } else {
            return 'fa fa-link color--grey'
          }
        } else {
          if (this.showCurrentIconState) {
            return 'fa fa-link color-highlight'
          } else {
            return 'fa fa-unlink  color-highlight cursor-default'
          }
        }
      }

      if (this.isLinkedWithCurrentlyHovered && this.thereIsAnActiveElement === false) {
        return 'fa fa-link color--grey cursor-default'
      }

      if (this.isHovered && this.thereIsAnActiveElement === true) {
        if (this.hasSettingsThatPreventGrouping || this.hasDifferentDefaultVisibility || this.isInAnotherGroupThatsNotEmpty) {
          return 'fa fa-lock color--grey cursor-help'
        }
        if (this.hasGroupId) {
          if (this.showCurrentIconState) {
            return 'fa fa-unlink color-highlight'
          } else {
            return 'fa fa-link color--grey'
          }
        } else {
          if (this.showCurrentIconState) {
            return 'fa fa-link color-highlight'
          } else {
            return 'fa fa-unlink color--grey cursor-default'
          }
        }
      }

      if (this.thereIsAnActiveElement) {
        if (this.hasSettingsThatPreventGrouping || this.hasDifferentDefaultVisibility || this.isInAnotherGroupThatsNotEmpty) {
          return 'fa fa-lock color--grey'
        } else {
          if (this.hasGroupId) {
            if (toggleMyIconWithoutGroup) {
              return 'fa fa-link cursor-default color-highlight'
            } else {
              return 'fa fa-link color--grey cursor-default'
            }
          } else {
            return 'fa fa-unlink color--grey cursor-default'
          }
        }
      }

      return ''
    },

    isLockedLayer () {
      if (!this.hasSettingsThatPreventGrouping) {
        return false
      }

      if (this.isActive) {
        return true
      }

      if (this.isHovered && !this.thereIsAnActiveElement) {
        return true
      }

      return (this.thereIsAnActiveElement && (this.hasDifferentDefaultVisibility || this.isInAnotherGroupThatsNotEmpty))
    },

    isHovered () {
      return this.hoverLayerId === this.layer.id
    },

    thereIsAnActiveElement () {
      return typeof this.activeLayer.id !== 'undefined'
    },

    hasDifferentDefaultVisibility () {
      return this.layer.attributes.hasDefaultVisibility !== this.activeLayerDefaultVisibility
    },

    isInAnotherGroupThatsNotEmpty () {
      return this.hasGroupId === true && this.isInActiveGroup === false
    },

    isInActiveGroup () {
      return this.layer.attributes.visibilityGroupId === this.activeLayer.attributes.visibilityGroupId
    },

    hasGroupId () {
      return this.layer.attributes.visibilityGroupId !== ''
    },

    /**
     * Indicates whether the visibility group icon should show the current State
     * or its opposite (e.g. when the user hovers over it) to indicate what will happen when clicking
     *
     * returns Boolean
     */
    showCurrentIconState () {
      return this.$store.state.Layers.hoverLayerIconIsHovered
    },

    /**
     * ActiveLayer is used to check if the Item can be grouped (visibility group) with it
     *
     * returns Object|active Layer
     */
    activeLayer () {
      return this.$store.getters['Layers/element']({
        id: this.$store.state.Layers.activeLayerId,
        type: 'GisLayer'
      }) || { attributes: {} }
    },

    /**
     * The visibilityGroupId of the hovered Layer is needed to check if this element is in the same group as the hovered one
     *
     * returns String|VisiblitygroupId
     */
    visibilityGroupIdOfHoveredLayer () {
      return this.$store.getters['Layers/attributeForElement']({
        id: this.hoverLayerId,
        attribute: 'visibilityGroupId'
      })
    },

    /**
     * Groupsize is needed to check if the group should be dissolved when there is just one item left
     *
     * returns Integer
     */
    currentGroupSize () {
      return this.$store.getters['Layers/visibilityGroupSize'](this.layer.attributes.visibilityGroupId)
    },

    /**
     * Is used to show/highlight status of this element coresponding to the hovered layer
     *
     * returns String | layerId
     */
    hoverLayerId () {
      return this.$store.state.Layers.hoverLayerId
    },
    /**
     * Checks if this layer is the active one
     *
     * return Boolean
     */
    isActive () {
      return this.activeLayer.id === this.layer.id
    },

    /**
     * Gets the procedureId from Store
     *
     * returns String|procedureId
     */
    procedureId () {
      return this.$store.state.Layers.procedureId
    },

    /**
     * Needed to return empty String whe active-layer ist not set
     *
     * returns String|visibilitgroupId of the activeLayer (or empty String)
     */
    activeLayerVisibilityGroupId () {
      return (typeof this.activeLayer.attributes === 'undefined') ? '' : this.activeLayer.attributes.visibilityGroupId
    },
    /**
     * Needed to return empty String whe active-layer ist not set*
     *
     * returns Boolean|status of the defaultVisibility from activeLayer
     */
    activeLayerDefaultVisibility () {
      return (typeof this.activeLayer.attributes === 'undefined') ? '' : this.activeLayer.attributes.hasDefaultVisibility
    },

    /**
     * Help-texts to explain why a Layer cant be grouped
     *
     * returns String
     */
    hintTextForLockedLayer () {
      if (this.isLockedLayer === false) {
        return ''
      }

      if (this.activeLayer.attributes.isBplan === true) {
        return Translator.trans('explanation.gislayer.useas.bplan')
      }

      if (this.activeLayer.attributes.isScope === true) {
        return Translator.trans('explanation.gislayer.useas.scope')
      }

      if (this.activeLayer.attributes.canUserToggleVisibility === true) {
        return Translator.trans('explanation.gislayer.visibility.group.locked.different.visibility')
      }
      if (this.layer.attributes.isBplan === true) {
        return Translator.trans('explanation.gislayer.useas.bplan')
      }
      if (this.layer.attributes.isScope === true) {
        return Translator.trans('explanation.gislayer.useas.scope')
      }
      if (this.layer.attributes.canUserToggleVisibility === false) {
        return Translator.trans('explanation.gislayer.visibility.group.locked.different.not.togglable')
      }
      if (this.layer.attributes.visibilityGroupId !== this.activeLayerVisibilityGroupId || this.layer.attributes.visibilityGroupId !== '') {
        return Translator.trans('explanation.gislayer.visibility.group.locked.different.group')
      }
      if (this.hasSameVisibilityAsCurrentlyActive === false) {
        return Translator.trans('explanation.gislayer.visibility.group.locked.different.visibility')
      }
      return Translator.trans('explanation.gislayer.visibility.group.locked.unexpected')
    },

    /**
     * Checks if this element is already in the same visibility-group as the active Layer
     *
     * returns Boolean
     */
    isLinkedWithCurrentlyActive () {
      return (this.layer.attributes.visibilityGroupId === this.activeLayerVisibilityGroupId && this.layer.attributes.visibilityGroupId !== '')
    },
    /**
     * Checks if this element is in the same visibility-group as the hovered Layer
     *
     * returns Boolean
     */
    isLinkedWithCurrentlyHovered () {
      return (this.layer.attributes.visibilityGroupId === this.visibilityGroupIdOfHoveredLayer && this.layer.attributes.visibilityGroupId !== '' && this.hoverLayerId !== this.layer.id)
    },

    /**
     * Compares the defaultVisibilty of the element with the active layer
     *
     * returns Boolean
     */
    hasSameVisibilityAsCurrentlyActive () {
      return this.layer.attributes.hasDefaultVisibility === this.activeLayerDefaultVisibility
    },

    /**
     * Checks if the element should be highlighted because the hovered visibilitygroup-Icon Layer is in the same group
     *
     * returns Boolean
     */
    highlightOnConnectedLayer () {
      return (this.showCurrentIconState && (this.isLinkedWithCurrentlyActive || this.isActive))
    },

    /**
     * Checks if there are settings preventing this Emelement from beeing grouped
     *
     * returns Boolea
     */
    hasSettingsThatPreventGrouping () {
      if (typeof this.activeLayer.id === 'undefined') {
        return this.layer.attributes.canUserToggleVisibility === false ||
          this.layer.attributes.layerType !== 'overlay' ||
          this.layer.attributes.isScope ||
          this.layer.attributes.isBplan
      }

      return this.layer.attributes.canUserToggleVisibility === false ||
        this.activeLayer.attributes.canUserToggleVisibility === false ||
        this.layer.attributes.layerType !== 'overlay' ||
        this.activeLayer.attributes.layerType !== 'overlay' ||
        this.layer.attributes.isScope ||
        this.activeLayer.attributes.isScope ||
        this.layer.attributes.isBplan ||
        this.activeLayer.attributes.isBplan
    },

    /**
     * Get/set all child elements
     * (only important for categories/ recursion)
     *
     * returns Array|List of Layers/Categories
     */
    childElements: {
      get () {
        return this.elementListForLayerSidebar(this.element.id, 'overlay', true)
      },
      set (value) {
        this.setChildrenFromCategory({
          categoryId: this.element.id,
          data: value.newOrder,
          orderType: 'treeOrder',
          parentOrder: this.layer.attributes.treeOrder
        })
      }
    },

    /**
     * Creates edit-link
     *
     * returns String|URL
     */
    editLink () {
      if (this.layer.type === 'GisLayerCategory') {
        return Routing.generate('DemosPlan_map_administration_gislayer_category_edit', {
          gislayerCategoryId: this.layer.id,
          procedureId: this.procedureId,
          r_layerWithChildrenHidden: this.layer.attributes.layerWithChildrenHidden
        })
      } else {
        return Routing.generate('DemosPlan_map_administration_gislayer_edit', {
          gislayerID: this.layer.id,
          procedure: this.procedureId
        })
      }
    },

    ...mapState('Layers', ['draggableOptions', 'draggableOptionsForBaseLayer']),
    ...mapGetters('Layers', ['elementListForLayerSidebar'])
  },

  watch: {
    index: {
      handler () {
        this.setOrderPosition()
      },
      deep: false // Set default for migrating purpose. To know this occurrence is checked
    },
    parentOrderPosition: {
      handler () {
        this.setOrderPosition()
      },
      deep: false // Set default for migrating purpose. To know this occurrence is checked
    }
  },

  methods: {
    toggleChildren () {
      if (this.childElements.length < 1) {
        return
      }
      this.showChildren = !this.showChildren
    },

    /**
     * Removes element icon from store
     */
    deleteElement () {
      let deleteData = {}
      if (this.isLoading) {
        return false
      }
      if (!dpconfirm(Translator.trans('check.item.delete'))) {
        return
      }
      if (this.layer.type === 'GisLayerCategory') {
        deleteData = {
          id: this.layer.id,
          categoryId: this.layer.id,
          route: 'layer_category',
          relationshipType: 'categories'
        }
      } else {
        deleteData = {
          id: this.layer.id,
          categoryId: this.layer.attributes.categoryId,
          route: 'layer',
          relationshipType: 'gisLayers'
        }
      }
      this.$store.dispatch('Layers/deleteElement', deleteData)
    },

    onAddToCategoryWithChildrenHidden () {
      // Set default visibility of the parent category.
      this.setAttributeForLayer({
        id: this.layer.id,
        attribute: 'hasDefaultVisibility',
        value: this.layer.attributes.hasDefaultVisibility
      })

      // Adjust children of Category if the Category hides them.
      this.setHiddenChildrenForCategory()
    },

    /**
     *
     * Fires when Element is added to a Category-Item that hides his children
     * - sets the default visiblity of the Category
     * - removes the visibilityGroup
     *
     */
    setHiddenChildrenForCategory () {
      // Adjust children of Category if the Category hides them.
      this.childElements.forEach((el) => {
        // Set default visibility of the parent category.
        this.setAttributeForLayer({
          id: el.id,
          attribute: 'hasDefaultVisibility',
          value: this.layer.attributes.hasDefaultVisibility
        })

        /*
         * Reset visibilityGroupId.
         * for now children of that type of Category can't be in a visibility-group.
         * that would make it even more complex
         */
        this.setAttributeForLayer({
          id: el.id,
          attribute: 'visibilityGroupId',
          value: ''
        })
      })
    },

    /**
     * Set active state when clicking on an overlay
     */
    setActiveState () {
      if (!hasPermission('feature_map_layer_visibility') ||
        this.layer.type !== 'GisLayer' ||
        this.layer.attributes.isBaseLayer ||
        this.isLoading ||
        this.isChildOfCategoryThatAppearsAsLayer) {
        return
      }
      if (this.preventActiveFromToggeling === false) {
        if (this.isActive) {
          this.$store.commit('Layers/setActiveLayerId', '')
        } else {
          this.$store.commit('Layers/setActiveLayerId', this.layer.id)
        }
      } else {
        this.preventActiveFromToggeling = false
      }
    },

    setOrderPosition () {
      this.setAttributeForLayer({
        id: this.element.id,
        attribute: this.sortingType,
        value: ((this.parentOrderPosition * 100) + (this.index + 1))
      })
    },

    /**
     * Set/unset hover-state for row
     */
    mouseOverElement () {
      if (this.isLoading || this.layer.attributes.layerType !== 'overlay') {
        return false
      }
      this.$store.commit('Layers/setHoverLayerId', this.layer.id)
    },

    mouseOutElement () {
      this.$store.commit('Layers/setHoverLayerId', '')
    },

    /**
     * Set/unset hover-state for visibilitygroup-icon
     */
    setIconHoverState () {
      if (this.isLoading) {
        return false
      }
      if (this.layer.attributes.layerType === 'overlay' && typeof this.activeLayer.id !== 'undefined') {
        this.$store.commit('Layers/setHoverLayerIconIsHovered', true)
      } else {
        this.unsetIconHoverState()
      }
    },

    unsetIconHoverState () {
      this.$store.commit('Layers/setHoverLayerIconIsHovered', false)
    },

    /**
     * Change defaultVisibility
     */
    toggleHasDefaultVisibility () {
      this.preventActiveFromToggeling = true
      // Can't be updated when it's a visiblityGroup
      if ((this.layer.attributes.visibilityGroupId !== '' && this.layer.type !== 'GisLayerCategory') || this.isLoading) {
        return
      }

      this.setAttributeForLayer({
        id: this.layer.id,
        attribute: 'hasDefaultVisibility',
        value: (this.layer.attributes.hasDefaultVisibility === false)
      })

      // If the Category hides his children we have to change the Value for the Children too so it will work in public detail
      if (this.layer.attributes.layerWithChildrenHidden) {
        this.setHiddenChildrenForCategory()
      }
    },

    /**
     * Set/unset the visibilitygroupId
     */
    toggleVisibilityGroup () {
      /*
       * If there is no active Layer the clicked Layer can't be grouped with it.
       * so we set the clicked one as active instead
       * base-layer can't be group at all
       */
      let newVisibilityGroupId = (typeof this.activeLayer.attributes === 'undefined') ? '' : this.activeLayer.attributes.visibilityGroupId
      this.preventActiveFromToggeling = true

      if (typeof this.activeLayer.id === 'undefined' ||
        this.layerType === 'base' ||
        this.isActive ||
        (this.layer.attributes.visibilityGroupId !== newVisibilityGroupId && this.layer.attributes.visibilityGroupId !== '') ||
        this.hasSettingsThatPreventGrouping ||
        this.isLoading) {
        return false
      }

      if (newVisibilityGroupId === '' || typeof newVisibilityGroupId === 'undefined') {
        // If the active Layer has no visibilitygroupId, create one and attach it to the active and the clicked Layer
        newVisibilityGroupId = uuid()
        this.setAttributeForLayer({
          id: this.activeLayer.id,
          attribute: 'visibilityGroupId',
          value: newVisibilityGroupId
        })
        this.setAttributeForLayer({
          id: this.layer.id,
          attribute: 'visibilityGroupId',
          value: newVisibilityGroupId
        })
      } else if (this.layer.attributes.visibilityGroupId === newVisibilityGroupId) {
        /*
         * Deselect visibilitygroup
         * if this is just one Element left (next to it self), unchain it too
         */
        const relatedLayers = this.$store.getters['Layers/elementsListByAttribute']({
          type: 'visibilityGroupId',
          value: newVisibilityGroupId
        })
        if (relatedLayers.length <= 2) {
          for (let i = 0; i < relatedLayers.length; i++) {
            this.setAttributeForLayer({
              id: relatedLayers[i].id,
              attribute: 'visibilityGroupId',
              value: null
            })
          }
        } else {
          this.setAttributeForLayer({
            id: this.layer.id,
            attribute: 'visibilityGroupId',
            value: null
          })
        }
      } else {
        // Just set new visibilitygroupId to clicked Layer
        this.setAttributeForLayer({
          id: this.layer.id,
          attribute: 'visibilityGroupId',
          value: newVisibilityGroupId
        })
      }
    },

    ...mapMutations('Layers', ['setAttributeForLayer', 'setChildrenFromCategory'])
  },

  beforeCreate () {
    this.$options.components.AdminLayerListItem = AdminLayerListItem
  }
}
</script>
