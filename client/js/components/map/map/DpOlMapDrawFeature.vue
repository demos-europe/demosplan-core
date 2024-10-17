<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--
      # component attaches a featureLayer (Einzeichnungs-Ebene) to an Ol5-map
      # It should be a child of DpOlMap
      # An olMapState-Object has to be provided (if used with DpOlMap it comes from there)
      #
      #
      # Emits:
      # > 'layerFeatures:changed'
      # >>> fired after feature-data changed
      # >>> Payload: features
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
    <dp-ol-map-draw-feature
      name="nameToIdentifyTheEvent/FeatureLayer"
      :features="features"
      :render-control="true"
      :label="LabelName"
      icon-class="fa fa-map"
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
  <button
    v-if="renderControl"
    class="btn--blank u-ml-0_5 o-link--default weight--bold"
    :class="{'color-highlight': currentlyActive}"
    type="button"
    :title="title"
    @click="toggle">
    {{ label }}
    <i
      v-if="icon"
      class="fa"
      :class="iconClass"
      aria-hidden="true" />
  </button>
</template>

<script>
import { Draw, Snap } from 'ol/interaction'
import drawStyle from './utils/drawStyle'
import { GeoJSON } from 'ol/format'
import { hasOwnProp } from '@demos-europe/demosplan-ui'
import { v4 as uuid } from 'uuid'
import VectorLayer from 'ol/layer/Vector'
import VectorSource from 'ol/source/Vector'

export default {
  name: 'DpOlMapDrawFeature',

  inject: ['olMapState'],

  props: {
    defaultControl: {
      required: false,
      type: Boolean,
      default: false
    },

    drawStyle: {
      required: false,
      type: [Object, null],
      default: null
    },

    features: {
      required: false,
      type: Object,
      default: () => ({})
    },

    fitDrawing: {
      required: false,
      type: Boolean,
      default: false
    },

    icon: {
      required: false,
      type: Boolean,
      default: false
    },

    iconClass: {
      required: false,
      type: String,
      default: 'fa-map'
    },

    initActive: {
      required: false,
      type: Boolean,
      default: false
    },

    label: {
      required: false,
      type: String,
      default: ''
    },

    name: {
      required: false,
      type: String,
      default: uuid()
    },

    options: {
      type: Object,
      required: false,
      default: () => ({})
    },

    renderControl: {
      required: false,
      type: Boolean,
      default: false
    },

    title: {
      required: false,
      type: String,
      default: ''
    },

    type: {
      required: false,
      type: String,
      default: 'Point'
    }
  },

  emits: [
    'layerFeatures:changed',
    'setDrawingActive'
  ],

  data () {
    return {
      currentlyActive: this.initActive,
      drawingExtent: '',
      drawInteraction: null,
      featureId: uuid(),
      layerToDrawInto: null,
      snap: null,
      vectorSourceOptions: {}
    }
  },

  computed: {
    map () {
      return this.olMapState.map
    }
  },

  methods: {
    /**
     *
     * @param name
     */
    activateTool (name) {
      if (this.map === null || this.renderControl === false) {
        return
      }
      if (((this.currentlyActive === false && name === this.name) || (this.defaultControl && name === ''))) {
        const style = this.drawStyle ? this.drawStyle : this.olMapState.drawStyles

        this.drawInteraction = new Draw({
          source: this.layerToDrawInto.getSource(),
          type: this.type,
          name: this.name,
          id: `draw${this.featureId}`,
          style: drawStyle(style)
        })

        if (this.type === 'Point' && !this.options.multiplePoints) {
          this.drawInteraction.on('drawstart', () => {
            this.layerToDrawInto.getSource().clear()
          })
        }

        this.snap = new Snap({
          source: new VectorSource()
        })
        this.map.addInteraction(this.drawInteraction)
        this.map.addInteraction(this.snap)
        this.currentlyActive = true
      } else {
        this.map.removeInteraction(this.drawInteraction)
        this.map.removeInteraction(this.snap)
        this.currentlyActive = false
      }
    },

    clearAll () {
      this.map.getLayers().forEach(layer => {
        if (layer instanceof VectorLayer && this.name === layer.get('name')) {
          layer.getSource().clear()
          this.map.removeLayer(layer)
        }
      })
    },

    init () {
      if (this.map === null) {
        return
      }

      //  Define layer source to draw into
      this.vectorSourceOptions = {
        format: new GeoJSON(),
        projection: this.map.getView().getProjection(),
        id: `source${this.featureId}`
      }

      // Validate geojson? https://github.com/craveprogramminginc/GeoJSON-Validation
      if (JSON.stringify(this.features) !== JSON.stringify({})) {
        this.vectorSourceOptions.features = new GeoJSON().readFeatures(this.features)
      }

      const layerSource = new VectorSource(this.vectorSourceOptions)

      this.drawingExtent = layerSource.getExtent()

      const style = this.drawStyle ? this.drawStyle : this.olMapState.drawStyles

      const layer = new VectorLayer({
        source: layerSource,
        title: this.name, // Title?
        name: this.name,
        type: this.type,
        id: `layer${this.featureId}`,
        style: drawStyle(style),
        // make sure drawing layer is always on top
        zIndex: 1000
      })

      this.map.addLayer(layer)

      //  Atm this does not work for layer groups. See https://gis.stackexchange.com/a/240405
      this.map.getLayers().forEach(l => {
        if (l instanceof VectorLayer && l.get('name') === this.name) {
          this.layerToDrawInto = l
        }
      })

      //  Whenever drawing changes, emit current features
      layerSource.on('change', () => {
        this.$emit('layerFeatures:changed', new GeoJSON().writeFeatures(layerSource.getFeatures()))
      })

      if (this.fitDrawing) {
        this.fitMapToDrawing()
      }
      if (this.currentlyActive) {
        this.activateTool(this.name)
      }

      this.map.updateSize()
      this.map.render()
      this.$forceUpdate()
    },

    fitMapToDrawing () {
      //  Add features to vector source if set via props
      if (this.features.type === 'FeatureCollection' &&
        this.features.features.length === 1 &&
        hasOwnProp(this.features.features[0], 'type') &&
        this.features.features[0].type === 'Feature' &&
        this.features.features[0].geometry.coordinates.length === 2) {
        const center = this.vectorSourceOptions.features[0].getGeometry().getCoordinates()
        /*
         *  Centering the map view around the given coordinate.
         *  This only works with point feature atm.
         *  For centering based on multiple geometries see https://gis.stackexchange.com/a/240405
         */
        this.map.getView().setCenter(center)
      } else {
        this.map.getView().fit(this.drawingExtent)
      }
    },

    getExtent () {
      return this.drawingExtent
    },

    toggle () {
      if (this.currentlyActive === false) {
        this.$root.$emit('setDrawingActive', this.name)
      } else {
        this.$root.$emit('setDrawingActive', '')
      }
    }
  },

  mounted () {
    this.init()
    this.$root.$on('setDrawingActive', name => this.activateTool(name))
  }
}
</script>
