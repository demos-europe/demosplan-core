/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Shorthand for Object.prototype.hasOwnProperty.call
 * @param obj
 * @param prop
 * @return {boolean}
 */
export default function hasOwnProp (obj, prop) {
  if (typeof obj !== 'object') {
    console.warn('Cannot check for property on a non-object, got type: ' + typeof obj)

    return false
  }

  if (obj === null) {
    return false
  }

  return Object.prototype.hasOwnProperty.call(obj, prop)
}
