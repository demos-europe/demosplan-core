<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    v-if="hasPermission('feature_public_index_map')"
    ref="mapEl"
    class="c-publicindex__map isolate"
    :aria-label="Translator.trans('map')"
  />
</template>

<script>
import { mapActions, mapGetters, mapState } from 'vuex'
import { getCssVariable } from '@demos-europe/demosplan-ui'
import L from 'leaflet'
import proj4 from 'proj4'

/*
 * Leaflet.markercluster's UMD wrapper reads the bare global `L` with no require('leaflet') of
 * its own - it must find `window.L` already set before it's imported, or it throws
 */
window.L = L

// eslint-disable-next-line sort-imports
import 'leaflet.markercluster'

export default {
  name: 'DpMap',

  props: {
    mapData: {
      type: Object,
      required: true,
    },

    initialMapSettings: {
      type: Object,
      required: false,
      default: () => ({}),
    },

    isPublicAgency: {
      type: Boolean,
      required: false,
      default: false,
    },

    isPublicUser: {
      type: Boolean,
      required: false,
      default: false,
    },

    projectionName: {
      type: String,
      required: false,
      default: window.dplan.defaultProjectionLabel,
    },

    projectionString: {
      type: String,
      required: false,
      default: window.dplan.defaultProjectionString,
    },

    projectVersion: {
      required: false,
      type: String,
      default: '',
    },
  },

  data () {
    return {
      map: null,
      clusterGroup: null,
      markers: new Map(),
      clusterOptions: {
        iconCreateFunction: function (cluster) {
          return L.divIcon({
            html: '<div><span>' + cluster.getChildCount() + '</span></div>',
            className: 'c-publicindex__marker-cluster',
            iconSize: new L.Point(40, 40),
          })
        },
      },
      /*
       * Get color to fill the markers from css custom property, fallback to platform style.
       * The replace() statements are needed because the # sign is already appended in the svg
       * where the color is used. We do not use regex inside replace() because "some browsers"
       * do not support the resulting syntax.
       */
      markersColor: getCssVariable('--dp-token-color-brand-highlight') ? getCssVariable('--dp-token-color-brand-highlight').replace(' ', '').replace('#', '') : 'a4004c',

      mapCRS: L.CRS[window.dplan.defaultProjectionLabel.replace(':', '')],
    }
  },

  computed: {
    ...mapGetters('Procedure', [
      'currentProcedureId',
      'currentView',
      'shouldMapZoomBeSet',
    ]),

    ...mapState('Procedure', {
      proceduresFromStore: 'procedures',
    }),

    bbox () {
      const projName = this.projectionName
      const projString = this.projectionString

      proj4.defs(projName, projString)

      const bb = this.mapData.publicExtent.replace(/[[|\]]/g, '').split(',').map(v => v * 1)
      const bbsw = proj4(projName, 'WGS84', [bb[0], bb[1]]).reverse()
      const bbne = proj4(projName, 'WGS84', [bb[2], bb[3]]).reverse()
      const sw = new L.LatLng(bbsw[0], bbsw[1])
      const ne = new L.LatLng(bbne[0], bbne[1])

      return [sw, ne]
    },

    initialLocation () {
      const settings = this.initialMapSettings

      return new L.LatLng(settings.initialLat, settings.initialLon)
    },

    initialZoom () {
      return this.initialMapSettings.initialZoom
    },

    mapOptions () {
      return {
        maxBounds: this.bbox,
        attributionControl: false,
        zoomControl: false,
        minZoom: 7,
        maxZoom: 18,
        crs: this.mapCRS,
      }
    },

    procedures () {
      return this.proceduresFromStore.filter(procedure => procedure.coordinate !== '')
    },

    /*
     * Watching `procedures` directly would watch an array, which the app-wide `WATCH_ARRAY` @vue/compat flag silently
     * forces into a deep watch - firing the handler many times per change instead of once. Watch this primitive instead.
     */
    procedureIds () {
      return this.procedures.map(procedure => procedure.id).join(',')
    },

    tooltipOffset () {
      return new L.Point(0, -22)
    },

    wmsLayers () {
      const mapData = this.mapData
      const settings = this.initialMapSettings

      return [{
        name: 'baseLayer',
        url: mapData.publicBaselayer,
        layers: mapData.publicBaselayerLayers,
        format: 'image/png',
        crs: this.mapCRS,
        // MaxZoom: 14,
        minZoom: settings.minZoom,
        bounds: this.bbox,
        transparent: true,
      }]
    },
  },

  watch: {
    procedureIds: {
      handler () {
        this.syncMarkers()
      },
      deep: false,
    },

    currentView: {
      handler (newVal) {
        if (newVal === 'DpDetailView') {
          this.zoomToMarker(this.currentProcedureId)
        }

        if (newVal === 'DpList') {
          this.setZoom()
        }
      },
      deep: false, // Set default for migrating purpose. To know this occurrence is checked

    },

    shouldMapZoomBeSet: {
      handler (newVal) {
        if (newVal) {
          this.$nextTick(() => this.setZoom())
        }
      },
      deep: false, // Set default for migrating purpose. To know this occurrence is checked
    },
  },

  methods: {
    ...mapActions('Procedure', [
      'showDetailView',
    ]),

    activateMarker (id) {
      this.zoomToMarker(id)
      this.showDetailView(id)
    },

    addFooter () {
      /*
       * To reliably show imprint/privacy links also with expanded drawer
       * on narrow viewports, the links are put into the attribution section
       * of the leaflet map - only on the public index page in bauleitplanung-online.
       */
      const dataProtectionLink = `<a href="${Routing.generate('DemosPlan_misccontent_static_dataprotection')}" class="c-publicindex__footer-item">${Translator.trans('privacy')}</a>`
      const imprintLink = `<a href="${Routing.generate('DemosPlan_misccontent_static_imprint')}" class="c-publicindex__footer-item">${Translator.trans('imprint')}</a>`
      const termsLink = hasPermission('area_terms_of_use') ? `<a href="${Routing.generate('DemosPlan_misccontent_static_terms')}" class="c-publicindex__footer-item">${Translator.trans('terms.of.use')}</a>` : ''
      const attribution = `<span class="c-publicindex__footer-item">${this.mapData.mapAttribution || ''}</span>${this.projectVersion}`

      const attributionHtml = dataProtectionLink + imprintLink + termsLink + attribution

      L.control.attribution({
        prefix: `<div class="c-publicindex__footer">${attributionHtml}</div>`,
      }).addTo(this.map)
    },

    addZoomControl () {
      this.map.addControl(L.control.zoom({ position: 'topright' }))
    },

    coordinate (coordinate) {
      const latLng = coordinate.split(',')

      return this.proj(latLng)
    },

    customMarker (procedure) {
      const accessType = this.determineAccessType(procedure)

      return accessType === 'write' ?
        L.icon({
          iconUrl: `data:image/svg+xml,%3Csvg
            xmlns='http://www.w3.org/2000/svg'
            width='30'
            height='40'%3E%3Cpath
            d='M13.4584 39.193C2.10703 22.7368 0 21.0479 0 15 0 6.7157 6.7157 0 15 0s15 6.7157 15 15c0 6.0479-2.107 7.7368-13.4584 24.193-.745 1.0761-2.3383 1.076-3.0832 0Z'
            fill='%23${this.markersColor}'/%3E%3Cpath
            d='M15.2915 6.66699c-4.764 0-8.625 3.11719-8.625 6.96431 0 1.6607.721 3.1808 1.92041 4.3761-.42114 1.6875-1.82944 3.1908-1.84628 3.2076-.07413.077-.09434.1908-.05054.2913.0438.1004.13813.1607.24595.1607 2.23374 0 3.90816-1.0647 4.73696-1.721 1.1018.4118 2.3248.6496 3.6185.6496 4.764 0 8.625-3.1172 8.625-6.9643 0-3.84712-3.861-6.96431-8.625-6.96431Z'
            fill='%23fff'
            /%3E%3C/svg%3E`,
          iconSize: [30, 40],
        }) :
        L.icon({
          iconUrl: `data:image/svg+xml,%3Csvg
            xmlns='http://www.w3.org/2000/svg'
            width='30'
            height='40'%3E%3Cpath
            d='M13.4584 39.193C2.10703 22.7368 0 21.0479 0 15 0 6.7157 6.7157 0 15 0s15 6.7157 15 15c0 6.0479-2.107 7.7368-13.4584 24.193-.745 1.0761-2.3383 1.076-3.0832 0Z'
            fill='%23${this.markersColor}'
            /%3E%3C/svg%3E`,
          iconSize: [30, 40],
        })
    },

    /*
     * For a certain combination of role and permissionSet, determine
     * if an item should be shown as readable or writable.
     */
    determineAccessType ({ externalPhasePermissionset, internalPhasePermissionset }) {
      let accessType

      if (this.isPublicUser && this.isPublicAgency) {
        if (externalPhasePermissionset === 'write' || internalPhasePermissionset === 'write') {
          accessType = 'write'
        } else {
          accessType = 'read'
        }
      }

      if (this.isPublicUser && !this.isPublicAgency) {
        accessType = externalPhasePermissionset
      }

      if (!this.isPublicUser && this.isPublicAgency) {
        accessType = internalPhasePermissionset
      }

      // For any case not handled above, use the external permissionSet.
      return accessType || externalPhasePermissionset
    },

    proj (coords) {
      // Shouldn't be needed but otherwise proj4 cries about infinitive numbers
      coords = [Math.round(coords[0]), Math.round(coords[1])]

      return proj4(this.projectionName, 'WGS84', coords).reverse()
    },

    publicDetailHref (procedure) {
      return Routing.generate('DemosPlan_procedure_public_detail', { procedure: procedure.id })
    },

    setZoom () {
      if (this.procedures.length) {
        setTimeout(() => {
          if (this.clusterGroup.getLayers().length) {
            const bounds = this.clusterGroup.getBounds().pad(0.1)

            this.map.fitBounds(bounds)

            if (this.map.getZoom() > 16) {
              this.map.setZoom(12)
            }
          }
        }, 0)
      }
    },

    syncMarkers () {
      this.clusterGroup.clearLayers()
      this.markers.clear()

      this.procedures.forEach(procedure => {
        const marker = L.marker(this.coordinate(procedure.coordinate), { icon: this.customMarker(procedure) })

        marker.bindTooltip(this.tooltipContent(procedure), { direction: 'top', offset: this.tooltipOffset })
        marker.on('click', () => this.activateMarker(procedure.id))

        this.markers.set(procedure.id, marker)
        this.clusterGroup.addLayer(marker)
      })
    },

    tooltipContent (procedure) {
      const accessType = this.determineAccessType(procedure)

      return Translator.trans(accessType === 'write' ? 'phase.writable' : 'phase.readable')
    },

    zoomToMarker (id) {
      const latLng = this.markers.get(id)?.getLatLng()

      if (latLng) {
        this.map.fitBounds([latLng, latLng])
      }
    },
  },

  mounted () {
    if (!this.$refs.mapEl) {
      return
    }

    this.map = L.map(this.$refs.mapEl, this.mapOptions).setView(this.initialLocation, this.initialZoom)

    this.wmsLayers.forEach(({ url, layers, format, transparent, crs, minZoom }) => {
      L.tileLayer.wms(url, {
        layers,
        format,
        transparent,
        crs,
        minZoom,
      }).addTo(this.map)
    })

    this.clusterGroup = L.markerClusterGroup(this.clusterOptions).addTo(this.map)
    this.syncMarkers()

    this.addZoomControl()
    this.addFooter()
  },

  beforeUnmount () {
    this.map?.remove()
  },
}
</script>
