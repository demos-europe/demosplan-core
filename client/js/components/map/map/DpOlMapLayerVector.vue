<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import drawStyle from './utils/drawStyle'
import { GeoJSON } from 'ol/format'
import { hasOwnProp } from '@demos-europe/demosplan-ui'
import { v4 as uuid } from 'uuid'
import VectorLayer from 'ol/layer/Vector'
import VectorSource from 'ol/source/Vector'

export default {
  name: 'DpOlMapLayerVector',

  inject: ['olMapState'],

  props: {
    //  Expected format: http://geojson.org
    features: {
      required: false,
      type: Object,
      default: () => ({})
    },

    name: {
      required: false,
      type: String,
      default: uuid()
    },

    zoomToDrawing: {
      required: false,
      type: Boolean,
      default: false
    },

    drawStyle: {
      required: false,
      type: String,
      default: ''
    }
  },

  emits: [
    'layerFeatures:changed'
  ],

  data () {
    return {
      drawingExtent: '',
      layer: {}
    }
  },

  computed: {
    map () {
      return this.olMapState.map
    },

    isFeatureGeoJSON () {
      return JSON.stringify(this.features) !== JSON.stringify({}) && this.features != null
    },

    geoJsonFeatures () {
      // Validate geojson? https://github.com/craveprogramminginc/GeoJSON-Validation
      return this.isFeatureGeoJSON ? new GeoJSON().readFeatures({ properties: { id: `feature:${this.name}` }, ...this.features }) : []
    }
  },

  watch: {
    features: {
      handler () {
        this.map.removeLayer(this.layer)
        this.addLayer()
      },
      deep: true
    }
  },

  methods: {
    init () {
      if (this.map === null) {
        return
      }

      this.setCenter()

      this.addLayer()
    },

    setCenter () {
      if (this.isFeatureGeoJSON && hasOwnProp(this.features, 'type') && this.features.type === 'Feature') {
        if (this.features.geometry.coordinates.length === 2) {
          /*
           *  Centering the map view around the given coordinate.
           *  This only works with point feature atm.
           *  For centering based on multiple geometries see https://gis.stackexchange.com/a/240405
           */
          const center = this.geoJsonFeatures[0].getGeometry().getCoordinates()
          this.map.getView().setCenter(center)
        }
      }
    },

    addLayer () {
      //  Define layer source to draw into
      const vectorSource = new VectorSource({
        format: new GeoJSON(),
        projection: this.map.getView().getProjection(),
        features: this.geoJsonFeatures
      })
      //  Define layer source to draw into
      const VectorSourceOptions = {
        format: new GeoJSON(),
        projection: this.map.getView().getProjection()
      }

      // Validate geojson? https://github.com/craveprogramminginc/GeoJSON-Validation
      if (this.isFeatureGeoJSON) {
        VectorSourceOptions.features = new GeoJSON().readFeatures(this.features)
      }

      this.drawingExtent = vectorSource.getExtent()

      this.layer = new VectorLayer({
        source: vectorSource,
        title: this.name, // Title?
        name: this.name,
        type: 'draw',
        properties: {
          name: `layer:${this.name}`
        },
        style: this.drawStyle !== '' ? drawStyle(JSON.parse(this.drawStyle)) : drawStyle(this.olMapState.drawStyles)
      })

      this.map.addLayer(this.layer)

      //  Whenever drawing changes, emit current features
      vectorSource.on('change', () => {
        this.$emit('layerFeatures:changed', vectorSource.getFeatures())
      })
    }
  },

  mounted () {
    this.init()

    if (this.zoomToDrawing && this.isFeatureGeoJSON) {
      this.map.updateSize()
      this.$nextTick(() => {
        this.map.getView().fit(this.drawingExtent, { size: this.map.getSize() })
      })
    }
  },

  render: () => null
}
</script>
