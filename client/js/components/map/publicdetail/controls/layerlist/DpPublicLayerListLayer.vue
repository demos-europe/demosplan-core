<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <li
    v-if="layer.attributes.isEnabled && !layer.attributes.isScope && !layer.attributes.isBplan"
    :id="id"
    :title="layerTitle"
    :class="[(isVisible && layer.attributes.canUserToggleVisibility) ? prefixClass('is-active') : '', prefixClass('c-map__group-item c-map__layer flex items-center space-x-1')]"
    @click="toggleFromSelf(false)">
    <span
      :class="prefixClass('c-map__group-item-controls')"
      @mouseover="toggleOpacityControl(true)"
      @mouseout="toggleOpacityControl(false)">
      <button
        :class="prefixClass('btn--blank btn--focus w-3 text-left flex')"
        :aria-label="layer.attributes.name + ' ' + statusAriaText"
        :data-cy="dataCy"
        @focus="toggleOpacityControl(true)"
        @click.prevent.stop="toggleFromSelf(true)"
        @keydown.tab.shift.exact="toggleOpacityControl(false)">
        <i
          :class="prefixedStatusIcon"
          aria-hidden="true" />
      </button>
      <span
        :class="prefixClass('c-map__opacity-control u-ml-0_5')"
        v-show="showOpacityControl && isVisible">
        <input
          type="range"
          min="0"
          max="100"
          step="2"
          v-model="opacity"
          :aria-label="layer.attributes.name + ' ' + Translator.trans('opacity.percent')"
          aria-valuemin="0"
          aria-valuemax="100"
          aria-orientation="horizontal"
          @input="setOpacity"
          @change="setAndSaveOpacity"
          @focus="toggleOpacityControl(true)"
          @blur="toggleOpacityControl(false)"
          @click.stop="">
      </span>
    </span>
    <span
      :class="prefixClass('c-map__group-item-name o-hellip--nowrap')"
      v-show="!showOpacityControl">
      {{ layer.attributes.name }}
    </span>
    <dp-contextual-help
      v-if="contextualHelpText"
      class="c-map__layerhelp u-mt-0_125"
      :text="contextualHelpText" />
  </li>
</template>

<script>
import { DpContextualHelp, prefixClass } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'

