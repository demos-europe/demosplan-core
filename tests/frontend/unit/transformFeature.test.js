/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { transformFeatureCollection, transformGeometry } from '@DpJs/lib/map/transformFeature'

// Mock proj4 to avoid dependency on actual projections
jest.mock('proj4', () => {
  const mockTransformer = {
    forward: jest.fn((coord) => {
      // Mock transformation: simply add 1000 to each coordinate
      return [coord[0] + 1000, coord[1] + 1000]
    })
  }
  
  return jest.fn(() => mockTransformer)
})

describe('transformFeature', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  describe('transformGeometry', () => {
    it('transforms Point geometry', () => {
      const pointGeometry = {
        type: 'Point',
        coordinates: [10, 20]
      }

      const result = transformGeometry(pointGeometry, 'EPSG:4326', 'EPSG:3857')

      expect(result).toEqual({
        type: 'Point',
        coordinates: [1010, 1020]
      })
    })

    it('transforms LineString geometry', () => {
      const lineStringGeometry = {
        type: 'LineString',
        coordinates: [[10, 20], [30, 40]]
      }

      const result = transformGeometry(lineStringGeometry, 'EPSG:4326', 'EPSG:3857')

      expect(result).toEqual({
        type: 'LineString',
        coordinates: [[1010, 1020], [1030, 1040]]
      })
    })

    it('transforms MultiPoint geometry', () => {
      const multiPointGeometry = {
        type: 'MultiPoint',
        coordinates: [[10, 20], [30, 40]]
      }

      const result = transformGeometry(multiPointGeometry, 'EPSG:4326', 'EPSG:3857')

      expect(result).toEqual({
        type: 'MultiPoint',
        coordinates: [[1010, 1020], [1030, 1040]]
      })
    })

    it('transforms Polygon geometry', () => {
      const polygonGeometry = {
        type: 'Polygon',
        coordinates: [[[10, 20], [30, 40], [50, 60], [10, 20]]]
      }

      const result = transformGeometry(polygonGeometry, 'EPSG:4326', 'EPSG:3857')

      expect(result).toEqual({
        type: 'Polygon',
        coordinates: [[[1010, 1020], [1030, 1040], [1050, 1060], [1010, 1020]]]
      })
    })

    it('transforms MultiLineString geometry', () => {
      const multiLineStringGeometry = {
        type: 'MultiLineString',
        coordinates: [[[10, 20], [30, 40]], [[50, 60], [70, 80]]]
      }

      const result = transformGeometry(multiLineStringGeometry, 'EPSG:4326', 'EPSG:3857')

      expect(result).toEqual({
        type: 'MultiLineString',
        coordinates: [[[1010, 1020], [1030, 1040]], [[1050, 1060], [1070, 1080]]]
      })
    })

    it('transforms MultiPolygon geometry', () => {
      const multiPolygonGeometry = {
        type: 'MultiPolygon',
        coordinates: [
          [[[10, 20], [30, 40], [50, 60], [10, 20]]],
          [[[70, 80], [90, 100], [110, 120], [70, 80]]]
        ]
      }

      const result = transformGeometry(multiPolygonGeometry, 'EPSG:4326', 'EPSG:3857')

      expect(result).toEqual({
        type: 'MultiPolygon',
        coordinates: [
          [[[1010, 1020], [1030, 1040], [1050, 1060], [1010, 1020]]],
          [[[1070, 1080], [1090, 1100], [1110, 1120], [1070, 1080]]]
        ]
      })
    })

    it('returns original geometry for unknown type', () => {
      const unknownGeometry = {
        type: 'UnknownType',
        coordinates: [10, 20]
      }

      const result = transformGeometry(unknownGeometry, 'EPSG:4326', 'EPSG:3857')

      expect(result).toEqual(unknownGeometry)
    })

    it('uses default target projection when not specified', () => {
      const pointGeometry = {
        type: 'Point',
        coordinates: [10, 20]
      }

      transformGeometry(pointGeometry, 'EPSG:4326')

      // Check that proj4 was called with correct parameters
      const proj4 = require('proj4')
      expect(proj4).toHaveBeenCalledWith('EPSG:4326', 'EPSG:3857')
    })

    it('preserves original geometry properties', () => {
      const pointGeometry = {
        type: 'Point',
        coordinates: [10, 20],
        customProperty: 'test'
      }

      const result = transformGeometry(pointGeometry, 'EPSG:4326', 'EPSG:3857')

      expect(result.customProperty).toBe('test')
    })
  })

  describe('transformFeatureCollection', () => {
    it('transforms all features in a feature collection', () => {
      const featureCollection = {
        type: 'FeatureCollection',
        features: [
          {
            type: 'Feature',
            geometry: {
              type: 'Point',
              coordinates: [10, 20]
            },
            properties: {
              name: 'Feature 1'
            }
          },
          {
            type: 'Feature',
            geometry: {
              type: 'LineString',
              coordinates: [[30, 40], [50, 60]]
            },
            properties: {
              name: 'Feature 2'
            }
          }
        ]
      }

      const result = transformFeatureCollection(featureCollection, 'EPSG:4326', 'EPSG:3857')

      expect(result.type).toBe('FeatureCollection')
      expect(result.features).toHaveLength(2)
      
      // Check first feature
      expect(result.features[0].geometry.coordinates).toEqual([1010, 1020])
      expect(result.features[0].properties.name).toBe('Feature 1')
      
      // Check second feature
      expect(result.features[1].geometry.coordinates).toEqual([[1030, 1040], [1050, 1060]])
      expect(result.features[1].properties.name).toBe('Feature 2')
    })

    it('preserves feature collection properties', () => {
      const featureCollection = {
        type: 'FeatureCollection',
        customProperty: 'test',
        features: [
          {
            type: 'Feature',
            geometry: {
              type: 'Point',
              coordinates: [10, 20]
            },
            properties: {}
          }
        ]
      }

      const result = transformFeatureCollection(featureCollection, 'EPSG:4326', 'EPSG:3857')

      expect(result.customProperty).toBe('test')
    })

    it('uses default target projection when not specified', () => {
      const featureCollection = {
        type: 'FeatureCollection',
        features: [
          {
            type: 'Feature',
            geometry: {
              type: 'Point',
              coordinates: [10, 20]
            },
            properties: {}
          }
        ]
      }

      transformFeatureCollection(featureCollection, 'EPSG:4326')

      // Check that proj4 was called with correct parameters
      const proj4 = require('proj4')
      expect(proj4).toHaveBeenCalledWith('EPSG:4326', 'EPSG:3857')
    })

    it('handles empty feature collection', () => {
      const featureCollection = {
        type: 'FeatureCollection',
        features: []
      }

      const result = transformFeatureCollection(featureCollection, 'EPSG:4326', 'EPSG:3857')

      expect(result.type).toBe('FeatureCollection')
      expect(result.features).toHaveLength(0)
    })

    it('preserves feature properties and type', () => {
      const featureCollection = {
        type: 'FeatureCollection',
        features: [
          {
            type: 'Feature',
            id: 'test-id',
            geometry: {
              type: 'Point',
              coordinates: [10, 20]
            },
            properties: {
              name: 'Test Feature',
              description: 'A test feature'
            }
          }
        ]
      }

      const result = transformFeatureCollection(featureCollection, 'EPSG:4326', 'EPSG:3857')

      expect(result.features[0].type).toBe('Feature')
      expect(result.features[0].id).toBe('test-id')
      expect(result.features[0].properties.name).toBe('Test Feature')
      expect(result.features[0].properties.description).toBe('A test feature')
    })
  })
})