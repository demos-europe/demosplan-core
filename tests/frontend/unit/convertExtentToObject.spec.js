import convertExtentToObject from '@DpJs/components/map/map/utils/convertExtentToObject'

describe('convertExtentToObject', () => {
  it('returns null for invalid input', () => {
    const extentArray = 'invalid input'
    const result = convertExtentToObject(extentArray)
    expect(result).toBeNull()
  })

  it('returns an object with latitude and longitude for an extent array with two elements', () => {
    const extentArray = [1, 2]
    const result = convertExtentToObject(extentArray)
    expect(result).toEqual({ latitude: 1, longitude: 2 })
  })

  it('returns an object with start and end properties for an extent array with four elements', () => {
    const extentArray = [1, 2, 3, 4]
    const result = convertExtentToObject(extentArray)
    expect(result).toEqual({
      start: { latitude: 1, longitude: 2 },
      end: { latitude: 3, longitude: 4 }
    })
  })
})
