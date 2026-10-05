/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/*
 * Not covered here (needs real DOM/canvas rendering, verified manually in the browser instead):
 * tile rendering, marker cluster expand/collapse interaction, visual icon styling,
 * WMS layer visibly displaying.
 */
import { createStore, Store } from 'vuex'
import { enableAutoUnmount, VueWrapper } from '@vue/test-utils'
import DpMap from '@DpJs/components/procedure/publicindex/map/Map.vue'
import L from 'leaflet'
import ProcedureStore from '@DpJs/store/procedure/Procedure'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import { vi } from 'vitest'

const mapInstance = {
  addControl: vi.fn(),
  fitBounds: vi.fn(),
  getZoom: vi.fn(() => 10),
  setZoom: vi.fn(),
  setView: vi.fn(),
  remove: vi.fn(),
}

mapInstance.setView.mockReturnValue(mapInstance)

const tileLayerInstance = { addTo: vi.fn() }

const clusterGroupInstance = {
  addTo: vi.fn(),
  clearLayers: vi.fn(),
  addLayer: vi.fn(),
  getLayers: vi.fn(() => []),
  getBounds: vi.fn(() => ({ pad: vi.fn(() => 'padded-bounds') })),
}

clusterGroupInstance.addTo.mockReturnValue(clusterGroupInstance)

const attributionControlInstance = { addTo: vi.fn() }

vi.mock('leaflet.markercluster', () => ({}))

vi.mock('leaflet', () => ({
  default: {
    map: vi.fn(() => mapInstance),
    tileLayer: { wms: vi.fn(() => tileLayerInstance) },
    markerClusterGroup: vi.fn(() => clusterGroupInstance),
    marker: vi.fn((latLng, options) => ({
      latLng,
      options,
      bindTooltip: vi.fn(),
      on: vi.fn(),
      getLatLng: () => latLng,
    })),
    icon: vi.fn(options => ({ __type: 'icon', ...options })),
    divIcon: vi.fn(options => ({ __type: 'divIcon', ...options })),
    control: {
      zoom: vi.fn(() => ({})),
      attribution: vi.fn(() => attributionControlInstance),
    },
    LatLng: vi.fn(function (lat: number, lng: number) {
      return { lat, lng }
    }),
    Point: vi.fn(function (x: number, y: number) {
      return { x, y }
    }),
    CRS: { EPSG3857: { name: 'EPSG3857' } },
  },
}))

enableAutoUnmount(afterEach)

