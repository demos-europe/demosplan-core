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

    isTopLevelCategory () {
      return this.rootId === this.group.attributes.parentId
    },

    isVisible () {
      return this.$store.state.Layers.layerStates[this.group.id]?.isVisible
    },

    layers () {
      return this.elementListForLayerSidebar(this.group.id, 'overlay', true)
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

    // Toggle self and children
    toggleFromSelf () {
      this.$store.dispatch('Layers/updateLayerVisibility', {
        id: this.group.id,
        isVisible: !this.isVisible,
        layerGroupsAlternateVisibility: this.layerGroupsAlternateVisibility
      })
    }
  },

  mounted () {
    // Handle data for the category that has to appear as Layer and hides his children
    if (this.appearsAsLayer) {
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
