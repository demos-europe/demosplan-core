/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

// Group key prefix (memberOf) of custom field conditions, followed by the field id
export const CUSTOM_FIELD_GROUP_PREFIX = 'customField_'

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
   * customField[fieldId][]=optionId
   *
   * Special handling for OR groups:
   * - IS NULL operators in OR groups use the `memberOf` value as the API filter key (with empty string)
   * - Regular values in OR groups are treated as ungrouped filters (path.id format)
   * - Custom field conditions are collected via their `memberOf` value (customField_<fieldId>)
   *   into the `customField[fieldId][]` shape that GET /api/3.0/StatementSegment expects:
   *   options of the same field are OR-combined, different fields AND-combined.
   *
   * @param {Object} edtFilters
   * @returns {Object}
   */
  const transformFiltersToApiPlatform = (edtFilters) => {
    const apiFilters = {}
    const groupedFilters = {}
    const customFieldFilters = {}

    Object.entries(edtFilters).forEach(([key, { condition, group } = {}]) => {
      if (group) {
        groupedFilters[key] = { conjunction: group.conjunction }
      }

      if (condition?.memberOf?.startsWith(CUSTOM_FIELD_GROUP_PREFIX)) {
        const fieldId = condition.memberOf.slice(CUSTOM_FIELD_GROUP_PREFIX.length)

        customFieldFilters[fieldId] = [...(customFieldFilters[fieldId] ?? []), condition.value]
      }
    })

    Object.values(edtFilters).forEach(({ condition } = {}) => {
      if (!condition) {
        return
      }

      if (condition.memberOf?.startsWith(CUSTOM_FIELD_GROUP_PREFIX)) {
        // Already collected above via the customField_<fieldId> memberOf
        return
      }

      const { path, value, operator, memberOf } = condition
      const isNull = operator === IS_NULL
      const isOrGroup = memberOf && groupedFilters[memberOf]?.conjunction === OR
      const isAssigneeFilterGroup = isOrGroup && memberOf === 'assigneeOrUnassigned'

      if (isAssigneeFilterGroup) {
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

    if (Object.keys(customFieldFilters).length > 0) {
      apiFilters.customField = customFieldFilters
    }

    return apiFilters
  }

  return {
    transformFiltersToApiPlatform,
  }
}