describe('DpMap', () => {
  let store: Store<any>
  let wrapper: VueWrapper<any>

  const mapData = {
    publicExtent: '[100000,100000,200000,200000]',
    publicBaselayer: 'https://example.com/wms',
    publicBaselayerLayers: 'base',
    mapAttribution: 'Attribution',
  }

  const initialMapSettings = {
    initialLat: 52.5,
    initialLon: 13.4,
    initialZoom: 10,
    minZoom: 7,
  }

  const mountMap = (props: Record<string, unknown> = {}) => shallowMountWithGlobalMocks(DpMap, {
    props: {
      mapData,
      initialMapSettings,
      projectionName: 'WGS84',
      projectionString: '+proj=longlat +datum=WGS84 +no_defs',
      ...props,
    },
    global: {
      plugins: [store],
    },
  })

  beforeEach(() => {
    dplan.defaultProjectionLabel = 'EPSG:3857'
    vi.clearAllMocks()
    clusterGroupInstance.getLayers.mockReturnValue([])

    store = createStore({
      modules: {
        Procedure: ProcedureStore,
      },
    })

    // ProcedureStore's module state is a shared object literal, not a factory - reset it explicitly per test.
    store.commit('Procedure/reset')
  })

  describe('pure logic', () => {
    it.each([
      [true, true, 'write', 'read', 'write'],
      [true, true, 'read', 'read', 'read'],
      [true, false, 'write', 'read', 'write'],
      [true, false, 'read', 'write', 'read'],
      [false, true, 'write', 'write', 'write'],
      [false, true, 'read', 'read', 'read'],
      [false, false, 'write', 'read', 'write'],
    ])('determineAccessType(isPublicUser=%s, isPublicAgency=%s, external=%s, internal=%s) -> %s', (isPublicUser, isPublicAgency, externalPhasePermissionset, internalPhasePermissionset, expected) => {
      wrapper = mountMap({ isPublicUser, isPublicAgency })

      expect(wrapper.vm.determineAccessType({ externalPhasePermissionset, internalPhasePermissionset })).toBe(expected)
    })

    it('returns the write icon (with the white inner path) for write access', () => {
      wrapper = mountMap({ isPublicUser: true, isPublicAgency: false })
      const icon = wrapper.vm.customMarker({ externalPhasePermissionset: 'write', internalPhasePermissionset: 'write' })

      expect(icon.iconUrl).toContain('fff')
    })

    it('returns the read icon (without the white inner path) for read access', () => {
      wrapper = mountMap({ isPublicUser: true, isPublicAgency: false })
      const icon = wrapper.vm.customMarker({ externalPhasePermissionset: 'read', internalPhasePermissionset: 'read' })

      expect(icon.iconUrl).not.toContain('fff')
    })

    it('converts a "lon,lat" coordinate string into a [lat, lon] pair', () => {
      wrapper = mountMap()

      expect(wrapper.vm.coordinate('13,52')).toEqual([52, 13])
    })

    it('picks the writable tooltip translation for write access', () => {
      wrapper = mountMap({ isPublicUser: true, isPublicAgency: false })

      expect(wrapper.vm.tooltipContent({ externalPhasePermissionset: 'write', internalPhasePermissionset: 'write' })).toBe('phase.writable')
    })

    it('picks the readable tooltip translation for read access', () => {
      wrapper = mountMap({ isPublicUser: true, isPublicAgency: false })

      expect(wrapper.vm.tooltipContent({ externalPhasePermissionset: 'read', internalPhasePermissionset: 'read' })).toBe('phase.readable')
    })
  })

  describe('leaflet integration', () => {
    it('builds the map, WMS layer, and cluster group on mount', () => {
      mountMap()

      expect(L.map).toHaveBeenCalledWith(expect.any(HTMLElement), expect.objectContaining({
        minZoom: 7,
        maxZoom: 18,
      }))
      expect(L.tileLayer.wms).toHaveBeenCalledWith(mapData.publicBaselayer, expect.objectContaining({
        layers: mapData.publicBaselayerLayers,
        transparent: true,
      }))
      expect(tileLayerInstance.addTo).toHaveBeenCalledWith(mapInstance)
      // Leaflet.markercluster (no official types) extends L with markerClusterGroup at runtime
      expect((L as any).markerClusterGroup).toHaveBeenCalled()
      expect(clusterGroupInstance.addTo).toHaveBeenCalledWith(mapInstance)
    })

    it('syncMarkers builds one marker per procedure and adds it to the cluster group', () => {
      store.commit('Procedure/setProcedures', [
        { id: '1', coordinate: '13,52', externalPhasePermissionset: 'read', internalPhasePermissionset: 'read' },
        { id: '2', coordinate: '14,53', externalPhasePermissionset: 'write', internalPhasePermissionset: 'write' },
      ])

      wrapper = mountMap()

      expect(clusterGroupInstance.clearLayers).toHaveBeenCalled()
      expect(clusterGroupInstance.addLayer).toHaveBeenCalledTimes(2)
      expect(wrapper.vm.markers.size).toBe(2)
      expect(wrapper.vm.markers.get('1').getLatLng()).toEqual([52, 13])
    })

    it('zoomToMarker fits the map to the marker matching the given id', () => {
      store.commit('Procedure/setProcedures', [
        { id: '1', coordinate: '13,52', externalPhasePermissionset: 'read', internalPhasePermissionset: 'read' },
      ])

      wrapper = mountMap()
      wrapper.vm.zoomToMarker('1')

      expect(mapInstance.fitBounds).toHaveBeenCalledWith([[52, 13], [52, 13]])
    })

    it('zoomToMarker does nothing for an unknown id', () => {
      wrapper = mountMap()
      wrapper.vm.zoomToMarker('unknown')

      expect(mapInstance.fitBounds).not.toHaveBeenCalled()
    })

    it('setZoom fits bounds to the cluster group when procedures exist', async () => {
      store.commit('Procedure/setProcedures', [
        { id: '1', coordinate: '13,52', externalPhasePermissionset: 'read', internalPhasePermissionset: 'read' },
      ])
      clusterGroupInstance.getLayers.mockReturnValue([{}])

      wrapper = mountMap()
      wrapper.vm.setZoom()

      await new Promise(resolve => setTimeout(resolve, 0))

      expect(mapInstance.fitBounds).toHaveBeenCalledWith('padded-bounds')
    })

    it('setZoom is a no-op when there are no procedures', async () => {
      wrapper = mountMap()
      wrapper.vm.setZoom()

      await new Promise(resolve => setTimeout(resolve, 0))

      expect(mapInstance.fitBounds).not.toHaveBeenCalled()
    })

    it('removes the map instance on unmount', () => {
      wrapper = mountMap()
      wrapper.unmount()

      expect(mapInstance.remove).toHaveBeenCalled()
    })
  })
})
