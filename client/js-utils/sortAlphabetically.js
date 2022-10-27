/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import hasOwnProp from './hasOwnProp'

/**
 * Sort array of strings or objects alphabetically with german locale.
 * @param array {Array<String>|Array<Object>} Array to sort
 * @param sortBy {String} Property (or dot-separated chain of properties) of an object to use for sorting. Required only for array of objects.
 * @param direction {('asc'|'desc')} Sorting direction, can be asc or desc. Default is asc.
 */

export default function sortAlphabetically (array, sortBy, direction = 'asc') {
  const sortedArray = array
  // Is it an array of object or strings?
  if (typeof sortedArray[0] === 'string') {
    sortedArray.sort((a, b) => a.localeCompare(b, 'de', { sensitivity: 'base' }))
  } else if (typeof array[0] === 'object') {
    sortedArray.sort((a, b) => {
      const sortProperties = sortBy.split('.')
      let sortPropertyA = a
      let sortPropertyB = b

      for (const prop of sortProperties) {
        if (hasOwnProp(sortPropertyA, prop) && hasOwnProp(sortPropertyB, prop)) {
          sortPropertyA = sortPropertyA[prop]
          sortPropertyB = sortPropertyB[prop]
        }
      }
      return sortPropertyA.localeCompare(sortPropertyB, 'de', { sensitivity: 'base' })
    })
  }

  if (direction === 'desc') {
    sortedArray.reverse()
  }

  return sortedArray
}
