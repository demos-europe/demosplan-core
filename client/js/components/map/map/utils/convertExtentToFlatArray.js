import { hasOwnProp } from '@demos-europe/demosplan-ui'

/**
 * Converts a given extent object to a flat array.
 *
 * If the extent object has 'start' and 'end' properties, it returns an array
 * containing the latitude and longitude of both the start and end points.
 *
 * If the extent object does not have 'start' and 'end' properties, it returns an array
 * containing the latitude and longitude of the extent object.
 *
 * @param {Object} extent - The extent object to be converted.
 * @returns {Array} An array containing the latitude and longitude values.
 */
export default function convertExtentToFlatArray (extent) {
  if (extent === null || typeof extent !== 'object') {
    return []
  }

  if (hasOwnProp(extent, 'start') && hasOwnProp(extent, 'end')) {
    return [
      extent.start.latitude,
      extent.start.longitude,
      extent.end.latitude,
      extent.end.longitude
    ]
  }

  return [extent?.latitude, extent?.longitude]
}