export default {
  name: 'DpPublicLayerListLayer',

  components: { DpContextualHelp },

  props: {
    dataCy: {
      type: String,
      required: false,
      default: 'publicLayerListLayer'
    },

    layer: {
      type: Object,
      required: true
    },

    visible: {
      type: Boolean,
      required: true,
      default: true
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
    },

    parentIsVisible: {
      type: Boolean,
      required: false,
      default: true
    }
  },

  emits: [
    'layer:hideOtherCategories',
    'layer:showParent',
    'layer:showVisibiltyGroupLayer',
    'layer:toggle',
    'layer:toggleLegend',
    'layer:toggleOtherBaselayers',
    'layer:toggleVisibiltyGroup',
    'layer-opacity:change',
    'layer-opacity:changed'
  ],

  data () {
    return {
      // isVisible: true,
      showOpacityControl: false,
      // showVisibilityGroup: false,
      opacity: 100,
      tooltipExpanded: false
    }
  },

  computed: {
    ...mapState('Layers', [
      'layerStates'
    ]),
    ...mapGetters('Layers', [
      'isLayerVisible',
      'isVisibilityGroupVisible'
    ]),

    contextualHelpText () {
      const contextualHelp = this.$store.getters['Layers/element']({ id: this.layer.id, type: 'ContextualHelp' })
      const hasContextualHelp = contextualHelp && contextualHelp.attributes.text
      return hasContextualHelp ? contextualHelp.attributes.text : ''
    },

    id () {
      return this.layer.id // .replace(/-/g, '')
    },

    isVisible () {
      return this.isLayerVisible(this.layer.id)
    },

    layerTitle () {
      //  Return title only if contextualHelp is currently not shown
      return this.tooltipExpanded === false ? this.layer.attributes.name : ''
    },

    statusIcon () {
      return this.setStatusIcon()
    },

    prefixedStatusIcon () {
      return this.prefixClass('fa ' + this.setStatusIcon())
    },

    showVisibilityGroup () {
      this.isVisibilityGroupVisible(this.layer.attributes.visibilityGroupId)
    },

    statusAriaText () {
      let text = ''
      switch (this.statusIcon) {
        case 'fa-lock':
          text = 'maplayer.locked'
          break
        case 'fa-link':
          text = 'maplayer.locked'
          break
        case 'fa-eye-slash':
          text = 'maplayer.show'
          break
        case 'fa-eye':
          text = 'maplayer.hide'
          break
      }

      return Translator.trans(text)
    }
  },

  methods: {
    ...mapActions('Layers', [
      'updateLayerVisibility'
    ]),

    ...mapMutations('Layers', [
      'removeVisibleLayer',
      'removeVisibleVisibilityGroup',
      'setLayerState',
      'setVisibleVisibilityGroup',
      'updateState'
    ]),

    setStatusIcon () {
      if (!this.layer.attributes.canUserToggleVisibility) {
        return 'fa-lock'
      } else if (this.showVisibilityGroup) {
        return 'fa-link'
      } else if (this.isVisible === false && !this.showVisibilityGroup) {
        return 'fa-eye-slash'
      } else {
        // If(this.isVisible && false === this.showVisibilityGroup)
        return 'fa-eye'
      }
    },

    toggle (isVisible) {
      if (!this.layer.attributes.canUserToggleVisibility) {
        return
      }

      // this.isVisible = (typeof isVisible !== 'undefined') ? isVisible : (!this.isVisible)

      // @todo handle Baselayer
      const exclusively = this.layer.attributes.isBaseLayer
      // this.$root.$emit('layer:toggle', { id: this.id, exclusively, isVisible: this.isVisible })

      // Toggle overlays
      this.updateLayerVisibility({
        id: this.layer.id,
        value: !this.isVisible,
        layerGroupsAlternateVisibility: this.layerGroupsAlternateVisibility,
        exclusively
      })
    },

    toggleFromSelf (showOpacityControl) {
      if (this.tooltipExpanded === true || this.layer.attributes.canUserToggleVisibility === false) {
        return
      }
      if (this.layer.attributes.isBaseLayer) {
        this.$root.$emit('layer:toggleOtherBaselayers', this.id)
      } else {
        // this.updateLayerVisibility({ id: this.layer.id, value: this.isVisible, layerGroupsAlternateVisibility: this.layerGroupsAlternateVisibility })
      }

      this.toggle()

      if (showOpacityControl === true) {
        this.isVisible ? this.toggleOpacityControl(true) : this.toggleOpacityControl(false)
      }

      // if (this.layer.attributes.isBaseLayer === false) {
      //   // If item is visible after toggle, also toggle parent category visible
      //   if (this.isVisible) {
      //     this.$root.$emit('layer:showParent', this.layer.attributes.categoryId)
      //
      //     // If feature layerGroupsAlternateVisibility is activated
      //     if (this.layerGroupsAlternateVisibility) {
      //       this.$root.$emit('layer:hideOtherCategories', { groupId: this.layer.attributes.visibilityGroupId, categoryId: this.layer.attributes.categoryId })
      //     }
      //   }
      //
      //   // If item is in a visibility group, also toggle other items in that group
      //   if (this.layer.attributes.visibilityGroupId !== '') {
      //     this.$root.$emit('layer:toggleVisibiltyGroup', { visibilityGroupId: this.layer.attributes.visibilityGroupId, layerId: this.layer.id, isVisible: this.isVisible })
      //   }
      // }
    },

    // toggleFromVisibilityGroup (visibilityGroupId, layerId, isVisible) {
    //   if (layerId !== this.layer.id && visibilityGroupId === this.layer.attributes.visibilityGroupId) {
    //     this.toggle(isVisible)
    //   }
    // },

    // toggleFromOtherBaselayer (layerId) {
    //   if (layerId !== this.layer.id) {
    //     this.toggle(false)
    //   }
    // },

    /*
    showVisibilityGroupLayer (visibilityGroupId, calleeId, hoverState) {
      if (calleeId !== this.layer.id && visibilityGroupId === this.layer.attributes.visibilityGroupId) {
        this.showVisibilityGroup = hoverState
      }
    },
    */

    setOpacity (e) {
      let val = e.target.value
      this.$store.commit('Layers/setAttributeForLayer', { id: this.id, attribute: 'opacity', value: val })
      if (isNaN(val * 1)) return false
      val /= 100
      this.$root.$emit('layer-opacity:change', { id: this.id, opacity: val })
    },

    saveOpacity () {
      this.$root.$emit('layer-opacity:changed', { id: this.id, opacity: this.opacity })
    },

    setAndSaveOpacity (e) {
      this.setOpacity(e).saveOpacity()
    },

    toggleOpacityControl (overObject) {
      /* Show only if layer is visible / hide should always be possible */
      /* mouseover -> overObject = true */
      /* mouseout -> overObject = false */
      if (this.isVisible || overObject === false) {
        this.showOpacityControl = overObject && this.layer.attributes.canUserToggleVisibility === true
      }

      if (this.layer.attributes.visibilityGroupId !== '') {
        // if (overObject) {
        //   this.setVisibleVisibilityGroup({ id: this.layer.attributes.visibilityGroupId })
        // } else {
        //   this.removeVisibleVisibilityGroup({ id: this.layer.attributes.visibilityGroupId })
        // }
        // this.$root.$emit('layer:showVisibiltyGroupLayer', { visibilityGroupId: this.layer.attributes.visibilityGroupId, layerId: this.layer.id, hoverState: overObject })
      }
    },

    prefixClass (classList) {
      return prefixClass(classList)
    }
  },

  created () {
    // this.isVisible = this.visible
    this.opacity = this.layer.attributes.opacity

    if (this.visible) {
      this.setLayerState( { id: this.id, key: 'isVisible', value: true })
    }

    // if (this.layer.attributes.isBaseLayer) {
    //   this.$root.$on('layer:toggleOtherBaselayers', layerId => {
    //     this.toggleFromOtherBaselayer(layerId)
    //   })
    // }

    if (this.layer.attributes.isBaseLayer === false) {
      // this.$root.$on('layer:toggleChildLayer', ({ layer, isVisible, visibilityGroupId }) => this.toggleFromCategory(layer, isVisible, visibilityGroupId))
      // this.$root.$on('layer:toggleVisibiltyGroup', ({ layerId, isVisible, visibilityGroupId }) => this.toggleFromVisibilityGroup(visibilityGroupId, layerId, isVisible))
      // this.$root.$on('layer:showVisibiltyGroupLayer', ({ visibilityGroupId, layerId, hoverState }) => this.showVisibilityGroupLayer(visibilityGroupId, layerId, hoverState))

      // Set parent-categories to visible if the layer is visible
      if (this.isVisible) {
        // this.$root.$emit('layer:showParent', this.layer.attributes.categoryId)
      }
    }

    // this.$root.$on('layer:toggleLayer', ({ layerId, isVisible }) => {
      // if (layerId !== this.id) {
        // return
      // }
      // this.toggle(isVisible)
    // })
  }
}
</script>
