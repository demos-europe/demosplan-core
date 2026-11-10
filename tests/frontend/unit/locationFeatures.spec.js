import {
  getConsistentLocationData,
  getLocationIsSet,
  getLocationReferenceCoordinates,
  hasLocationFeatures,
  mergeFeatureCollections,
} from '@DpJs/lib/map/locationFeatures'

const drawing = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{},"geometry":{"type":"Polygon","coordinates":[[[0,0],[0,1],[1,1],[0,0]]]}}]}'
const locationReference = '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Point","coordinates":[3.5,4.5]},"properties":null}]}'

describe('locationFeatures', () => {
  describe('mergeFeatureCollections', () => {
    it('combines the features of the drawing and the location reference', () => {
      const result = mergeFeatureCollections(drawing, locationReference)

      expect(result.type).toBe('FeatureCollection')
      expect(result.features).toHaveLength(2)
      expect(result.features[0].geometry.type).toBe('Polygon')
      expect(result.features[1].geometry.type).toBe('Point')
    })

    it('accepts already parsed collections', () => {
      const result = mergeFeatureCollections(JSON.parse(drawing), locationReference)

      expect(result.features).toHaveLength(2)
    })

    it('ignores empty and invalid values', () => {
      expect(mergeFeatureCollections(drawing, '', null, undefined, 'not json').features).toHaveLength(1)
    })

    it('returns an empty object if there is nothing to show', () => {
      expect(mergeFeatureCollections('', undefined)).toEqual({})
      expect(mergeFeatureCollections()).toEqual({})
    })
  })

  describe('hasLocationFeatures', () => {
    it('is true if the drawing or the location reference has features', () => {
      expect(hasLocationFeatures(drawing, '')).toBe(true)
      expect(hasLocationFeatures('', locationReference)).toBe(true)
    })

    it('is false if both are empty', () => {
      expect(hasLocationFeatures('', '')).toBe(false)
      expect(hasLocationFeatures(undefined, null)).toBe(false)
    })
  })

  describe('getLocationReferenceCoordinates', () => {
    it('returns the point as "x,y" string like the statement form keeps it', () => {
      expect(getLocationReferenceCoordinates(locationReference)).toBe('3.5,4.5')
    })

    it('returns an empty string without a point', () => {
      expect(getLocationReferenceCoordinates('')).toBe('')
      expect(getLocationReferenceCoordinates(drawing)).toBe('')
      expect(getLocationReferenceCoordinates('not json')).toBe('')
    })
  })

  describe('getLocationIsSet', () => {
    it('prefers the priority area', () => {
      expect(getLocationIsSet('PR3_STE_027', '3,4', drawing)).toBe('priority_area')
    })

    it('is "point" if only a place is marked', () => {
      expect(getLocationIsSet('', '3,4', '')).toBe('point')
    })

    it('is "geometry" if there is a drawing, with or without a marked place', () => {
      expect(getLocationIsSet('', '', drawing)).toBe('geometry')
      expect(getLocationIsSet('', '3,4', drawing)).toBe('geometry')
    })
  })

  describe('getConsistentLocationData', () => {
    const formWithBoth = {
      r_location: 'point',
      r_location_geometry: drawing,
      r_location_point: '3.5,4.5',
      r_location_priority_area_key: '',
    }

    it('keeps the marked place when only the drawing is cleared', () => {
      const data = { r_location_geometry: '' }

      expect(getConsistentLocationData(formWithBoth, data)).toEqual(data)
    })

    it('keeps the drawing when only a place is marked', () => {
      const data = { r_location_point: '1,2', location_is_set: 'point' }

      expect(getConsistentLocationData(formWithBoth, data)).toEqual(data)
    })

    it('resets to "not located" when nothing of the map reference is left', () => {
      const form = { ...formWithBoth, r_location_point: '' }

      expect(getConsistentLocationData(form, { r_location_geometry: '' })).toEqual({
        r_location_geometry: '',
        r_location: 'notLocated',
        location_is_set: '',
      })
    })

    it('does not touch other location types such as the county', () => {
      const form = { ...formWithBoth, r_location: 'county', r_location_geometry: '', r_location_point: '' }

      expect(getConsistentLocationData(form, { r_county: 'abc' })).toEqual({ r_county: 'abc' })
    })

    it('keeps a selected priority area', () => {
      const form = { ...formWithBoth, r_location_geometry: '', r_location_point: '' }
      const data = { r_location_priority_area_key: 'PR3_STE_027' }

      expect(getConsistentLocationData(form, data)).toEqual(data)
    })
  })
})
