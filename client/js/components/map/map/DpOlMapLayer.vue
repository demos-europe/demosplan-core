<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import { formatDate } from '@demos-europe/demosplan-ui'
import { getTopLeft } from 'ol/extent'
import TileGrid from 'ol/tilegrid/TileGrid'
import TileLayer from 'ol/layer/Tile'
import TileWMS from 'ol/source/TileWMS'

export default {
  name: 'DpOlMapLayer',

  inject: ['olMapState'],

  props: {
    attributions: {
      required: false,
      type: String,
      default: ''
    },

    layers: {
      required: true,
      type: String
    },

    name: {
      required: false,
      type: String,
      default: 'baselayer_global'
    },

    opacity: {
      required: false,
      type: Number,
      default: 100
    },

    order: {
      required: false,
      type: Number,
      default: 0
    },

    projection: {
      required: false,
      type: String,
      default: window.dplan.defaultProjectionLabel
    },

    title: {
      required: false,
      type: String,
      default: 'Global Baselayer'
    },

    url: {
      required: true,
      type: String
    }
  },

  data () {
    return {
      source: null
    }
  },

  computed: {
    /*
     * In several cases is shown map.attribution.default: in the view in basic settings for the procedure,
     * in the map by defining startMapSegment and
     * in the map in the general view in Settings for configuration for map and layer
     */
    defaultAttributions () {
      const currentYear = formatDate(new Date(), 'YYYY')
      return this.attributions
        ? this.attributions.replaceAll('{currentYear}', currentYear)
        : Translator.trans('map.attribution.default', {
          linkImprint: Routing.generate('DemosPlan_misccontent_static_imprint'),
          currentYear
        })
    },

    map () {
      return this.olMapState.map
    }
  },

  watch: {
    defaultAttributions () {
      this.source.setAttributions(this.defaultAttributions)
    }
  },

  methods: {
    addLayer () {
      if (this.map === null) {
        return
      }

      const splittedUrl = this.url.split('?')
      let url = splittedUrl[0]

      if (splittedUrl[1]) {
        // We have to ensure that the Service is not within the params,
        // since the `createSourceTileWMS` function from OL already adds it
        const params = splittedUrl[1].split('&').reduce((acc, curr) => {
          return (!curr.toUpperCase().includes('SERVICE=')) ? acc + curr : acc
        }, '?')

        url += params
      }

      this.source = createSourceTileWMS(url, this.layers, this.projection, this.defaultAttributions, this.map)
      const layer = createTileLayer(this.title, this.name, this.source, this.opacity)

      //  Insert layer at pos 0, making it the background layer
      this.map.getLayers().insertAt(this.order, layer)
    }
  },

  mounted () {
    this.addLayer()
  },

  render: () => null
}

/**
 * Create layer source for tile data from WMS servers.
 * @param {string}  url     URL of map service to query
 * @param {string}  layers  Comma separated string of layers to create source from
 * @param {string}  projection  String projection that should be used for the layer
 * @param {string}  attributions  Attributions for this layer
 * @param {object}  map     OpenLayers Map instance
 * @return {object} ol/source/TileWMS instance
 */
const createSourceTileWMS = (url, layers, projection, attributions, map) => {
  // Extent needs to be retrieved from the map, therefore use map projection, not layer projection
  const extent = map.getView().getProjection().getExtent()
  const origin = getTopLeft(extent)
  const resolutions = map.getView().getResolutions()

  return new TileWMS({
    url: url,
    params: {
      LAYERS: layers || '',
      FORMAT: 'image/png'
    },
    projection: projection,
    tileGrid: new TileGrid({
      origin: origin,
      resolutions: resolutions,
      matrixIds: getMatrixIds(resolutions)
    }),
    attributions: attributions
  })
}

/**
 * Creates a layer from tiled image source.
 * @param {string}  title   URL of map service to query
 * @param {string}  name    The name of the layer serves as an identifier in OpenLayers
 * @param {object}  source  ol/source/TileWMS instance
 * @return {object} ol/layer/Tile instance
 * @see https://openlayers.org/en/latest/apidoc/module-ol_layer_Tile-TileLayer.html
 */
const createTileLayer = (title, name, source, opacity) => {
  return new TileLayer({
    title: title,
    name: name,
    preload: 10,
    opacity: opacity / 100,
    type: 'base',
    visible: true,
    source: source
  })
}

/**
 * Return numeric array that matches resolutions
 * @param resolutions
 * @return {number[]}
 */
const getMatrixIds = (resolutions) => {
  return Array.apply(null, new Array(resolutions.length)).map(function (_, i) {
    return i
  })
}
</script>
