/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Filter array of objects to hold unique values, using an object property.
 * @param array The array of objects that should be "de-duplicated"
 * @param key   The property in the objects, that should be unique
 * @return {*}
 */
export default function uniqueArrayByObjectKey (array, key) {
  const a = array.concat()
  for (let i = 0; i < a.length; ++i) {
    for (let j = i + 1; j < a.length; ++j) {
      if (a[i][key] === a[j][key]) {
        a.splice(j--, 1)
      }
    }
  }

  return a
}
