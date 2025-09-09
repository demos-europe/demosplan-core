/**
 * Converts a map extent array to an object with latitude and longitude.
 *
 * If the extent array is null, not an object, or has less than 2 elements, it returns null.
 * If the extent array has only one coordinate (is a point), it returns an object with 'latitude' and 'longitude' properties.
 * If the extent array has 4 elements, it returns an object with 'start' and 'end' properties, each being an object with 'latitude' and 'longitude'.
 * This method will not validate the extent array, so it is the caller's responsibility to ensure it is valid.
 * It won't return meaningful results for Arrays with more than 4 elements.
 *
 * @param {Array} extentArray - The extent array to be converted.
 * @returns {Object|null} An object with 'latitude' and 'longitude' properties, or 'start' and 'end' properties, or null.
 */
export default function convertExtentToObject (extentArray) {
  if (extentArray === null || typeof extentArray !== 'object' || extentArray.length < 2) {
    return null
  }

  if (extentArray.length < 4) {
    return {
      latitude: extentArray[0],
      longitude: extentArray[1]
    }
  }

  return {
    start: {
      latitude: extentArray[0],
      longitude: extentArray[1]
    },
    end: {
      latitude: extentArray[2],
      longitude: extentArray[3]
    }
  }
}
