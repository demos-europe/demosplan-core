/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 *
 * @param searchterm String | is the term that should be searched
 * @param items Array | is an array of objects that should be searched
 * @param fields Array | properties of the objects that should be included in the search.
 *  deep property access is possible (e.g. [ 'attributes.text' ])
 * @returns Array
 *
 */
export default function dataTableSearch (searchterm, items, fields) {
  let foundItems = items
  if (searchterm.length > 0) {
    // Run some replacements on the searchterm to avoid regex issues
    const regexSpecialChars = /(\[|\]|\\|\^|\$|\.|\||\?|\*|\+|\(|\)|\.)/ig
    let preparedSearchTerm = searchterm.replace(regexSpecialChars, '\\$1')
    preparedSearchTerm = preparedSearchTerm.replace(/\s+/ig, '\\s+')

    const searchRegex = new RegExp(preparedSearchTerm, 'ig')
    foundItems = items.filter(item => {
      return fields.filter(field => {
        const path = field.split('.')
        let nestedProp = item[path[0]]
        let doesPropExist = !!nestedProp
        let i = 1
        while (doesPropExist && i < path.length) {
          nestedProp = nestedProp[path[i]]
          doesPropExist = !!nestedProp
          i++
        }
        return typeof nestedProp === 'string' && nestedProp.match(searchRegex)
      }).length > 0
    })
  }
  return foundItems
}
