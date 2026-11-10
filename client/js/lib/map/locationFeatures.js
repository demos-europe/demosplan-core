/**
 * Helpers for the two map related values of a statement:
 * - the drawing ("Einzeichnung"), stored in the attribute `polygon`
 * - the location reference ("Ortsbezug"), stored in the attribute `locationReference`
 *
 * Both are GeoJSON FeatureCollections, given as strings (or already parsed objects).
 */

function parseFeatureCollection (featureCollection) {
  if (featureCollection === null || featureCollection === undefined || featureCollection === '') {
    return null
  }

  if (typeof featureCollection === 'object') {
    return Array.isArray(featureCollection.features) ? featureCollection : null
  }

  try {
    const parsed = JSON.parse(featureCollection)

    return Array.isArray(parsed?.features) ? parsed : null
  } catch (error) {
    return null
  }
}

/**
 * Merge the features of the given FeatureCollections into one FeatureCollection.
 * Empty or invalid values are ignored. Returns an empty object if there are no features,
 * because this is what the map modals expect for "nothing to show".
 *
 * @param {...(string|Object|null|undefined)} featureCollections
 *
 * @return {Object}
 */
function mergeFeatureCollections (...featureCollections) {
  const features = featureCollections
    .map(parseFeatureCollection)
    .filter(collection => collection !== null)
    .flatMap(collection => collection.features)

  return features.length > 0 ? { type: 'FeatureCollection', features } : {}
}

/**
 * Is there anything to show for the drawing or the location reference?
 *
 * @param {...(string|Object|null|undefined)} featureCollections
 *
 * @return {boolean}
 */
function hasLocationFeatures (...featureCollections) {
  return Object.keys(mergeFeatureCollections(...featureCollections)).length > 0
}

/**
 * The statement form keeps the location reference as "x,y" string, while the backend returns it as FeatureCollection.
 *
 * @param {string|Object|null|undefined} locationReference
 *
 * @return {string} "x,y" of the first point or an empty string
 */
function getLocationReferenceCoordinates (locationReference) {
  const collection = parseFeatureCollection(locationReference)
  const point = collection?.features.find(feature => feature?.geometry?.type === 'Point')

  return point ? point.geometry.coordinates.join(',') : ''
}

/**
 * `location_is_set` of the statement form tells which value is the reason that a location is set.
 *
 * @param {string} priorityAreaKey
 * @param {string} locationPoint "x,y" of the marked place or an empty string
 * @param {string} polygon the drawing
 *
 * @return {string}
 */
function getLocationIsSet (priorityAreaKey, locationPoint, polygon) {
  if (priorityAreaKey.length > 0) {
    return 'priority_area'
  }

  return locationPoint !== '' && !polygon ? 'point' : 'geometry'
}

/**
 * The drawing and the marked place can both be set. Removing the drawing must not reset the marked place.
 * If nothing of the map reference is left after a map action, the statement is "not located" again.
 *
 * @param {Object} formData current data of the statement form
 * @param {Object} data data that the map wants to set
 *
 * @return {Object} the data to set
 */
function getConsistentLocationData (formData, data) {
  const location = { ...formData, ...data }
  const isAnythingLeft = location.r_location_geometry !== '' ||
    location.r_location_point !== '' ||
    location.r_location_priority_area_key !== ''

  if (location.r_location !== 'point' || isAnythingLeft) {
    return data
  }

  return { ...data, r_location: 'notLocated', location_is_set: '' }
}

export {
  getConsistentLocationData,
  getLocationIsSet,
  getLocationReferenceCoordinates,
  hasLocationFeatures,
  mergeFeatureCollections,
}
