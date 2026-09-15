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
  const IS_NULL = 'IS NULL'
  const OR = 'OR'

  /**
   * Transforms EDT-style filters to API Platform 3.0 format.
   *
   * EDT:
   * filter[uuid][condition][path/value/operator]
   *
   * API Platform 3:
   * path.id[]=uuid
   * exists[path]=false
   *
   * Special handling for OR groups:
   * - IS NULL operators in OR groups use the `memberOf` value as the API filter key (with empty string)
   * - Regular values in OR groups are treated as ungrouped filters (path.id format)
   *
   * @param {Object} edtFilters
   * @returns {Object}
   */
  const transformFiltersToApiPlatform = (edtFilters) => {
    const apiFilters = {}
    const groupedFilters = {}

    Object.entries(edtFilters).forEach(([key, { condition, group } = {}]) => {
      if (group) {
        groupedFilters[key] = { conjunction: group.conjunction }
      }
    })

    Object.values(edtFilters).forEach(({ condition } = {}) => {
      if (!condition) {
        return
      }

      const { path, value, operator, memberOf } = condition
      const isNull = operator === IS_NULL
      const isOrGroup = memberOf && groupedFilters[memberOf]?.conjunction === OR

      if (isOrGroup && isNull) {
        apiFilters[memberOf] ??= []
        apiFilters[memberOf].push(isNull ? '' : value)

        return
      }

      if (isNull) {
        apiFilters[`exists[${path}]`] = false

        return
      }

      // Handle ungrouped value filters
      const key = `${path}.id`

      apiFilters[key] = [
        ...(apiFilters[key] ?? []),
        value,
      ]
    })

    return apiFilters
  }

  return {
    transformFiltersToApiPlatform,
  }
}
