/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This arbitrary number is due to the specification of the "standardized rendering pixel size"
 * to be 0.28 mm by the Open Geospatial Consortium (OGC 05-077r4). While this does not hold true
 * in today's crazy world of devices, tile servers stick to it, and so does OpenLayers.
 * - 25.4 mm = 1 inch
 * - 0.28 mm is assumed to be the physical size of a monitor pixel
 *   (see https://en.wikipedia.org/wiki/Dot_pitch)
 * @type {number}
 * @private
 */
const _DPI = 25.4 / 0.28

/**
 * Helper to calculate resolution
 * @type {{m: number, dd: number}}
 */
const _INCHES_PER_UNIT = {
  m: 39.37,
  dd: 4374754
}

/**
 * Translate scales into resolutions ready to drop into OpenLayers.
 * @param scales    Scales to translate
 * @param units     Meter or Decimal Degrees
 *
 */
export function getResolutionsFromScales (scales, units) {
  //  Drop non-numeric values
  scales.filter(scale => isFinite(scale))

  //  Scales have to be sorted ascending for OpenLayers to work
  scales.sort((a, b) => b - a)

  return scales.map(scale => getResolutionFromScale(scale, units))
}

/**
 * Translate resolutions into scales, return both in an array of objects.
 * @param resolutions   Resolutions to translate
 * @param units         Meter or Decimal Degrees
 *
 */
export function getScalesAndResolutions (resolutions, units) {
  return resolutions.map((resolution) => {
    return {
      resolution,
      scale: getScaleFromResolution(resolution, units)
    }
  }).sort((a, b) => b.resolution - a.resolution)
}

/**
 * Translate a given scale into a corresponding resolution.
 * @param scale     Scale to translate
 * @param units     Meter or Decimal Degrees
 * @return {number}
 */
export function getResolutionFromScale (scale, units) {
  return scale / (_INCHES_PER_UNIT[units] * _DPI)
}

/**
 * Translate a given resolution into a corresponding scale.
 * @param {number} resolution   Resolution to translate
 * @param {string} units        Meter or Decimal Degrees
 * @return {number} Scale
 */
export function getScaleFromResolution (resolution, units) {
  const scale = _INCHES_PER_UNIT[units] * _DPI * resolution
  return Math.round(scale)
}
