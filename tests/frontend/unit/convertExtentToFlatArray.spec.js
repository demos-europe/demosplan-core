import convertExtentToFlatArray from '@DpJs/components/map/map/utils/convertExtentToFlatArray'

describe('convertExtentToFlatArray', () => {
  it('returns a flat array for an extent object with start and end properties', () => {
    const extent = {
      start: { latitude: 1, longitude: 2 },
      end: { latitude: 3, longitude: 4 }
    }

    const result = convertExtentToFlatArray(extent)

    expect(result).toEqual([1, 2, 3, 4])
  })

  it('returns a flat array for an extent object without start and end properties', () => {
    const extent = { latitude: 1, longitude: 2 }

    const result = convertExtentToFlatArray(extent)

    expect(result).toEqual([1, 2])
  })
})
