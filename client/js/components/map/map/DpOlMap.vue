<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
    <!--

        # DpOlMap - A Component for displaying OpenLayers maps in demosplan.

        This will be the default map component for working with openLayers in demosplan.
        Some of the logic specific to demosplan and how maps are handled there is moved into here,
        so it is not a really generic openLayers wrapper written in Vue.js but an opinionated one
        that tries to abstract away as much project specific stuff as possible while not ending
        as a new toggleAnything.js with trying to solve every case ever.

        ## Extent vs. Boundingbox

        In OL2, the "boundingbox" option for Ol.Map contained 4 coordinates [x1, x2, y1, y2] that effectively marked
        an area which the user could not pan/zoom out of (which is a very intuitive understanding of "limiting the
        borders of a map"). In OL3+ this concept was deprecated and we were left with the `extent` option,
        which limits the center of the map to be contained in the area described by the coordinates. However when
        zooming out, the center would be still contained within the boundingbox but parts of the map that are not
        expected to be visible are revealed. The OpenLayers 6 release restored the OL2 behavior again on View.extent
        (https://openlayers.org/en/latest/apidoc/module-ol_View-View.html), comparable with leaflet map.maxBounds
        (https://leafletjs.com/reference-1.7.1.html#map-maxbounds).

        ## Resolutions vs. Scales

        "resolutions" is an array of possible values for "size of 1 pixel
        in map units", while scales maps to our common understanding of map scales
        like "1:250.000", but it is specified without the leading "1:". Both values
        can be transformed one into the other by taking into account the density
        of the unit of the current projection (dpi is set to a constant specified by OGC).

        Available scales are defined in parameters[_default].yml via `map_global_available_scales` and
        `map_public_available_scales`. Aside from that, scales may be defined in procedure settings.

        ## Questions

        - Should proceduresettings.settings.boundingBox be saved in geoJson format?

        ## Roadmap

        - Make Progress.js a child component of DpOlMap
        - Enable the default `View.extent` behavior by removing `constrainOnlyCenter: true`.
          This requires some investigation why scrolling in the public_detail map breaks when
          removing `constrainOnlyCenter: true` there (see "Extent vs. Boundingbox" above).

     -->
</documentation>

<template>
  <div>
    <div
      id="DpOlMap"
      :class="[small ? prefixClass('c-ol-map--small') : '', prefixClass('c-ol-map')]">
      <!-- Components that depend on OpenLayers instance are mounted after map is initialized -->
      <div v-if="Boolean(map)">
        <!-- Controls -->
        <div :class="prefixClass('c-ol-map__controls flow-root')">
          <slot
            :map="map"
            name="controls" />
          <div :class="prefixClass('float-right')">
            <dp-autocomplete
              :class="prefixClass('u-mb inline-block w-11 bg-color--white')"
              v-if="_options.autoSuggest.enabled"
              :options="autoCompleteOptions"
              :route-generator="(searchString) => {
                return Routing.generate(_options.autoSuggest.serviceUrlPath, {
                  filterByExtent: JSON.stringify(maxExtent),
                  query: searchString
                })
              }"
              label="value"
              :placeholder="Translator.trans('autocomplete.label')"
              track-by="value"
              data-cy="autoCompleteInput"
              @search-changed="setAutoCompleteOptions"
              @selected="zoomToSuggestion" />

            <dp-ol-map-scale-select
              :class="prefixClass('u-ml-0_5 u-mb-0_5 align-top')"
              v-if="_options.scaleSelect" />
          </div>
        </div>

        <!-- Components may use the default slot which exposes the OpenLayers map instance -->
        <slot :map="map" />

        <!-- Default layer -->
        <dp-ol-map-layer
          v-if="!options.hideDefaultLayer"
          :attributions="options?.defaultAttribution"
          :layers="baselayerLayers"
          :projection="baseLayerProjection"
          :url="baselayer" />

        <!-- Layer from outside -->
        <dp-ol-map-layer
          v-for="layer in layers"
          :key="layer.name"
          :attributions="layer.attribution || ''"
          :order="layer.mapOrder + 1"
          :opacity="layer.opacity"
          :url="layer.url"
          :layers="layer.layers"
          :projection="layer.projectionValue" />
      </div>

      <!-- Map container -->
      <div
        ref="mapContainer"
        data-cy="map:mapContainer"
        :class="[(isValid === false) ? 'border--error' : '', prefixClass('c-ol-map__canvas u-1-of-1 relative')]"
        :id="mapId">
        <dp-loading
          v-if="!Boolean(map)"
          overlay />
      </div>

      <!-- These blocks make it possible to set colors in _map.scss which then are read by map script -->
      <div
        ref="mapDrawStyles"
        :class="prefixClass('hidden')">
        <span :class="prefixClass('c-map__draw-fill')">&nbsp;</span>
        <span :class="prefixClass('c-map__draw-stroke')">&nbsp;</span>
        <span :class="prefixClass('c-map__draw-image')">&nbsp;</span>
      </div>
    </div>
  </div>
</template>

<script>
import { Attribution, FullScreen, MousePosition, ScaleLine, Zoom } from 'ol/control'
import {
  checkResponse,
  deepMerge,
  dpApi,
  DpAutocomplete,
  DpLoading,
  prefixClassMixin
} from '@demos-europe/demosplan-ui'
import { addProjection } from 'ol/proj'
import { containsXY } from 'ol/extent'
import DpOlMapLayer from './DpOlMapLayer'
import DpOlMapScaleSelect from './DpOlMapScaleSelect'
import { easeOut } from 'ol/easing'
import { getResolutionsFromScales } from './utils/utils'
import { Map } from 'ol'
import MasterportalApi from '@masterportal/masterportalapi/src/maps/map'
import proj4 from 'proj4'
import Projection from 'ol/proj/Projection'
import { register } from 'ol/proj/proj4'

export default {
  name: 'DpOlMap',

  components: {
    DpAutocomplete,
    DpLoading,
    DpOlMapLayer,
    DpOlMapScaleSelect
  },

  mixins: [prefixClassMixin],

  provide () {
    return {
      olMapState: this.olMapState
    }
  },

  props: {
    isValid: {
      required: false,
      type: Boolean,
      default: true
    },

    layers: {
      required: false,
      type: Array,
      default: () => ([])
    },

    mapId: {
      required: false,
      type: String,
      default: 'map'
    },

    /*
     * If there is no procedureId, mapOptions have to be passed as prop
     * if there is a procedureId, mapOptions are not needed.
     */
    mapOptions: {
      required: false,
      type: Object,
      default: () => ({})
    },

    mapOptionsRoute: {
      required: false,
      type: String,
      default: 'dplan_api_map_options_admin'
    },

    options: {
      required: false,
      type: Object,
      default: () => ({})
    },

    procedureId: {
      required: false,
      type: String,
      default: ''
    },

    small: {
      required: false,
      type: Boolean,
      default: false
    }
  },

  data () {
    return {
      autoCompleteOptions: [],
      /*
       *  Make the map object a property of some other object to have it reactive
       *  See https://learn.adamwathan.com/advanced-vue/building-compound-components-with-provide-inject
       */
      olMapState: {
        map: null,
        drawStyles: {}
      },
      baselayer: '',
      baselayerLayers: '',
      baseLayerProjection: '',
      maxExtent: [],
      scales: []
    }
  },

  computed: {
    //  Shortcut for convenience
    map () {
      return this.olMapState.map
    },

    panToResolution () {
      // Copying of the array is necessary since when bound values are sorted strange things happen performance wise
      const resolutions = this.resolutions.slice()
      const compareToResolution = this.publicSearchAutozoom
      return resolutions.sort((a, b) => Math.abs(compareToResolution - a) - Math.abs(compareToResolution - b))[0]
    },

    /**
     * Transform function to only return results from inside current maxExtent to AutoComplete
     * @todo make it work - somehow there seem to be different projections ?:/
     *
     * @return {function(*=): *}
     */
    transformAutoCompleteResult () {
      const maxExtent = this.maxExtent

      return function (response) {
        const parsedResponse = JSON.parse(response)
        const projection = this._options.projection.code
        parsedResponse.data.suggestions = parsedResponse.data.suggestions.filter(suggestion => {
          const coordinate = suggestion.data[projection]
          return containsXY(maxExtent, coordinate.x, coordinate.y)
        })
        return parsedResponse.data
      }
    }
  },

  methods: {
    createMap () {
      const namedProjections = [
        [
          this._options.projection.code,
          this._options.projection.transform
        ]
      ]

      // Put resolutions in correct format for masterportalapi
      const resolutions = this.resolutions.map(resolution => ({ resolution }))

      const config = {
        epsg: this.projection.getCode(),
        extent: this.maxExtent,
        layerConf: [], // We need to pass an empty config here, so the api doesn't use its default
        namedProjections,
        options: resolutions,
        startCenter: [this.centerX, this.centerY],
        target: this.mapId,
        units: 'm'
      }

      const controls = this.options.controls ? this.options.controls : this._options.controls

      return MasterportalApi.createMap(config, '2D', { mapParams: { controls } })
    },

    /**
     * Define extent for map
     * @param mapOptions
     * @return {Array}
     */
    defineExtent (mapOptions) {
      let extent = mapOptions.defaultMapExtent

      if (this._options.procedureExtent) {
        if (mapOptions.procedureMaxExtent && mapOptions.procedureMaxExtent.length > 0) {
          extent = mapOptions.procedureMaxExtent
        } else if (mapOptions.procedureDefaultMaxExtent && mapOptions.procedureDefaultMaxExtent.length > 0) {
          extent = mapOptions.procedureDefaultMaxExtent
        }
      }

      return extent
    },

    /**
     * Get color value of specified DOM element
     * @param selector
     * @return {string | null}
     */
    getColorByClassName (selector) {
      const element = this.$refs.mapDrawStyles.querySelector(this.prefixClass(selector))
      return window.getComputedStyle(element).color
    },

    /**
     * Get colors of DOM elements that serve as a bridge between scss + javascript
     * @return {{fillColor: *|string, strokeColor: *|string, imageColor: *|string}}
     */
    getDrawStyles () {
      return {
        fillColor: this.getColorByClassName('.c-map__draw-fill'),
        strokeColor: this.getColorByClassName('.c-map__draw-stroke'),
        imageColor: this.getColorByClassName('.c-map__draw-image')
      }
    },

    getMapOptions () {
      if (this.procedureId === '') {
        return this.mapOptions
      }
      return dpApi({
        method: 'GET',
        url: Routing.generate(this.mapOptionsRoute, { procedureId: this.procedureId })
      })
        .then(checkResponse)
        .then(response => response.data.attributes)
        .catch(error => checkResponse(error.response))
    },

    panToCoordinate (coordinate) {
      this.map.getView().animate({
        center: coordinate,
        duration: 800,
        easing: easeOut,
        resolution: this.panToResolution
      })
    },

    registerFullscreenChangeHandler () {
      const html = document.getElementsByTagName('html')[0]
      const events = ['webkitfullscreenchange', 'mozfullscreenchange', 'fullscreenchange', 'MSFullscreenChange']
      events.forEach(event => {
        document.addEventListener(event, () => {
          //  Toggle class `fullscreen-mode` on html element to change canvas size dynamically via CSS
          html.classList.toggle(this.prefixClass('fullscreen-mode'))
        }, false)
      })
    },

    setAutoCompleteOptions (response) {
      this.autoCompleteOptions = response.data.data.suggestions
    },

    setProjection () {
      /*
       *  Add custom projection to OpenLayers instance
       *  @see https://github.com/openlayers/openlayers/blob/fd0e7782ed252e17ad2c74c68e1c3ddedc211697/changelog/upgrade-notes.md#changes-in-proj4-integration
       */
      proj4.defs(this._options.projection.code, this._options.projection.transform)
      register(proj4)

      const mapProjection = new Projection({
        code: this._options.projection.code,
        units: this._options.projection.units,
        extent: this.maxExtent
      })

      addProjection(mapProjection)

      this.projection = mapProjection
      this.projectionExtent = mapProjection.getExtent()
    },

    updateMapInstance () {
      if (this.map === 'undefined' || this.map === null) {
        return
      }

      const view = this.map.getView()
      const extent = view.getProjection().getExtent()
      const center = view.getCenter()

      this.map.updateSize()

      view.fit(extent, {
        size: this.map.getSize()
      })

      view.setCenter(center)
    },

    //  Animate map to given coordinate when user selects an item from search-location
    zoomToSuggestion (suggestion) {
      const coordinate = [suggestion.data[this._options.projection.code].x, suggestion.data[this._options.projection.code].y]
      this.panToCoordinate(coordinate)
    }
  },

  created () {
    /*
     *  Set _options during component creation to bypass reactivity
     *  Default options defined below
     *  User may change options via prop
     */
    this._options = deepMerge(_defaults, this.options)
  },

  async mounted () {
    //  Wait for ajax call to return stuff, then assign variables
    const mapOptions = await this.getMapOptions()

    //  These are the layers that are shown by default. Consumers of this component may add other layers.
    this.baselayerLayers = mapOptions.baseLayerLayers
    this.baselayer = mapOptions.baseLayer
    this.baseLayerProjection = mapOptions.baseLayerProjection

    if (this.mapOptions.scales) {
      this.scales = this.mapOptions.scales
    } else if (mapOptions.procedureScales.length > 0) {
      this.scales = mapOptions.procedureScales
    } else {
      this.scales = mapOptions.globalAvailableScales
    }
    //  Calculate resolutions from given scales
    this.resolutions = getResolutionsFromScales(this.scales, this._options.projection.units)

    //  Zoom to this resolution after selecting an autoSuggest value
    this.publicSearchAutozoom = mapOptions.publicSearchAutoZoom || 8

    //  Define extent & center
    this.maxExtent = this.defineExtent(mapOptions)

    this.centerX = (this.maxExtent[0] + this.maxExtent[2]) / 2
    this.centerY = (this.maxExtent[1] + this.maxExtent[3]) / 2

    // Initial view values that can be defined in options object
    if (this._options.initialExtent.length > 0) {
      this.initialExtent = this._options.initialExtent
    } else if (JSON.stringify(mapOptions.procedureInitialExtent) !== JSON.stringify(mapOptions.procedureDefaultInitialExtent) && mapOptions.procedureInitialExtent.length !== 0) {
      this.initialExtent = mapOptions.procedureInitialExtent
    } else {
      this.initialExtent = this.maxExtent
    }

    this.initCenter = this._options.initCenter

    //  Add custom projection and make it available to OpenLayers
    this.setProjection()

    /*
     *  Create OpenLayers map instance with masterportalapi and expose it to child components via `provide`.
     *  This also mounts child components that are wrapped inside v-if="Boolean(map)".
     *  @see https://css-tricks.com/using-scoped-slots-in-vue-js-to-abstract-functionality/#article-header-id-0
     */
    this.olMapState.map = this.createMap()
    /*
     *  Layers have their own attributions, so copyright is not rendered into svg here atm.
     *  this.olMapState.map.on('postrender', e => renderCopyright(e.context, 'test'));
     */

    //  After child components have added their stuff to the map instance, it needs to update accordingly

    this.$nextTick(() => {
      this.updateMapInstance()

      // If startkartenausschnitt is defined by user, show it on mounted
      if (this._options.initialExtent.length > 0 && JSON.stringify(this.maxExtent) !== JSON.stringify(this.initialExtent)) {
        this.map.getView().fit(this.initialExtent, { size: this.map.getSize() })
        // If it is not defined, but procedure has coordinates, zoom the map to the coordinates
      } else if (this.initCenter) {
        this.panToCoordinate(this.initCenter)
      }
    })

    //  Get draw styles to be used by child components that need draw styles
    this.olMapState.drawStyles = this.getDrawStyles()

    this.registerFullscreenChangeHandler()
  },

  beforeUnmount () {
    if (this.map instanceof Map) {
      /*
       *  Reset stuff - But. Is this really needed? Since every instance of DpOlMap has its own ol.Map instance.
       *  @see https://github.com/ghettovoice/vuelayers/blob/a625a1f85380efaa2b1f785cd0ea8415c19990b9/src/component/map/map.vue#L315
       */
      this.map.getLayers().clear()
      this.map.getOverlays().clear()
      this.map.getInteractions().clear()
    }
  }
}

// DO NOT pass this as Prop if you are not exactly know what you are doing!
const _defaults = {
  hideDefaultLayer: false,
  procedureExtent: false,
  initialExtent: false,
  projection: {
    code: window.dplan.defaultProjectionLabel,
    transform: window.dplan.defaultProjectionString,
    units: 'm'
  },
  controls: [
    new Attribution({ collapsible: false }),
    new FullScreen({ source: 'DpOlMap' }),
    /*
     *  Custom coordinateFormat() limits the displayed coordinates to 10 digits behind the decimal dot.
     *  This way the displayed coordinates do not "jump" when changing decimal places.
     */
    new MousePosition({
      coordinateFormat: (coordinate) => {
        const x = coordinate[0]
        const y = coordinate[1]
        return `${x.toFixed(10)} ${y.toFixed(10)}`
      }
    }),
    new ScaleLine(),
    new Zoom()
  ],
  autoSuggest: {
    enabled: true,
    serviceUrlPath: 'DemosPlan_procedure_public_suggest_procedure_location_json' // Path to openGeoDb action
  },
  scaleSelect: true,
  initView: false,
  initCenter: false
}
</script>
