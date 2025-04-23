<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <li
    v-if="layers.length > 0"
    :class="prefixClass('c-map__group-item')"
    :title="group.attributes.name">
    <div
      :class="[
        isVisible ? prefixClass('is-active') : '',
        prefixClass('c-map__layer')
      ]">
      <span :class="prefixClass('c-map__group-item-controls')">
        <button
          :class="prefixClass('btn--blank btn--focus w-3 text-left')"
          :aria-label="group.attributes.name + ' ' + (isVisible ? Translator.trans('maplayer.category.hide') : Translator.trans('maplayer.category.show'))"
          @click="toggleFromSelf">
          <i
            :class="[isVisible ? prefixClass('fa-eye') : prefixClass('fa-eye-slash'), prefixClass('fa')]"
            aria-hidden="true" />
        </button>
        <button
          v-if="false === appearsAsLayer"
          :class="prefixClass('btn--blank btn--focus w-3 text-left')"
          :aria-label="group.attributes.name + ' ' + (unfolded ? Translator.trans('maplayer.category.close') : Translator.trans('maplayer.category.open'))"
          @click="fold">
          <i
            :class="[unfolded ? prefixClass('fa fa-folder-open') : prefixClass('fa fa-folder')]"
            aria-hidden="true" />
        </button>

      </span>
      <span
        @click="appearsAsLayer ? toggleFromSelf() : fold()"
        :class="prefixClass('c-map__group-item-name o-hellip--nowrap')">
        {{ group.attributes.name }}
      </span>
      <dp-contextual-help
        v-if="'' !== contextualHelp"
        class="c-map__layerhelp"
        :text="contextualHelp" />
    </div>
    <dp-public-layer-list
      :layer-groups-alternate-visibility="layerGroupsAlternateVisibility"
      :layers="layers"
      :unfolded="unfolded"
      :class="[appearsAsLayer ? prefixClass('sr-only') : prefixClass('c-map__group-item-child u-mr-0')]" />
  </li>
</template>

<script>
import { DpContextualHelp, hasOwnProp, prefixClass } from '@demos-europe/demosplan-ui'
import DpPublicLayerList from './DpPublicLayerList'
import { mapGetters } from 'vuex'

export default {
  name: 'DpPublicLayerListCategory',
  components: { DpContextualHelp },

  props: {
    group: {
      type: Object,
      required: true
    },

    layerType: {
      type: String,
      required: false,
      default: 'overlay'
    },

    layerGroupsAlternateVisibility: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  data () {
    return {
      isVisible: true,
      unfolded: false,
      appearsAsLayer: this.group.attributes.layerWithChildrenHidden,
      contextualHelp: '',
      tooltipExpanded: false
    }
  },

  computed: {
    contextualHelpId () {
      return 'contextualHelp' + this.group.id
    },

    id () {
      return 'layergroup' + this.group.id.replaceAll('-', '')
    },

    isTopLevelCategory () {
      return this.rootId === this.group.attributes.parentId
    },

    layers () {
      return this.elementListForLayerSidebar(this.group.id, this.layerType, true)
    },

    ...mapGetters('Layers', ['rootId', 'element', 'elementListForLayerSidebar'])
  },

  methods: {
    fold () {
      this.unfolded = (this.unfolded === false)
    },

    prefixClass (classList) {
      return prefixClass(classList)
    },

    toggle (isVisible) {
      this.isVisible = (typeof isVisible !== 'undefined') ? isVisible : (this.isVisible === false)
    },

    toggleChildren (isVisible, visibilityGroupId) {
      visibilityGroupId = visibilityGroupId || null

      const visible = isVisible || this.isVisible
      this.$root.$emit('layer:toggleChildCategories', { categories: this.group.relationships.categories.data, isVisible: visible })
      this.$root.$emit('layer:toggleChildLayer', { layer: this.group.relationships.gisLayers.data, isVisible: visible, visibilityGroupId })
    },

    // Toggle self and children
    toggleFromSelf () {
      this.toggle()
      this.toggleChildren()

      if (this.isVisible) {
        this.$root.$emit('layer:showParent', this.group.attributes.parentId)
      }

      // If the feature layerGroupsAlternateVisibility is activated
      if (this.isVisible && (this.layerGroupsAlternateVisibility === true && this.group.attributes.layerWithChildrenHidden === false)) {
        this.$root.$emit('layer:hideOtherCategories', { groupId: this.id, categoryId: this.group.id })
      }
    },

    // Toggle category visible when child category is toggled visible
    toggleFromChild (id) {
      if (id === this.group.id) {
        this.toggle(true)
        this.$root.$emit('layer:showParent', this.group.attributes.parentId)
      }
    },

    // Toggle child categories when parent is toggled
    toggleFromParent (childObjects, isVisible) {
      if (childObjects.filter(category => category.id === this.group.id).length > 0) {
        this.toggle(isVisible)
        this.toggleChildren()
      }
    },

    isParentOf (elementList, categoryId) {
      let isParent = false

      for (const key in elementList) {
        //  Skip loop if the property is from prototype
        if (hasOwnProp(elementList, key) === false) continue

        const element = elementList[key]

        //  If the currently looped category is the direct parent of the toggled layer, return here
        if (element.type === 'GisLayerCategory' && element.id === categoryId) {
          return true
        }

        //  If the currently looped category is not the parent of toggled layer, check its child categories
        if (element.type === 'GisLayerCategory' && element.id !== categoryId) {
          const elementList = this.elementListForLayerSidebar(element.id, 'overlay', true)

          isParent = this.isParentOf(elementList, categoryId)
        }
      }

      return isParent
    },

    toggleFromOtherCategories (visibilityGroupId, categoryId) {
      if (this.isTopLevelCategory === false || /*  If this is not a top level category, return */
                    this.appearsAsLayer || /* If its a category that appears as layer it should not act like a category */
                    this.isParentOf(this.layers, categoryId) || /* If current category or any of its child categories is parent of toggled layer */
                    this.group.id === categoryId) {
        return
      }

      /*
       * If toggled layer is not a child of current category
       * or any of its child categories
       * and not a top level layer
       * toggle current category and its children invisible
       */
      if ((categoryId === this.rootId) === false) {
        this.toggle(false)
        this.toggleChildren(false, visibilityGroupId)
      }
    }

  },

  mounted () {
    this.$root.$on('layer:showParent', id => {
      this.toggleFromChild(id)
    })

    this.$root.$on('layer:toggleChildCategories', ({ categories, isVisible }) => {
      this.toggleFromParent(categories, isVisible)
    })

    if (this.layerGroupsAlternateVisibility) {
      this.$root.$on('layer:hideOtherCategories', ({ groupId, categoryId }) => {
        this.toggleFromOtherCategories(groupId, categoryId)
      })
    }

    // Handle data for the category that has to appear as Layer and hides his children
    if (this.group.attributes.layerWithChildrenHidden) {
      this.appearsAsLayer = true
      this.isVisible = this.group.attributes.hasDefaultVisibility
      // Get contextualHelp from all children
      this.layers.forEach(el => {
        const contextualHelp = this.element({ id: el.id, type: 'ContextualHelp' })
        if (hasOwnProp(contextualHelp, 'attributes') && contextualHelp.attributes.text !== '') {
          this.contextualHelp += contextualHelp.attributes.text + ' '
        }
      })
    }
  },

  beforeCreate () {
    this.$options.components.dpPublicLayerList = DpPublicLayerList
  }
}
</script>
