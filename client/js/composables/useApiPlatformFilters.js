/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Composable for transforming EDT-style filters to API Platform 3.0 format.
 */
export function useApiPlatformFilters () {
  /**
   * Transforms EDT-style filters to API Platform 3.0 format.
   * EDT: filter[uuid][condition][path/value/operator]
   * AP3: path.id[]=uuid or exists[path]=false for IS NULL
   *
   * @param {Object} edtFilters - EDT 2.0 filter object
   * @returns {Object} - API Platform 3.0 filter object
   */
  const transformFiltersToApiPlatform = (edtFilters) => {
    const apiFilters = {}
    const unassigned = 'IS NULL'

    Object.values(edtFilters).forEach(({ condition } = {}) => {
      if (!condition) {
        return
      }

      const { path, value, operator } = condition

      if (operator === unassigned) {
        apiFilters[`exists[${path}]`] = false

        return
      }

      const key = `${path}.id`

      // Safely append value to array, initializing array if key doesn't exist
      apiFilters[key] = [
        ...(apiFilters[key] ?? []),
        value
      ]
    })

    return apiFilters
  }

  return {
    transformFiltersToApiPlatform
  }
}
