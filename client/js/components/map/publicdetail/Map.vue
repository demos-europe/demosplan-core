<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div aria-hidden="true">
    <dp-autocomplete
      :class="prefixClass('c-map__autocomplete')"
      v-if="hasPermission('feature_map_search_location')"
      :options="autocompleteOptions"
      :value="selectedValue"
      :route-generator="(searchString) => {
        return Routing.generate('DemosPlan_procedure_public_suggest_procedure_location_json', {
          filterByExtent: JSON.stringify(maxExtent),
          maxResults: 9999,
          query: searchString
        })
      }"
      label="value"
      track-by="value"
      @search-changed="(response) => sortResults(response.data.data || [])"
      @selected="zoomToSuggestion"
      @searched="selectFirstOption" />
    <slot />
  </div>
</template>

<script>
import * as olHas from 'ol/has'
import { addProjection, Projection, transform } from 'ol/proj'
import { Attribution, FullScreen, MousePosition, OverviewMap, ScaleLine } from 'ol/control'
import { Circle, Fill, Stroke, Style } from 'ol/style'
import { defaults as defaultInteractions, DragZoom, Draw } from 'ol/interaction'
import { dpApi, DpAutocomplete, formatDate, hasOwnProp, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { Circle as GCircle, LineString as GLineString, Polygon as GPolygon } from 'ol/geom'
import { GeoJSON, WMTSCapabilities } from 'ol/format'
import { getArea, getLength } from 'ol/sphere'
import { Map, View } from 'ol'
import { TileWMS, WMTS } from 'ol/source'
import { easeOut } from 'ol/easing'
import Feature from 'ol/Feature'
import { getResolutionsFromScales } from '@DpJs/components/map/map/utils/utils'
import { getTopLeft } from 'ol/extent'
import LayerGroup from 'ol/layer/Group'
import { optionsFromCapabilities } from 'ol/source/WMTS'
import Overlay from 'ol/Overlay'
import Progress from './lib/Progress'
import proj4 from 'proj4'
import { register } from 'ol/proj/proj4'
import TileGrid from 'ol/tilegrid/TileGrid'
import TileLayer from 'ol/layer/Tile'
import { unByKey } from 'ol/Observable'
import VectorLayer from 'ol/layer/Vector'
import VectorSource from 'ol/source/Vector'

export default {
  name: 'DpMap',

  components: {
    DpAutocomplete
  },

  mixins: [prefixClassMixin],

  props: {
    availableProjections: {
      type: Array,
      required: true
    },

    draftStatement: {
      type: [Object, String],
      default: () => ({})
    },

    //  global twig var set in robobsh/app/config/config.yml
    getFeatureInfoUrlPlanningArea: {
      type: String,
      default: ''
    },

    mapDanmarkLayer: {
      type: String,
      default: ''
    },

    procedureDefaultInitialExtent: {
      required: true,
      type: Array
    },

    procedureDefaultMaxExtent: {
      required: true,
      type: Array
    },

    procedureId: {
      type: String,
      default: ''
    },

    procedureInitialExtent: {
      required: true,
      type: Array
    },

    procedureMaxExtent: {
      required: true,
      type: Array
    },

    procedureSettings: {
      type: Object,
      required: true
    },

    projectMapSettings: {
      type: Object,
      required: true
    }
  },

  data () {
    return {
      activeclickcontrol: null,
      autocompleteOptions: [],
      bPlan: {},
      hasTerritoryWMS: false,
      dragZoomAlways: new DragZoom({ condition: () => true }),
      map: null,
      mapSingleClickListener: null,
      measureSource: new VectorSource({ projection: this.mapprojection }),
      measureTools: [
        {
          button: '#measureLineButton',
          active: 'measureline',
          interaction: 'LineString',
          measuretype: 'length'
        },
        {
          button: '#measurePolygonButton',
          active: 'measurepolygon',
          interaction: 'Polygon',
          measuretype: 'area'
        },
        {
          button: '#measureRadiusButton',
          active: 'measureradius',
          interaction: 'Circle',
          measuretype: 'radius'
        }
      ],
      measureTooltip: null,
      measureTooltipCoord: null,
      measureTooltipElement: null,
      measureTooltipsArray: [],
      opacities: {},
      projectionName: window.dplan.defaultProjectionLabel,
      projectionString: window.dplan.defaultProjectionString,
      projectionUnits: 'm',
      scope: {},
      statementActionFields: {},
      selectedValue: ''
    }
  },

  computed: {
    featureInfoUrl () {
      /**
       * ProcedureSettings.featureInfoUrl holds GetFeatureInfo service method getUrl() return value,
       * see DemosPlanCoreBundle/Controller/Procedure/DemosPlanProcedureController.php:1309
       * see DemosPlanMapBundle/Services/GetFeatureInfo/GetFeatureInfo.php:75 for logic
       */
      return this.procedureSettings.featureInfoUrl
    },

    initialExtent () { // This is the Startkartenausschnitt, stored in prop procedureInitialExtent
      if (this.procedureInitialExtent.length === 0 && this.procedureDefaultInitialExtent.length === 0) {
        return JSON.parse(this.projectMapSettings.publicExtent)
      }

      let initialExtent
      if (this.procedureInitialExtent.length !== 0 &&
        JSON.stringify(this.procedureInitialExtent) !== JSON.stringify(this.procedureDefaultInitialExtent)) {
        initialExtent = this.procedureInitialExtent
      } else {
        initialExtent = this.maxExtent
      }
      return typeof initialExtent === 'string' ? JSON.parse(initialExtent) : initialExtent
    },

    layers () {
      return this.$store.getters['Layers/gisLayerList']()
    },

    mapx () {
      return (this.initialExtent[0] + this.initialExtent[2]) / 2
    },

    mapy () {
      return (this.initialExtent[1] + this.initialExtent[3]) / 2
    },

    maxExtent () { // This is the Kartenbegrenzung, stored in prop procedureMaxExtent
      if (this.procedureMaxExtent.length === 0 && this.procedureDefaultMaxExtent.length === 0) {
        return JSON.parse(this.projectMapSettings.publicExtent)
      }

      let maxExtent
      if (this.procedureMaxExtent.length !== 0 && JSON.stringify(this.procedureMaxExtent) !== JSON.stringify(this.procedureDefaultMaxExtent)) {
        maxExtent = this.procedureMaxExtent
      } else {
        maxExtent = this.procedureDefaultMaxExtent
      }
      return typeof maxExtent === 'string' ? JSON.parse(maxExtent) : maxExtent
    },

    /**
     * Returns the layer that is specified as such via admin settings.
     * @return {boolean|*}
     */
    overviewMapLayer () {
      const baseLayers = this.getLayersOfType('base')
      const miniMapLayer = baseLayers.find(layer => layer.attributes.isMinimap === true)

      return typeof miniMapLayer !== 'undefined' ? [miniMapLayer] : baseLayers
    },

    /**
     * Return either the specified overviewMapLayer or all baseLayers as TileLayer
     * for usage in overviewMap.
     */
    overviewMapTileLayers () {
      const layers = this.overviewMapLayer
      const layersLength = layers.length

      return layers.map(layer => {
        const name = layer.id.replaceAll('-', '')
        const visiblility = layersLength > 1 ? layer.attributes.hasDefaultVisibility : true
        const source = visiblility ? this.createLayerSource(layer) : null

        return new TileLayer({
          name,
          title: layer.attributes.name || '',
          visible: visiblility,
          source,
          opacity: 1,
          preload: 0
        })
      })
    },

    /**
     * Compare the resolution found in map_public_search_autozoom to available resolutions
     * and return closest resolution to pan to onSelect of AutoComplete
     */
    panToResolution () {
      // Copying of the array is necessary since when bound values are sorted strange things happen performance wise
      const resolutions = this.resolutions.slice()
      const compareToResolution = JSON.parse(this.projectMapSettings.publicSearchAutozoom)
      return resolutions.sort((a, b) => Math.abs(compareToResolution - a) - Math.abs(compareToResolution - b))[0]
    },

    resolutions () {
      let procedureScales = this.procedureSettings.scales

      //  Fallback to publicAvailableScales
      if (procedureScales.length === 0 || procedureScales[0] === '') {
        procedureScales = JSON.parse(this.projectMapSettings.publicAvailableScales)
      }

      return getResolutionsFromScales(procedureScales, this.projectionUnits)
    }
  },

  methods: {
    addCustomLayerToggleButton ({ id, layerName, activated }) {
      const element = document.getElementById(id)
      //  Add click handler if button is present in DOM
      if (element) {
        //  Set button state to active
        if (activated) {
          element.classList.add(this.prefixClass('is-active'))
        }

        element.addEventListener('click', () => {
          this.toggleCustomLayerButton({ element, layerName })
        }, false)
      }
    },

    /**
     * Adds a custom zoom control element with '+', '-' and 'reset to initial' controls.
     * @improve T15717
     */
    addCustomZoomControls () {
      const duration = 250
      const mapCustomZoomIn = document.getElementById('mapCustomZoomIn')
      const mapCustomZoomReset = document.querySelectorAll(this.prefixClass('.zoom-reset'))
      const mapCustomZoomOut = document.getElementById('mapCustomZoomOut')

      mapCustomZoomIn.addEventListener('click', () => {
        this.handleZoom(1, duration)
      })

      for (let i = 0; i < mapCustomZoomReset.length; i++) {
        mapCustomZoomReset[i].addEventListener('click', () => {
          this.map.getView().fit(this.initialExtent, { duration })
        })
      }

      if (typeof mapCustomZoomOut !== 'undefined') {
        mapCustomZoomOut.addEventListener('click', () => {
          this.handleZoom(-1, duration)
        })
      }
    },

    addGetCapabilityParamToUrl (url) {
      const param = 'REQUEST=GetCapabilities'
      if (url.toLowerCase().includes(param.toLowerCase())) {
        return url
      }

      return url.includes('?') ? `${url}&${param}` : `${url}?${param}`
    },

    addLayersToMap () {
      this.map.addLayer(this.baseLayerGroup)
      this.map.addLayer(this.overlayLayerGroup)
      this.map.addOverlay(this.popupoverlay)
    },

    addMeasureTools () {
      const measureLayer = new VectorLayer({
        name: 'measureLayer',
        source: this.measureSource,
        style: this.drawStyle()
      })

      this.map.addLayer(measureLayer)
      //  Attach measure interaction to elements
      this.measureTools.forEach(measureTool => {
        const measure = this.drawInteraction(this.measureSource, measureTool.interaction)
        let doubleClickListener
        let sketch
        let listener

        $(measureTool.button).on('click', el => {
          this.handleButtonInteraction(measureTool.active, measureTool.button, () => {
            this.map.addInteraction(measure)
          })
        })

        measure.on('drawstart', evt => {
          sketch = evt.feature
          this.measureTooltipCoord = evt.coordinate

          listener = sketch.getGeometry().on('change', evt => {
            this.setMeasureTooltip(evt)
          })

          this.createMeasureTooltip()

          if (measureTool.button === '#measureRadiusButton') {
            doubleClickListener = this.map.on('dblclick', event => {
              event.preventDefault()
              measure.finishDrawing()
            })
          }
        })

        // On circle drawend add feature with radius line
        measure.on('drawend', evt => {
          if (measureTool.button === '#measureRadiusButton' && evt.feature.getGeometry().getType() === 'Circle') {
            const center = evt.feature.getGeometry().getCenter()
            const lastPoint = evt.feature.getGeometry().getLastCoordinate()
            const radiusGeometry = new GLineString([center, lastPoint])
            const radiusFeature = new Feature({ geometry: radiusGeometry })
            this.measureSource.addFeature(radiusFeature)
            unByKey(doubleClickListener)
            unByKey(listener)
          }
        })
      })
    },

    addTerritoryLayer () {
      //  If there is no territory wms layer defined but a "hand-drawn" territory, craft a vector layer from it
      if (!this.hasTerritoryWMS && this.hasTerritory()) {
        //  Read GeoJson features
        const features = new GeoJSON().readFeatures(this.procedureSettings.territory)

        const territoryLayer = new VectorLayer({
          name: 'territory',
          source: new VectorSource({
            projection: this.mapprojection,
            features
          }),
          style: new Style({
            stroke: new Stroke({
              color: '#000000',
              width: 3,
              lineDash: [4, 4]
            })
          })
        })
        territoryLayer.id = 'territoryLayer'
        this.map.addLayer(territoryLayer)

        this.scope = territoryLayer

        //  Add behavior to button inside map.
        this.addCustomLayerToggleButton({
          id: 'territorySwitcher',
          layerName: 'territory',
          activated: true
        })
      }
    },

    addXMLPartToString (xml, needle, string) {
      const stringToAdd = this.getXMLPart(xml, needle)

      if (stringToAdd.length > 0) {
        // Yes, it results in crappy markup <ul><h4>... but there may be more than one h4 within the list...
        string = string + '<ul>' + stringToAdd + '</ul>'
      }
      return string
    },

    baseLayerVisibility (baseLayer) {
      //  If no baseLayer has defaultVisibility, show first toggleable baselayer
      if (baseLayer.every(el => el.getProperties().visible === false)) {
        const firstToggleableLayer = baseLayer.find(layer => {
          return layer.getProperties().isEnabled === true && layer.getProperties().title !== 'customBaselayerDanmark'
        })

        if (typeof firstToggleableLayer !== 'undefined') {
          firstToggleableLayer.setVisible(true)
        } else {
          console.warn('There is no published baseLayer, please go to the admin area and set a baseLayer as "published"')
        }
      }
    },

    bindLoadingEvents (source) {
      /*
       *  Only bind to ol source instances
       *  This way sources created from failing ajax requests won't kill the script
       */
      if (!(source instanceof TileWMS || source instanceof WMTS)) {
        return
      }
      source.on('tileloadstart', () => {
        this.progress.addLoading()
      })
      source.on('tileloadend', () => {
        this.progress.addLoaded()
      })
      source.on('tileloaderror', () => {
        this.progress.addLoaded()
      })
    },

    createBaseLayers () {
      const layers = this.getLayersOfType('base')
      const l = layers.length
      let i = 0
      let visible = true
      let layer
      let baseLayer

      if (PROJECT && PROJECT === 'robobsh') {
        // @TODO find out why url gets prefixed with current page's url
        const tmpDanmarkURL = this.mapDanmarkLayer.slice(this.mapDanmarkLayer.lastIndexOf('https'))
        const danmarkSource = new TileWMS({
          url: tmpDanmarkURL,
          params: {
            LAYERS: 'dtk_skaermkort_graa',
            FORMAT: 'image/png',
            login: 'dataport_wms_dk',
            password: 'dataport_wms_dk',
            client: 'arcGIS',
            servicename: 'topo_skaermkort',
            transparent: 'TRUE'
          },
          projection: this.mapprojection,
          tileGrid: new TileGrid({
            origin: getTopLeft(this.mapProjectionExtent),
            resolutions: this.resolutions
          })
        })

        // Add custom Baselayer for danmark
        this.baseLayers.push(new TileLayer({
          name: 'customBaselayerDanmark',
          preload: 10,
          visible: true,
          source: danmarkSource,
          doNotToggleLayer: true
        }))

        this.bindLoadingEvents(danmarkSource)
      }

      for (; i < l; i++) {
        layer = layers[i]

        if (layer.attributes.isEnabled === false && layer.attributes.isPrint === false) {
          continue
        }

        const isHiddenPrintLayer = layer.attributes.isEnabled === false && layer.attributes.isPrint
        const visibilityToSet = isHiddenPrintLayer ? false : visible

        baseLayer = this.createLayer({ layer, opacity: 1, visibility: visibilityToSet, preload: 2 })

        //  Set visibility false after first visible layer has been added
        if (layer.attributes.hasDefaultVisibility && visibilityToSet) {
          visible = false
        }

        this.baseLayers.push(baseLayer)
      }

      this.baseLayerGroup = new LayerGroup({
        layers: this.baseLayers,
        name: 'baseLayerGroup'
      })

      //  If no baseLayer has defaultVisibility, show first toggleable baseLayer
      this.baseLayerVisibility(this.baseLayers)
    },

    createMeasureTooltip () {
      this.measureTooltipElement = window.measureTooltipElement || null
      if (window.measureTooltipElement && !!this.measureTooltipElement) {
        this.measureTooltipElement.parentNode.removeChild(this.measureTooltipElement)
      }
      this.measureTooltipElement = document.createElement('div')
      this.measureTooltipElement.className = this.prefixClass('c-map__measure-output pointer-events-none')

      this.measureTooltip = new Overlay({
        element: this.measureTooltipElement,
        offset: [0, -15],
        positioning: 'bottom-center'
      })
      this.map.addOverlay(this.measureTooltip)
      this.measureTooltipElement.parentNode.classList.add(this.prefixClass('pointer-events-none'))

      this.measureTooltipsArray.push(this.measureTooltip)
    },

    /**
     *
     * @param payload Object{layer, opacity, visibility, preload}
     * @return {*}
     */
    createLayer ({ layer, opacity = 1, visibility = true, preload = 0 }) {
      const name = layer.id.replaceAll('-', '')
      const visible = layer.attributes.hasDefaultVisibility && visibility
      const source = visibility ? this.createLayerSource(layer) : null

      return new TileLayer({
        name,
        title: layer.attributes.name || '',
        visible,
        source,
        serviceType: layer.attributes.serviceType,
        opacity,
        preload,
        isPrint: layer.attributes.isPrint,
        isEnabled: layer.attributes.isEnabled,
        treeOrder: layer.attributes.treeOrder,
        mapOrder: layer.attributes.mapOrder,
        isBaseLayer: layer.attributes.isBaseLayer,
        projection: layer.attributes.projectionLabel
      })
    },

    createLayerSource (layer) {
      const source = (layer.attributes.serviceType === 'wmts')
        ? this.getWMTSSource(layer)
        : this.getWMSSource(layer)

      this.bindLoadingEvents(source)

      return source
    },

    createOverlayLayers () {
      this.hasTerritoryWMS = false

      const layersData = this.getLayersOfType('overlay')
      const l = layersData.length
      const opacities = this.opacities
      let hasBplan = false
      let hasScope = false
      let i = 0
      const tempLayers = []
      let layer
      let layerId
      let overlayLayer
      let opacity

      for (; i < l; i++) {
        layer = layersData[i]

        if (layer.attributes.isEnabled === false && layer.attributes.isPrint === false) {
          continue
        }

        const isHiddenPrintLayer = layer.attributes.isEnabled === false && layer.attributes.isPrint

        layerId = layer.id.replace(/-/g, '')
        opacity = opacities['GisLayerlayer' + layerId] / 100

        overlayLayer = this.createLayer({ layer, opacity, visibility: !isHiddenPrintLayer })

        //  For layers of type "Planzeichnung" and "Geltungsbereich", add a shiny big toggle button in the map
        if (layer.attributes.isBplan && hasBplan === false) {
          this.addCustomLayerToggleButton({
            id: 'bplanSwitcher',
            layerName: layerId,
            activated: layer.attributes.hasDefaultVisibility
          })
          hasBplan = true
          this.bPlan = layer
        } else if (layer.attributes.isScope && hasScope === false) {
          /*
           *  HasTerritoryWMS only holds the info that a territory based on a layer has been added. This
           *  is later queried to prevent also adding a "hand-drawn" territory if also a layer is defined.
           */
          this.hasTerritoryWMS = true
          this.addCustomLayerToggleButton({
            id: 'territorySwitcher',
            layerName: layerId,
            activated: layer.attributes.hasDefaultVisibility
          })
          hasScope = true
          this.scope = layer
        }

        this.overlayLayers.push(overlayLayer)

        tempLayers.push({
          id: layer.id,
          treeOrder: layer.attributes.treeOrder,
          mapOrder: layer.attributes.mapOrder,
          defaultVisibility: layer.attributes.hasDefaultVisibility
        })
      }
      tempLayers.sort((a, b) => a.treeOrder - b.treeOrder)

      this.overlayLayerGroup = new LayerGroup({
        layers: this.overlayLayers.reverse(),
        name: 'overlayLayerGroup'
      })
    },

    createPopup () {
      // Popup for getFeatureInfo interaction
      const popupContainer = document.getElementById('popup')
      const popupCloser = document.getElementById('popupCloser')

      popupCloser.onclick = () => {
        this.popupoverlay.setPosition(undefined)
        !popupCloser.blur || popupCloser.blur()
        return false
      }

      if (hasPermission('feature_map_new_statement') && document.getElementById('popupAction') !== null) {
        const popupAction = document.getElementById('popupAction')
        popupAction.onclick = event => this.doStatementAction(event)
      }

      this.popupoverlay = new Overlay(({
        element: popupContainer,
        title: 'popup',
        autoPan: true,
        autoPanAnimation: { duration: 250 }
      }))
    },

    /**
     * Shows legends when their respective layers are shown
     *
     * Only used in special cases
     * @improve T15718
     */
    displayLegends (recursive) {
      //  Grab dom reference
      const mapLegends = $(this.prefixClass('.js__mapLayerLegends'))

      //  Initially hide all legends
      mapLegends.find('[data-layername]').addClass(this.prefixClass('sr-only'))

      //  Loop layers
      this.map.getLayers().forEach((layer, idx, a) => {
        //  Loop layer groups
        if (layer instanceof LayerGroup) {
          layer.getLayers().forEach((sublayer, jdx, b) => {
            if (recursive) {
              //  Attach behavior
              sublayer.on('propertychange', (evt) => {
                this.displayLegends(false)
              })
            }

            //  Show appropriate legend if layer is visible
            if (sublayer.getVisible()) {
              mapLegends.find('[data-layername="' + sublayer.get('title') + '"]').removeClass(this.prefixClass('sr-only'))
            }
          })
        }
      })
    },

    doAllTheOtherExcitingStuff () {
      this.map.getView().fit(this.initialExtent, this.map.getSize()) // Zoom to Startkartenausschnitt from backend

      this.map.getLayerGroup().set('name', 'Root')

      this.addTerritoryLayer()

      /*
       * #########################################################
       * define overview map and overview map control to show and hide overview map
       */
      let overviewMapControlView

      if (PROJECT && PROJECT === 'robobsh') {
        overviewMapControlView = new View({
          center: [this.mapx, this.mapy],
          projection: this.mapprojection,
          resolution: 1000,
          minResolution: 1000,
          maxResolution: 1000
        })
      } else {
        overviewMapControlView = new View({
          center: [this.mapx, this.mapy],
          projection: this.mapprojection,
          resolutions: this.resolutions
        })
      }

      const overviewMapControl = new OverviewMap({
        className: 'ol-overviewmap ' + this.prefixClass('c-map__minimap'),
        collapseLabel: '\u00BB',
        // AutoPan: true,
        layers: this.overviewMapTileLayers,
        label: '\u00AB',
        tipLabel: 'Übersichtskarte',
        collapsed: false,
        view: overviewMapControlView
      })

      this.map.addControl(overviewMapControl)

      /*
       * #########################################################
       * updates the view's width and height value. Important for the GetfeatureInfo-Click
       */
      this.map.updateSize()

      /*
       * ##########################################################
       * Abfrage der Kriterien per getfeatureInfo
       */

      //  define tile to set query on
      let getFeatureinfoSource

      if (PROJECT && PROJECT === 'robobsh') {
        getFeatureinfoSource = new TileLayer({
          title: 'Entscheidungskriterien',
          opacity: 1,
          source: new TileWMS({
            url: this.featureInfoUrl,
            projection: this.mapprojection,
            tileGrid: new TileGrid({
              origin: getTopLeft(this.mapProjectionExtent),
              resolutions: this.resolutions,
              tileSize: [1, 1]
            })
          })
        })
      } else {
        getFeatureinfoSource = new TileLayer({
          title: 'GetFeatureInfo',
          source: new TileWMS({
            url: this.featureInfoUrl
          })
        })
      }

      //  Execute query
      const queryCriteria = evt => {
        const $popup = $(this.popupoverlay.getElement())
        const coordinate = evt.coordinate
        const viewResolution = this.mapview.getResolution()

        // Use prerendered html by default
        let infoFormat = 'text/html'
        if (PROJECT && PROJECT === 'robobsh') {
          infoFormat = 'text/xml'
        }

        const remappedUrl = getFeatureinfoSource.getSource().getFeatureInfoUrl(
          coordinate, viewResolution, this.mapprojection, { INFO_FORMAT: infoFormat }
        ).split('?')[1]

        if (remappedUrl) {
          const getData = { params: remappedUrl }

          //  This triggers getFeatureInfoByType() in GetFeatureInfo service
          if (PROJECT && PROJECT === 'robobsh') {
            getData.infotype = 'criteria'
          }

          //  Open Popup with loading state
          this.resetPopup()
          $popup.addClass(this.prefixClass('c-map__popup--scrollable c-map__popup--large c-map__popup--hide-action'))
          this.showPopup('criteriaPopup', '', coordinate)
          //  Add progress indicator (.o-spinner on same element required)
          $popup.find('#popupContent h3').addClass(this.prefixClass('is-progress'))

          dpApi.get(Routing.generate('DemosPlan_map_get_feature_info', { procedure: this.procedureId }), getData)
            .then(response => {
              const parsedData = JSON.parse(response.data)
              if (parsedData.code === 100 && parsedData.success) {
                if (parsedData.body !== null) {
                  let popupContent = ''

                  //  In Robob, do not show full response body
                  if (PROJECT && PROJECT === 'robobsh') {
                    let i = 0
                    let xmlNeedle
                    const xmlResponse = $.parseXML(response.data.body)
                    const xmlNeedles = ['abw_klarte', 'ht_klartex', 'wt_klartex']

                    //  Filter relevant content from xml response
                    for (; i < xmlNeedles.length; i++) {
                      xmlNeedle = xmlNeedles[i]
                      popupContent = this.addXMLPartToString(xmlResponse, xmlNeedle, popupContent)
                    }
                  } else {
                    popupContent = parsedData.body
                  }

                  if (popupContent.length === 0 || popupContent.match(/<table[^>]*?>[\s\t\n\r↵]*<\/table>/mg) !== null) {
                    popupContent = Translator.trans('map.getfeatureinfo.none')
                  }

                  this.showPopup('criteriaPopup', popupContent, coordinate)
                } else {
                  this.showPopupError('empty', coordinate)
                }
              } else {
                this.showPopupError('failed', coordinate)
              }
            })
        }
      }

      //  Add 'queryCriteria' button behavior
      if (this.featureInfoUrl.length > 1) {
        $('#criteriaButton').on('pointerup keydown', (event) => {
          // For keyboard events, do only execute when enter was pressed
          if (event.type === 'keydown' && event.keyCode !== 13) {
            return
          }
          this.handleButtonInteraction('criteria', '#criteriaButton', () => {
            this.mapSingleClickListener = this.map.on('singleclick', queryCriteria)
          })
        })
      }

      let queryArea
      //  Add 'queryArea' behavior
      if (PROJECT && PROJECT === 'robobsh' && dplan.procedureStatementPriorityArea) {
        /*
         * Real url is added in backend, this one is only needed to be able to
         * parse a getFeatureInfoUrl later
         */
        const vorrangGebiete = new TileLayer({
          title: 'Vorranggebiete',
          opacity: 1,
          type: 'overlay',
          source: new TileWMS({
            url: 'http://temporary.de'
          })
        })

        const getFeatureinfoSourcePlanungsraum = new TileLayer({
          title: 'PR',
          opacity: 1,
          source: new TileWMS({
            url: this.getFeatureInfoUrlPlanningArea,
            projection: this.mapprojection,
            params: { LAYERS: 'planungsraeume', QUERY_LAYERS: 'planungsraeume' },
            tileGrid: new TileGrid({
              origin: getTopLeft(this.mapProjectionExtent),
              resolutions: this.resolutions
            })
          })
        })

        queryArea = evt => {
          const viewResolution = (this.mapview.getResolution())
          const coordinate = evt.coordinate

          this.resetPopup()

          $('#queryAreaButton').addClass(this.prefixClass('is-progress'))

          if (vorrangGebiete.getVisible() === true) {
            /* URL for FeatureInfo */
            const vorrangurl = vorrangGebiete.getSource().getFeatureInfoUrl(
              coordinate, viewResolution, this.mapprojection, { INFO_FORMAT: 'text/xml' }
            )
            /* URL to check if we are in the correct procedure */
            const planungsraumUrl = getFeatureinfoSourcePlanungsraum.getSource().getFeatureInfoUrl(
              coordinate, viewResolution, this.mapprojection, { INFO_FORMAT: 'text/xml' }
            )
            const remappedUrl = vorrangurl.split('?')[1] // Get only the parameter part of the generated URL.
            const remappedPrUrl = planungsraumUrl.split('?')[1] // Get only the parameter part of the generated URL.

            if (remappedUrl) {
              if (remappedPrUrl) {
                // Because of Browser-Ajax-Security, we have to pipe the getfeatureInfo-Request through our server
                dpApi.get(Routing.generate('DemosPlan_map_get_planning_area', { procedure: this.procedureId }), {
                  params: remappedPrUrl,
                  url: this.getFeatureInfoUrlPlanningArea
                })
                  .then(responsePr => {
                    /*
                     * If we can't check the procedure we want to get the featureInfos anyway and
                     * if coordinates are in the area of the current procedure, but only if planningarea is not set to 'all'
                     */
                    if (responsePr.data.code === 100 &&
                      responsePr.data.success &&
                      responsePr.data.body?.id !== this.procedureId &&
                      this.procedureSettings.planningArea !== 'all' &&
                      hasPermission('feature_map_new_statement')) {
                      const popUpContent = Translator.trans('procedure.move.to.list') +
                      '<a class="' + this.prefixClass('btn btn--primary float-right u-mt-0_5') + '" href="' + Routing.generate('core_home') + '">' +
                      Translator.trans('procedures.all.show') +
                      '</a>'

                      this.showPopup('contentPopup', {
                        title: Translator.trans('procedure.not.in.scope'),
                        text: popUpContent
                      }, coordinate)
                    } else {
                      this.showPopup('contentPopup', {
                        title: Translator.trans('error.generic'),
                        text: popUpContent
                      }, coordinate)
                    }
                  })
                  .catch(e => {
                    console.error(e)
                  })
                  .then(() => {
                    $('#queryAreaButton').removeClass(this.prefixClass('is-progress'))
                  })
              }
            }
          }
        }

        //  Add 'queryArea' button behavior
        $('#queryAreaButton').on('click touchstart', () => {
          this.handleButtonInteraction('queryarea', '#queryAreaButton', () => {
            this.mapSingleClickListener = this.map.on('singleclick', queryArea)
          })
        })

        //  Bind 'queryArea' behavior to click on map when initially loading
        this.mapSingleClickListener = this.map.on('singleclick', queryArea)
      }

      /*
       * #########################################################
       * Draw: drawing features
       */

      //  define + add layer to draw into
      const geoJSONFormat = new GeoJSON()
      const mapdrawsource = new VectorSource({
        format: geoJSONFormat,
        projection: this.mapprojection,
        features: this.draftStatement && this.draftStatement.polygon ? geoJSONFormat.readFeatures(this.draftStatement.polygon) : null
      })
      const mapdrawvector = new VectorLayer({
        source: mapdrawsource,
        name: 'drawVector',
        title: 'draw',
        type: 'draw',
        style: this.drawDoneStyle()
      })
      this.map.addLayer(mapdrawvector)

      //  Define vars to init interactions
      let drawingexists = false
      const drawTools = [
        {
          button: '#drawPointButton',
          active: 'drawpoint',
          interaction: 'Point'
        },
        {
          button: '#drawLineButton',
          active: 'drawline',
          interaction: 'LineString'
        },
        {
          button: '#drawPolygonButton',
          active: 'drawpolygon',
          interaction: 'Polygon'
        }

      ]

      //  Attach drawing interactions to elements
      drawTools.forEach(drawTool => {
        const drawing = this.drawInteraction(mapdrawsource, drawTool.interaction)

        $(drawTool.button).on('pointerup keydown', event => {
          // For keyboard events, do only execute when enter was pressed
          if (event.type === 'keydown' && event.keyCode !== 13) {
            return
          }
          this.handleButtonInteraction(drawTool.active, drawTool.button, () => {
            this.map.addInteraction(drawing)
            $('#saveStatementButton').addClass(this.prefixClass('is-visible'))
          })
        })

        mapdrawsource.on('addfeature', evt => {
          updateMapFields()
          drawingexists = true
        })
      })

      //  Add 'clear map' control
      $('#clearDrawingButton').on('pointerup keydown', event => {
        // For keyboard events, do only execute when enter was pressed
        if (event.type === 'keydown' && event.keyCode !== 13) {
          return
        }
        window.dplan.clearMapDrawings()
        const resetData = {
          r_location: 'notLocated',
          r_location_geometry: '',
          r_location_point: '',
          location_is_set: ''
        }
        this.$root.$emit('update-statement-form-map-data', resetData, false)
      })

      window.dplan.clearMapDrawings = () => {
        mapdrawsource.clear()
        drawingexists = false
        $('#clearDrawingButton').addClass(this.prefixClass('c-actionbox__tool--dimmed'))
        $('#saveStatementButton')
          .removeClass(this.prefixClass('is-active'))
          .html(window.dplan.statement.labels.saveStatementButton.states.visible.button)
          .prop(
            'title',
            window.dplan.statement.labels.saveStatementButton.states.visible.title
          )
      }

      const updateMapFields = () => {
        $('#clearDrawingButton').removeClass(this.prefixClass('c-actionbox__tool--dimmed'))

        const saveStatementButton = $('#saveStatementButton')

        // Retrieve data needed for wms/wmts screenshot in BE: tiles with their positions and urls; add it to geoJSON
        const baseLayerGroup = this.findBy(this.map.getLayerGroup(), 'name', 'baseLayerGroup') || []
        const overlayLayerGroup = this.findBy(this.map.getLayerGroup(), 'name', 'overlayLayerGroup') || []
        const flatLayers = [...baseLayerGroup.getLayers().getArray(), ...overlayLayerGroup.getLayers().getArray()]
        const allPrintLayers = flatLayers.filter(el => el.getProperties().isPrint === true)

        const allFeatures = mapdrawsource.getFeatures()
        const extent = mapdrawsource.getExtent()

        // If there is no print layer defined in procedure settings, a default print layer will be used in BE for the screenshot. The default layer is always WMS, so we don't need the info about urls and tile grid.
        if (allPrintLayers.length > 0) {
          const featureMeta = {
            featureLayerExtent: extent,
            printLayers: []
          }

          allPrintLayers.forEach(printLayer => {
            if (!printLayer.getSource()) {
              this.setLayerSource(printLayer)
            }
            const printLayerName = printLayer.getProperties().name
            const source = printLayer.getSource()

            // This covers the edge case that a layer which is set as print layer is no longer valid.
            if (source === null) {
              return
            }

            const tileUrlFunction = source.getTileUrlFunction()
            const tileGrid = source.getTileGrid()
            const tileSize = tileGrid.getTileSize()
            const zoom = tileGrid.getZForResolution(this.map.getView().getResolution())
            const layerProjection = printLayer.getProperties().projection || source.getProjection().getCode()
            const isWmts = printLayer.get('serviceType') === 'wmts'

            // Transpose extent for layer projection
            const [minX, minY, maxX, maxY] = extent
            let bottomLeft = [minX, minY]
            let topRight = [maxX, maxY]
            if (this.mapprojection.getCode() !== layerProjection) {
              bottomLeft = transform([minX, minY], this.mapprojection, layerProjection)
              topRight = transform([maxX, maxY], this.mapprojection, layerProjection)
            }
            const transposedExtent = [...bottomLeft, ...topRight]

            const tilesInfo = []

            tileGrid.forEachTileCoord(transposedExtent, zoom, tileCoord => {
              // TileCoord is an array of [z, x, y]
              const url = tileUrlFunction(tileCoord, olHas.DEVICE_PIXEL_RATIO, source.getProjection())
              tilesInfo.push({
                position: {
                  z: isWmts ? tileCoord[0] : 0,
                  x: isWmts ? tileCoord[1] : 0,
                  y: isWmts ? tileCoord[2] : 0
                },
                projection: layerProjection,
                url,
                tileSize,
                tileExtent: tileGrid.getTileCoordExtent(tileCoord)
              })
            })

            featureMeta.printLayers.push({
              layerName: printLayerName,
              layerTitle: printLayer.getProperties().title,
              layerMapOrder: printLayer.getProperties().mapOrder,
              isBaseLayer: printLayer.getProperties().isBaseLayer,
              layerProjection,
              tiles: tilesInfo
            })
          })

          featureMeta.printLayers.sort((layerA, layerB) => {
            if (layerA.isBaseLayer !== layerB.isBaseLayer) {
              return layerA.isBaseLayer ? -1 : 1
            } else {
              return layerA.layerMapOrder > layerB.layerMapOrder ? -1 : 1
            }
          })

          allFeatures.forEach((feature, idx) => {
            if (idx === 0) {
              feature.setProperties({ metadata: featureMeta })
            } else {
              feature.setProperties({})
            }
          })
        }

        //  Write to sessionStorage on drawend to prevent drawing from being lost when user clicks somewhere else
        const statementFormGeometryData = {
          r_location: 'point',
          r_location_geometry: geoJSONFormat.writeFeatures(allFeatures),
          r_location_priority_area_key: '',
          r_location_priority_area_type: '',
          r_location_point: '',
          location_is_set: 'geometry'
        }
        this.$root.$emit('update-statement-form-map-data', statementFormGeometryData, false)

        saveStatementButton
          .addClass(this.prefixClass('is-active c-actionbox__toggle-shake'))
          .html(window.dplan.statement.labels.saveStatementButton.states.active.button)
          .prop(
            'title',
            window.dplan.statement.labels.saveStatementButton.states.active.title
          )

        setTimeout(() => {
          saveStatementButton.removeClass(this.prefixClass('c-actionbox__toggle-shake'))
        }, 100)
      }

      //  Add 'save statement geometry' control
      $('#saveStatementButton').on('pointerup keydown', event => {
        // For keyboard events, do only execute when enter was pressed
        if (event.type === 'keydown' && event.keyCode !== 13) {
          return
        }
        if (drawingexists === true) {
          const statementFormGeometryData = {
            r_location: 'point',
            r_location_geometry: geoJSONFormat.writeFeatures(mapdrawsource.getFeatures()),
            r_location_priority_area_key: '',
            r_location_priority_area_type: '',
            r_location_point: '',
            location_is_set: 'geometry'
          }
          this.$root.$emit('update-statement-form-map-data', statementFormGeometryData)
        } else {
          alert('Bitte nehmen Sie zuerst eine Einzeichnung vor!')
        }
      })

      /*
       * #########################################################
       * Mark Location features
       */

      const mapMarkLocationDisplayPopup = (coordinate) => {
        $('#markLocationButton').addClass(this.prefixClass('is-progress'))

        this.statementActionFields = {
          r_location: 'point',
          r_location_point: coordinate.join(','),
          r_location_priority_area_key: '',
          r_location_priority_area_type: '',
          r_location_geometry: '',
          location_is_set: 'point'
        }
        window.statementActionState = 'locationPointAdded'
        this.showPopup('markLocationPopup', '', coordinate)
      }

      //  Handle mark location
      const mapMarkLocation = evt => {
        const coordinate = evt.coordinate
        this.resetPopup()

        // @todo remove code (almost) duplication with queryArea
        if (PROJECT && PROJECT === 'robobsh') {
          const viewResolution = this.mapview.getResolution()

          const getFeatureinfoSourcePlanningAreaMarkLocation = new TileLayer({
            title: 'PR',
            opacity: 1,
            source: new TileWMS({
              url: this.getFeatureInfoUrlPlanningArea,
              projection: this.mapprojection,
              params: { LAYERS: 'planungsraeume', QUERY_LAYERS: 'planungsraeume' },
              tileGrid: new TileGrid({
                origin: getTopLeft(this.mapProjectionExtent),
                resolutions: this.resolutions
              })
            })
          })
          // URL to check if we are in the correct procedure
          const planungsraumUrlMarkLocation = getFeatureinfoSourcePlanningAreaMarkLocation.getSource().getFeatureInfoUrl(
            coordinate, viewResolution, this.mapprojection, { INFO_FORMAT: 'text/xml' }
          )
          const remappedPrUrlMarkLocation = planungsraumUrlMarkLocation.split('?')[1] // Get only the parameter part of the generated URL.

          if (remappedPrUrlMarkLocation) {
            if (this.procedureSettings.planningArea === 'all') {
              // No need to check planningArea if it should be displayed for any area
              mapMarkLocationDisplayPopup(coordinate)
            } else {
              // Because of Browser-Ajax-Security, we have to pipe the getfeatureInfo-Request through our server
              dpApi.get(Routing.generate('DemosPlan_map_get_planning_area', { procedure: this.procedureId }), {
                params: remappedPrUrlMarkLocation,
                url: this.getFeatureInfoUrlPlanningArea
              })
                .then(responsePr => {
                  // If we can't check the procedure we want to get the featureInfos anyway
                  if (responsePr.data.code === 100 && responsePr.data.success && responsePr.data.body !== null && responsePr.data.body !== '') {
                    /* Check if coordinates are in the area of the current procedure */
                    if (responsePr.data.body.id !== this.procedureId) {
                    /*
                     * Roll back to this one when we can handle procedure versions
                     * let popUpContent = Translator.trans('procedure.move.to.participate', {name: responsePr.body.name}) +
                     *     '<a class="btn btn--primary float-right u-mt-0_5 u-mb-0" href="' + Routing.generate('DemosPlan_procedure_public_detail', {'procedure': responsePr.body.id}) + '">' +
                     *     Translator.trans('procedure.goto') +
                     *     '</a>';
                     */
                      const popUpContent = Translator.trans('procedure.move.to.list') +
                      '<a class="' + this.prefixClass('btn btn--primary float-right u-mt-0_5') + '" href="' + Routing.generate('core_home') + '">' +
                      Translator.trans('procedures.all.show') +
                      '</a>'
                      this.showPopup('contentPopup', {
                        title: Translator.trans('procedure.not.in.scope'),
                        text: popUpContent
                      }, coordinate)
                    } else {
                      mapMarkLocationDisplayPopup(coordinate)
                    }
                  }
                })
            }
          }
        } else {
          mapMarkLocationDisplayPopup(coordinate)
        }
      }

      //  Add 'mark location' control
      $('#markLocationButton, [data-maptools-id="markLocationButtonResponsive"]').on('pointerup keydown', (event) => {
        // For keyboard events, do only execute when enter was pressed
        if (event.type === 'keydown' && event.keyCode !== 13) {
          return
        }
        this.activateMarkLocationButton($(this))
        this.handleButtonInteraction('marklocation', '#markLocationButton, [data-maptools-id="markLocationButtonResponsive"]', () => {
          this.mapSingleClickListener = this.map.on('singleclick', mapMarkLocation)
        })
      })

      /*
       * #########################################################
       * Kartenwerkzeuge: measure features
       */
      this.createMeasureTooltip()

      this.addMeasureTools()

      /*
       * #########################################################
       * Kartenwerkzeuge: DragZoom control
       */

      //  Add DragZoom control
      $('#dragZoomButton').on('click', el => {
        this.handleButtonInteraction('dragzoom', '#dragZoomButton', () => {
          this.mapSingleClickListener = this.map.addInteraction(this.dragZoomAlways)
          $('#dragZoomButton').addClass(this.prefixClass('is-active'))
        })
      })

      // Remove measure drawings
      $('#measureRemoveButton').on('pointerup', el => {
        this.removeOtherInteractions(true)
      })

      // Set trigger for adding custom layers
      this.$root.$on('addCustomlayer', (layerdata) => {
        let { currentCapabilities, serviceType, url, name, layers, projection, tileMatrixSet } = layerdata
        const layerId = Math.floor((Math.random() * 10000) + 1)

        if (Array.isArray(layers) && layers.length > 0 && hasOwnProp(layers[0], 'value')) {
          layers = layers.map(layer => layer.value)
        }

        // Create source
        let source
        if (serviceType === 'wms') {
          //  Remove everything from the beginning to first match of `SERVICE` - if the term is found in string
          const indexOfService = url.indexOf('SERVICE')
          if (indexOfService > 0) {
            url = url.slice(0, indexOfService)
          }

          source = new TileWMS({
            url,
            params: {
              LAYERS: layers || '',
              FORMAT: 'image/png'
            },
            projection
          })
        } else {
          const options = optionsFromCapabilities(currentCapabilities, {
            layer: layers[0] || '',
            matrixSet: tileMatrixSet
          })
          source = new WMTS({ ...options, layers })
        }

        const customLayer = new TileLayer({
          id: 'customLayer_' + layerId,
          title: name,
          name: layerId,
          type: 'overlay',
          source
        })

        this.map.addLayer(customLayer)

        // Create notification
        dplan.notify.notify('confirm', Translator.trans('confirm.layer.custom.added'))
      })

      //  Re-initialize map, used when map initializes on hidden element
      window.redrawMap = () => this.redrawMap()
    },

    doStatementAction () {
      // Window.dplan.statement.toggleFormFromMap(event)
      this.popupoverlay.setPosition(undefined)
      this.$root.$emit('update-statement-form-map-data', this.statementActionFields)
    },

    drawFillSelector (selector) {
      return $(this.prefixClass(selector)).css('color')
    },

    //  Styles for finished drawings
    drawDoneStyle () {
      return new Style({
        fill: this.fill(),
        stroke: this.stroke(),
        image: new Circle({
          radius: 5,
          fill: new Fill({
            color: this.drawFillSelector('.c-map__draw-image')
          })
        })
      })
    },

    //  Function to generate draw interaction
    drawInteraction (source, type) {
      return new Draw({
        source,
        type,
        style: this.drawStyle()
      })
    },

    //  Styles for drawing in progress
    drawStyle () {
      return new Style({
        fill: this.fill(),
        stroke: this.stroke([10]),
        image: new Circle({
          radius: 5,
          fill: new Fill({
            color: this.drawFillSelector('.c-map__draw-fill')
          }),
          stroke: this.stroke()
        })
      })
    },

    fill () {
      return new Fill({
        color: this.drawFillSelector('.c-map__draw-fill')
      })
    },

    findBy (layer, key, value) {
      if (layer.get(key) === value) {
        return layer
      }
      if (layer.getLayers) {
        const layers = layer.getLayers().getArray()
        const len = layers.length; let result

        for (let i = 0; i < len; i++) {
          result = this.findBy(layers[i], key, value)
          if (result) {
            return result
          }
        }
      }
      return null
    },

    findLayerById (id) {
      return this.findBy(this.map.getLayerGroup(), 'name', id)
    },

    formatArea (polygon) {
      const area = getArea(polygon)
      let output

      if (area > 10000) {
        output = Math.round((area / 1000000) * 100) / 100 + ' ' + 'km<sup>2</sup>'
      } else {
        output = Math.round(area * 100) / 100 + ' ' + 'm<sup>2</sup>'
      }
      return output
    },

    formatLength (line) {
      const length = getLength(line)
      let output
      if (length > 100) {
        output = Math.round((length / 1000) * 100) / 100 + ' ' + 'km'
      } else {
        output = Math.round(length * 100) / 100 + ' ' + 'm'
      }
      return output
    },

    getLayersOfType (type) {
      const allLayers = this.layers
      const l = allLayers.length
      const layers = []
      let i = 0
      let layer

      for (; i < l; i++) {
        layer = allLayers[i]
        if (layer.attributes.layerType === type) {
          layers.push(layer)
        }
      }
      return layers
    },

    getWMTSSource (layer) {
      const layerArray = Array.isArray(layer.attributes.layers) ? layer.attributes.layers : layer.attributes.layers.split(',')
      const url = this.addGetCapabilityParamToUrl(layer.attributes.url)

      let xml

      const xhr = new XMLHttpRequest()
      xhr.open('GET', url, false)
      xhr.send(null)

      if (xhr.status === 200) {
        xml = xhr.responseXML
      } else {
        throw new Error(`Error fetching WMTS source: ${xhr.statusText}`)
      }

      const result = this.parser.read(xml)
      const options = optionsFromCapabilities(result, {
        layer: layerArray[0] || '',
        matrixSet: layer.attributes.tileMatrixSet
      })

      return new WMTS({ ...options, layers: layerArray })
    },

    getWMSSource (layer) {
      // @TODO find out why 'SERVICE=WMS&' is added twice to url
      let url = layer.attributes.url || null
      if (url) {
        //  Remove everything from the beginning to first match of `SERVICE` - if the term is found in string
        const indexOfService = url.indexOf('SERVICE')
        if (indexOfService > 0) {
          url = url.slice(0, indexOfService)
        }
      }

      // We have to create this temporaryProjection to be able to get extent that will be used as origin for WMS TileGrid
      const projectionLabel = layer.attributes.projectionLabel || window.dplan.defaultProjectionLabel
      const projection = new Projection({
        code: projectionLabel,
        units: this.projectionUnits,
        extent: transform(this.mapProjectionExtent, 'EPSG:3857', projectionLabel)
      })

      return new TileWMS({
        url,
        params: {
          LAYERS: layer.attributes.layers || '',
          FORMAT: 'image/png',
          VERSION: layer.attributes.layerVersion || '1.3.0'
        },
        projection: layer.attributes.projectionLabel || window.dplan.defaultProjectionLabel,
        tileGrid: new TileGrid({
          origin: getTopLeft(projection.getExtent()),
          resolutions: this.resolutions
        })
      })
    },

    getXMLPart (xml, needle) {
      const $xml = $(xml)

      //  This is Chrome, Opera and Safari syntax:  http://stackoverflow.com/a/20705737/6234391
      let $xmlFromNeedle = $xml.find(needle)

      //  If no valid element, we try firefox / ie syntax...
      if ($xmlFromNeedle.length === 0) {
        $xmlFromNeedle = $xml.find('app\\:' + needle)
      }

      let string = ''
      if ($xmlFromNeedle.length > 0) {
        string = $xmlFromNeedle.text()
      }
      return string
    },

    handleButtonInteraction (active, element, callback) {
      this.removeOtherInteractions()
      //  Toggle #queryAreaButton inactive
      if (active !== 'queryarea' && dplan.procedureStatementPriorityArea) {
        window.dplan.statement.activateQueryAreaButton($('#queryAreaButton'), true)
      }
      if (this.activeclickcontrol !== active) {
        callback()
        $(element).addClass(this.prefixClass('is-active'))
        this.activeclickcontrol = active
      } else {
        this.activeclickcontrol = ''

        if (PROJECT && PROJECT === 'robobsh' && dplan.procedureStatementPriorityArea) {
          this.mapSingleClickListener = this.map.on('singleclick', queryArea)
        }
      }
      this.$root.$emit('changeActive')
    },

    hasTerritory () {
      return this.procedureSettings.territory?.features?.length > 0
    },

    removeOtherInteractions (reset) {
      this.map.getInteractions().forEach(interaction => {
        if (interaction instanceof Draw) {
          this.map.removeInteraction(interaction)
        } else if (interaction instanceof DragZoom) {
          this.map.removeInteraction(this.dragZoomAlways)
          $('#dragZoomButton').removeClass(this.prefixClass('is-active'))
        }
      })

      //  Remove mapSingleClickListener event listener
      unByKey(this.mapSingleClickListener)

      //  Hide drawpoint stn button
      $('#saveStatementButton').removeClass(this.prefixClass('is-visible'))
      $(this.prefixClass('.js__mapcontrol')).removeClass(this.prefixClass('is-active'))

      //  Unselect tools
      $(this.prefixClass('.c-map__tool, .c-map__tool-simple')).removeClass(this.prefixClass('is-active'))

      this.resetPopup()

      if (reset === true) {
        // Clear source for measuring layer
        this.measureSource.clear()
        // Remove all measure tooltips
        if (this.measureTooltipsArray.length > 0) {
          this.measureTooltipsArray.forEach(tt => this.map.removeOverlay(tt))
          this.measureTooltipsArray = []
        }
        this.$root.$emit('changeActive')
      }
    },

    activateMarkLocationButton (el) {
      if (el.hasClass(this.prefixClass('is-activated'))) {
        el.removeClass(this.prefixClass('is-activated'))
        //  Also change html when element is a big actionbutton
        if (el.prop('tagName') === 'H2') {
          el.html('Ort markieren')
          el.parent().find(this.prefixClass('.c-actionbox__arrow')).remove()
        }
      } else {
        el.addClass(this.prefixClass('is-activated'))
        //  Also change html when element is a big actionbutton
        if (el.prop('tagName') === 'H2') {
          el.html('Ort markieren...')
          el.parent().append('<i class="' + this.prefixClass('fa fa-2x fa-long-arrow-right c-actionbox__arrow') + '" aria-hidden="true"></i>')
        }
      }
    },

    //  Animate map to given coordinate when user selects an item from search-location
    zoomToSuggestion (suggestion) {
      const coordinate = [suggestion.data[this.projectionName].x, suggestion.data[this.projectionName].y]
      this.panToCoordinate(coordinate)
      this.resetPopup()
      this.selectedValue = suggestion.value
    },

    handleFullscreenChange () {
      //  Toggle class `fullscreen-mode` on html element to change canvas size dynamically via CSS
      document.getElementsByTagName('html')[0].classList.toggle(this.prefixClass('fullscreen-mode'))
      //  Update map size to account for changed proportions on fullscreenChange
      this.map.updateSize()
      // On FullScreen Mode, focus for all elements in Map Container.
      const fullScreenMode = document.getElementsByClassName('fullscreen-mode')
      this.$emit('fullscreen-toggle', fullScreenMode.length > 0)
    },

    handleZoom (delta, duration) {
      const view = this.map.getView()
      if (!view) {
        return
      }
      const currentZoom = view.getZoom()
      if (currentZoom !== undefined) {
        const newZoom = view.getConstrainedZoom(currentZoom + delta)
        if (duration > 0) {
          if (view.getAnimating()) {
            view.cancelAnimations()
          }
          view.animate({
            zoom: newZoom,
            duration,
            easing: easeOut
          })
        } else {
          view.setZoom(newZoom)
        }
      }
    },

    initializeMap () {
      const controls = [
        new FullScreen({ className: this.prefixClass('c-map__fullscreen'), source: 'procedureDetailsMap' }),
        new ScaleLine({ className: this.prefixClass('c-map__scale-line') + ' ol-scale-line' })
      ]
      if (PROJECT && PROJECT !== 'robobsh') {
        controls.push(new MousePosition({ className: this.prefixClass('c-map__mouseposition') }))
      }

      // Add either the attribution from procedureSettings or the attribution defined globally via translation key
      const currentYear = formatDate(new Date(), 'YYYY')
      let label = ''

      if (hasOwnProp(this.procedureSettings, 'copyright') && this.procedureSettings.copyright !== '') {
        label = this.procedureSettings.copyright.replace('{currentYear}', currentYear)
      } else {
        label = Translator.trans('map.attribution.default', {
          linkImprint: Routing.generate('DemosPlan_misccontent_static_imprint'),
          currentYear
        })
      }

      controls.push(new Attribution({
        collapsed: false,
        collapsible: false,
        label,
        tipLabel: label
      }))

      this.map = new Map({
        controls,
        interactions: defaultInteractions().extend([
          new DragZoom()
        ]),
        target: 'dp-map',
        view: this.mapview,
        resolutions: this.resolutions
      })
    },

    panToCoordinate (coordinate) {
      this.map.getView().animate({
        center: coordinate,
        duration: 800,
        resolution: this.panToResolution
      })
    },

    redrawMap () {
      this.map.updateSize()
      this.map.getView().fit(this.initialExtent, this.map.getSize())
    },

    registerFullscreenChangeHandler () {
      const events = ['webkitfullscreenchange', 'mozfullscreenchange', 'fullscreenchange', 'MSFullscreenChange']
      events.forEach((e) => {
        document.addEventListener(e, this.handleFullscreenChange, false)
      })
    },

    registerProjections () {
      this.availableProjections.forEach((projection, idx) => {
        proj4.defs(projection.label, projection.value)
        register(proj4)
        let projToAdd = null
        if (idx === 0) {
          // Set as map projection
          this.mapprojection = new Projection({
            code: this.projectionName,
            units: this.projectionUnits,
            extent: this.maxExtent
          })
          this.mapProjectionExtent = this.mapprojection.getExtent()
          projToAdd = this.mapprojection
        } else {
          const projectionExtent = transform(this.mapProjectionExtent, 'EPSG:3857', projection.label)
          projToAdd = new Projection({
            code: projection.label,
            units: this.projectionUnits,
            extent: projectionExtent
          })
        }
        addProjection(projToAdd)
      })
    },

    resetPopup () {
      $(this.popupoverlay.getElement()).removeClass(this.prefixClass('c-map__popup--small c-map__popup--scrollable c-map__popup--large c-map__popup--hide-action'))
      this.popupoverlay.setPosition(undefined)
    },

    resizeOnDrag () {
      this.map.updateSize()
      // Re-position elements if map gets too small
      const mapSize = this.map.getSize()
      const mapWidth = mapSize[0]
      const scaleElement = document.getElementsByClassName(this.prefixClass('c-map__scale-line'))[0]
      const hintElement = document.getElementsByClassName(this.prefixClass('c-map__hint__show'))[0]
      const miniMapContainer = document.getElementsByClassName('ol-overviewmap')[0]
      if (mapWidth > 642) {
        scaleElement.style.bottom = '11px'
        miniMapContainer.style.bottom = '42px'
        if (typeof hintElement !== 'undefined') {
          hintElement.style.bottom = '15px'
        }
      } else if (mapWidth <= 642 && mapWidth > 505) {
        scaleElement.style.bottom = '22px'
        miniMapContainer.style.bottom = '42px'
        if (typeof hintElement !== 'undefined') {
          hintElement.style.bottom = '15px'
        }
      } else if (mapWidth <= 505 && mapWidth > 458) {
        scaleElement.style.bottom = '33px'
        miniMapContainer.style.bottom = '52px'
        if (typeof hintElement !== 'undefined') {
          hintElement.style.bottom = '10px'
        }
      }
    },

    saveOpacitiesToSessionStorage (id, opacity) {
      this.opacities['GisLayerlayer' + id] = opacity
    },

    selectFirstOption () {
      if (this.autocompleteOptions[0]) {
        this.zoomToSuggestion(this.autocompleteOptions[0])
      }
    },

    setOpacities () {
      const layers = this.layers
      let l = layers.length; let layer; let id

      while (l--) {
        layer = layers[l]
        id = layer.type + 'layer' + layer.id.replaceAll('-', '')
        this.opacities[id] = layer.attributes.opacity && typeof layer.attributes.opacity === 'number' ? layer.attributes.opacity : 100
      }
    },

    createParser () {
      /*
       * Von SH festgelegte Scales #11229. Berechnet via OL2 OpenLayers.Util.getResolutionFromScale(scales[i],'m');
       * List der zur Verfügung stehenden Resolutions
       */

      this.parser = new WMTSCapabilities()
    },

    setLayerSource (layer) {
      if (layer.getSource() === null) {
        const layerObj = this.layers.find(el => el.id.replace(/-/g, '') === layer.get('name'))
        const source = this.createLayerSource(layerObj)
        layer.setSource(source)
      }
    },

    setMeasureTooltip (evt) {
      let geom = evt.target
      let output

      // Get Radius from Circle and format it to Line
      if (geom instanceof GCircle) {
        geom = new GLineString([geom.getCenter(), geom.getLastCoordinate()])
      }

      if (geom instanceof GPolygon) {
        output = this.formatArea(geom)
        this.measureTooltipCoord = geom.getInteriorPoint().getCoordinates()
      }

      if (geom instanceof GLineString) {
        output = this.formatLength(geom)
        this.measureTooltipCoord = geom.getLastCoordinate()
      }

      this.measureTooltipElement.innerHTML = output
      this.measureTooltip.setPosition(this.measureTooltipCoord)
    },

    setView () {
      const resolutions = this.resolutions

      this.mapview = new View({
        center: [this.mapx, this.mapy],
        projection: this.mapprojection,
        resolutions,
        extent: this.maxExtent,
        minResolution: resolutions[(resolutions.length - 1)],
        maxResolution: resolutions[0],
        constrainResolution: true
      })
    },

    showPopup (templateId, content, coordinate) {
      const olPopup = this.popupoverlay
      const $popup = $(olPopup.getElement())
      const contentElement = document.getElementById('popupContent')
      const contentSource = document.getElementById(templateId)

      $(this.prefixClass('.c-actionbox__title--button')).removeClass(this.prefixClass('is-progress'))

      if (typeof content === 'object' && typeof contentElement === 'object') {
        if (templateId === 'miscPopup') {
          contentElement.innerHTML = contentSource.innerHTML.replace(/___title___/g, content.title)
        } else if (templateId === 'contentPopup') {
          contentElement.innerHTML = contentSource.innerHTML.replace(/___title___/g, content.title)
            .replace(/___content___/g, content.text)
        }
      } else {
        contentElement.innerHTML = contentSource.innerHTML.replace(/___content___/g, content)
      }

      if (templateId === 'errorPopup' || templateId === 'criteriaPopup' || templateId === 'miscPopup' || templateId === 'contentPopup') {
        $popup.addClass(this.prefixClass('c-map__popup--hide-action'))
      }

      if (templateId === 'miscPopup') {
        $popup.addClass(this.prefixClass('c-map__popup--small'))
      }

      if (templateId === 'markLocationPopup') {
        $popup.find('#popupAction').html(window.dplan.statement.showMapButtonState(templateId)).show()
      }

      olPopup.setPosition(undefined)
      olPopup.setPosition(coordinate)
    },

    //  Displays a message within popup
    showPopupError (result, coordinate) {
      const messageKeys = {
        failed: 'error.featureinfo.failed',
        empty: 'warning.featureinfo.empty'
      }
      const errorMessage = '<span>' + Translator.trans(messageKeys[result]) + '</span>'
      this.showPopup('errorPopup', errorMessage, coordinate)
    },

    /**
     * We receive results sorted numerically; do a secondary sort alphabetically
     * @param data
     */
    sortResults (data) {
      // Let parsedResponse = JSON.parse(response)
      const searchResults = data.suggestions || []

      searchResults
        .sort((a, b) => {
          if (a.data.postcode === b.data.postcode) {
            const x = a.data.name.toLowerCase()
            const y = b.data.name.toLowerCase()
            return x.localeCompare(y, 'de', { sensitivity: 'base' })
          }
          return a.data.postcode - b.data.postcode
        })

      this.autocompleteOptions = searchResults
    },

    stroke (lineDash) {
      return new Stroke({
        color: this.drawFillSelector('.c-map__draw-stroke'),
        width: 1,
        lineDash: lineDash || 0
      })
    },

    toggleCustomLayerButton ({ element, layerName, visible }) {
      if (typeof element === 'undefined') {
        return
      }
      if (visible === true) {
        element.classList.add(this.prefixClass('is-active'))
      } else if (visible === false) {
        element.classList.remove(this.prefixClass('is-active'))
      } else {
        element.classList.toggle(this.prefixClass('is-active'))
      }
      const newState = element.classList.contains(this.prefixClass('is-active'))
      this.toggleLayer(layerName, false, newState)
      // If item is in a visibility group, also toggle other items in that group
      if (element.id === 'territorySwitcher' && hasOwnProp(this.scope, 'id')) {
        const layerId = this.scope.id.replace(/-/g, '')
        if (hasOwnProp(this.scope, 'attributes') && this.scope.attributes.visibilityGroupId !== '') {
          this.$root.$emit('layer:toggleVisibiltyGroup', { visibilityGroupId: this.scope.attributes.visibilityGroupId, layerId, isVisible: newState })
        } else {
          this.$root.$emit('layer:toggleLayer', { layerId, isVisible: newState })
        }
      }
      if (element.id === 'bplanSwitcher' && hasOwnProp(this.bPlan, 'id')) {
        const layerId = this.bPlan.id.replace(/-/g, '')
        if (hasOwnProp(this.bPlan, 'attributes') && this.bPlan.attributes.visibilityGroupId !== '') {
          this.$root.$emit('layer:toggleVisibiltyGroup', { visibilityGroupId: this.bPlan.attributes.visibilityGroupId, layerId, isVisible: newState })
        } else {
          this.$root.$emit('layer:toggleLayer', { layerId, isVisible: newState })
        }
      }
    },

    toggleLayer (layerId, toggleExclusive = false, newState) {
      if (typeof this.map === 'undefined') return

      const layerGroup = this.map.getLayerGroup()
      const layer = this.findBy(layerGroup, 'name', layerId)

      if (toggleExclusive === true) {
        const baseLayerGroup = this.findBy(layerGroup, 'name', 'baseLayerGroup')
        if (baseLayerGroup) {
          const layers = baseLayerGroup.getLayers().getArray()
          const len = layers.length
          //  Hide all baselayers except those which have to be shown additionally
          for (let i = 0; i < len; i++) {
            if (layers[i].get('doNotToggleLayer') !== true) {
              layers[i].setVisible(false)
            }
          }
          if (layer) {
            this.setLayerSource(layer)
            layer.setVisible(true)
          }
        }
      } else {
        const stateSetter = (typeof newState !== 'undefined') ? newState : (layer.getVisible() === false)
        if (layer) {
          layer.setVisible(stateSetter)
        }
      }
    }
  },

  mounted () {
    this.$store.dispatch('Layers/get', this.procedureId).then(() => {
      this.baseLayers = []
      this.overlayLayers = []
      this.progress = new Progress(document.getElementById('mapProgress'))

      this.setOpacities()
      this.registerFullscreenChangeHandler()
      this.registerProjections()
      this.createParser()
      this.setView()
      this.initializeMap()
      this.createBaseLayers()
      this.createOverlayLayers()
      this.createPopup()
      this.addLayersToMap()
      this.doAllTheOtherExcitingStuff()
      this.addCustomZoomControls()
      this.$store.commit('Layers/setIsMapLoaded')

      if (JSON.stringify(this.procedureInitialExtent) === JSON.stringify(this.procedureDefaultInitialExtent) && this.procedureSettings.coordinate !== '') {
        this.panToCoordinate(JSON.parse('[' + this.procedureSettings.coordinate + ']'))
      }

      if (hasPermission('feature_map_layer_legend_file')) {
        this.displayLegends(true)
      }

      this.$root.$on('layer:toggle', ({ id, exclusively, isVisible }) => {
        /*
         * If no specific layer is set for the overviewMap, the overviewMapTileLayers
         * are synced with their counterparts in the big map.
         */
        if (this.overviewMapLayer === false || this.overviewMapLayer.length > 1) {
          const layer = this.overviewMapTileLayers.find(layer => id === layer.get('name'))
          // Only toggle baselayer
          if (typeof layer !== 'undefined') {
            this.setLayerSource(layer)
            layer.setVisible(isVisible)
          }
        }

        this.toggleLayer(id, exclusively, isVisible)
      })

      this.$root.$on('layer-opacity:change', ({ id, opacity }) => {
        this.findLayerById(id).setOpacity(opacity)
      })

      this.$root.$on('layer-opacity:changed', ({ id, opacity }) => {
        this.saveOpacitiesToSessionStorage(id, opacity)
      })
      this.$root.$on('toolbar:drag', () => this.resizeOnDrag())
      this.$root.$on('layer:toggleVisibiltyGroup', ({ layerId, isVisible, visibilityGroupId }) => {
        if (hasOwnProp(this.bPlan, 'id') && layerId !== this.bPlan.id && hasOwnProp(this.bPlan, 'attributes') && this.bPlan.attributes.visibilityGroupId === visibilityGroupId) {
          this.toggleCustomLayerButton({ element: document.getElementById('bplanSwitcher'), layerName: this.bPlan.id, visible: isVisible })
        }
        if (hasOwnProp(this.scope, 'id') && layerId !== this.scope.id && hasOwnProp(this.scope, 'attributes') && this.scope.attributes.visibilityGroupId === visibilityGroupId) {
          this.toggleCustomLayerButton({ element: document.getElementById('territorySwitcher'), layerName: this.scope.id, visible: isVisible })
        }
      })
    })
  }
}
</script>
