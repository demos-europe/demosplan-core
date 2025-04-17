<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--

    !!! THIS COMPONENT SHOULD BE 3 COMPONENTS OR MERGED WITH DpOlMapDrawFeature !!!
    !!! THIS COMPONENT SHOULD BE 3 COMPONENTS OR MERGED WITH DpOlMapDrawFeature !!!
    !!! THIS COMPONENT SHOULD BE 3 COMPONENTS OR MERGED WITH DpOlMapDrawFeature !!!
    !!! THIS COMPONENT SHOULD BE 3 COMPONENTS OR MERGED WITH DpOlMapDrawFeature !!!

  -->
  <!--
      # component requires an Layer with Features
      #
      # Required Props:
      # target<String>
      # >>> Has to match the Layername of the Layer wich includes the Vector-Feature this Component should be able to manipulate
      #
      # Emits:
      # > 'setDrawingActive'
      # >>> fired after clicking the Control
      # >>> Payload: [name]|''
      #
      # On:
      # > 'setDrawingActive'
      # >>> updates the active-state of the control and the featureLayer (Einzeichnungs-Ebene)
      # >>> checks against the provided name
  -->
  <usage variant="With Control rendered">
    <dp-ol-map-edit-feature
      target="nameToIdentifyTheEvent/FeatureLayer"
    />
  </usage>
  <!--
    for a read-only-layer just the features are neccessary.
    optional you can fit/zoom the map to the drawing
   -->
  <usage variant="read-Only">
    <dp-ol-map-draw-feature
      :features="features"
      :fitDrawing="true"
    />
  </usage>
</documentation>

<template>
  <span ref="rootElement">
    <button
      type="button"
      @click="toggle"
      data-cy="editButtonDesc"
      v-tooltip="{
        classes: this.tooltipClass,
        content: Translator.trans('explanation.territory.help.edit',{ editTool: Translator.trans('map.territory.tools.edit') })
      }"
      class="btn--blank u-ml-0_5 o-link--default weight--bold"
      :class="{ 'color-highlight' : currentlyActive }">
      <slot name="editButtonDesc">
        {{ Translator.trans('map.territory.tools.edit') }}
      </slot>
    </button>
    <button
      type="button"
      @click="removeFeature"
      data-cy="removeButtonDesc"
      v-tooltip="{
        classes: this.tooltipClass,
        content: Translator.trans('explanation.territory.help.delete.selected', {
          deleteSelectedTool: Translator.trans('map.territory.tools.removeSelected'),
          editTool: Translator.trans('map.territory.tools.edit')
        })
      }"
      class="btn--blank u-ml-0_5 weight--bold"
      :class="disabled ? 'color--grey-light cursor-default' : 'o-link--default'">
      <slot name="removeButtonDesc">
        {{ Translator.trans('map.territory.tools.removeSelected') }}
      </slot>
    </button>
    <button
      type="button"
      @click="clearAll"
      data-cy="removeAllButtonDesc"
      v-tooltip="{
        classes: this.tooltipClass,
        content: Translator.trans('explanation.territory.help.delete.all', { deleteAllTool: Translator.trans('map.territory.tools.removeAll') })
      }"
      class="btn--blank u-ml-0_5 o-link--default weight--bold">
      <slot name="removeAllButtonDesc">
        {{ Translator.trans('map.territory.tools.removeAll') }}
      </slot>
    </button>
  </span>
</template>

<script>
import { Modify, Select } from 'ol/interaction'
import { hasOwnProp } from '@demos-europe/demosplan-ui'
import { v4 as uuid } from 'uuid'
import VectorLayer from 'ol/layer/Vector'

export default {
  name: 'DpOlMapEditFeature',

  inject: ['olMapState'],

  props: {
    // The name is used to identify the Events
    name: {
      required: false,
      type: String,
      default: uuid()
    },

    // Required to target a Layer with Vector-Featurs
    target: {
      required: true,
      type: [String, Array]
    },

    initActive: {
      required: false,
      type: Boolean,
      default: false
    },

    defaultControl: {
      required: false,
      type: Boolean,
      default: false
    }
  },

  emits: [
    'setDrawingActive'
  ],

  data () {
    return {
      selectInteraction: new Select({
        hitTolerance: 10,
        wrapX: false,
        filter: (_feat, layer) => {
          if (layer) {
            const name = layer.get('name')

            if (name === 'layer:mapSettingsPreviewMapExtent' || name === 'layer:mapSettingsPreviewInitExtent') {
              return false
            }
          }

          return true
        }
      }),
      modifyInteraction: null,
      currentlyActive: this.initActive,
      selectedFeatureId: [],
      layerNameOfSelectedFeature: '',
      disabled: true,
      zIndexUltimate: false,
      targets: Array.isArray(this.target) ? this.target : [this.target]
    }
  },

  computed: {
    tooltipClass () {
      return this.zIndexUltimate ? 'z-ultimate' : ''
    },

    map () {
      return this.olMapState.map
    }
  },

  methods: {
    activateTool (name) {
      if (this.map === null || this.renderControl === false) {
        return
      }

      if ((!this.currentlyActive && name === this.name) || (this.defaultControl && name === '')) {
        this.selectInteraction.getFeatures().on('add', this.addInteraction)
        this.selectInteraction.getFeatures().on('remove', this.removeInteraction)

        this.map.addInteraction(this.selectInteraction)
        this.map.addInteraction(this.modifyInteraction)
        this.currentlyActive = true
      } else {
        this.map.removeInteraction(this.selectInteraction)
        this.map.removeInteraction(this.modifyInteraction)
        this.currentlyActive = false
      }
    },

    addInteraction (event) {
      const id = 'selected' + uuid()

      if (this.selectedFeatureId.indexOf(id) === -1) {
        this.selectedFeatureId.push(id)
        if (hasOwnProp(event, 'element')) {
          event.element.set('id', id)
          this.disabled = false
        }
      }
    },

    clearAll () {
      if (!confirm(Translator.trans('map.territory.removeAll.confirmation'))) {
        return
      }

      this.map.getLayers().forEach(layer => {
        if (layer instanceof VectorLayer && this.targets.includes(layer.get('name'))) {
          this.selectInteraction.getFeatures().clear()
          layer.getSource().clear()
        }
      })

      this.resetSelection()
    },

    /**
     * Get the z-index of a DOM element.
     * @param element
     * @return {string|*}
     */
    getZIndex (element) {
      const z = window.getComputedStyle(element).getPropertyValue('z-index')

      if (isNaN(z)) {
        return (element.nodeName === 'HTML') ? 1 : this.getZIndex(element.parentNode)
      }

      return z
    },

    toggle () {
      if (this.currentlyActive === false) {
        this.$root.$emit('setDrawingActive', this.name)
      } else {
        this.$root.$emit('setDrawingActive', '')
      }
    },

    removeFeature () {
      const features = this.selectInteraction.getFeatures()

      if (features !== null && features.getLength() > 0) {
        features.getArray().forEach(feature => {
          const featureInSelection = this.selectedFeatureId.indexOf(feature.getProperties().id)
          if (featureInSelection > -1) {
            this.map.getLayers().forEach(layer => {
              if (layer instanceof VectorLayer && this.targets.includes(layer.get('name')) && layer.getSource().hasFeature(feature)) {
                layer.getSource().removeFeature(feature)
              }
            })
          }
        })

        this.resetSelection()
      }
    },

    removeInteraction (event) {
      if (hasOwnProp(event, 'element')) {
        event.element.get('id')
        const elIdx = this.selectedFeatureId.indexOf(event.element.get('id'))
        this.selectedFeatureId.splice(elIdx, 1)
        if (this.selectedFeatureId.length <= 0) {
          this.disabled = true
        }
      }
    },

    resetSelection () {
      this.selectedFeatureId = []
      this.selectInteraction.getFeatures().clear()
      this.$nextTick(() => {
        this.map.render()
      })
    }
  },

  mounted () {
    this.modifyInteraction = new Modify({ features: this.selectInteraction.getFeatures() })
    this.$root.$on('setDrawingActive', name => this.activateTool(name))

    /**
     * This logic should be implemented within demosplan-ui tooltip directive,
     * once it has been refactored to use an upto date version of v-tooltip.
     */
    if (this.getZIndex(this.$refs.rootElement) > 9999) {
      this.zIndexUltimate = true
    }
  }
}
</script>
