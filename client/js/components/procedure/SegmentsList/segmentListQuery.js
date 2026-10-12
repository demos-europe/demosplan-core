/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import qs from 'qs'

/**
 * The order the segment list starts with, and the order the export always uses.
 */
export const DEFAULT_SEGMENT_ORDER = {
  'parentStatementOfSegment.submit': 'asc',
  'parentStatementOfSegment.externId': 'asc',
  orderInProcedure: 'asc',
}

/**
 * The filter and the search of the segment list as API Platform query parameters.
 * The list request and the export both use them, so they always ask for the same segments.
 *
 * @param {Object} params
 * @param {Object} params.filter Already transformed with transformFiltersToApiPlatform()
 * @param {string} params.procedureId
 * @param {string} params.searchTerm
 * @param {string[]} params.searchFields
 * @returns {Object}
 */
export function buildSegmentListQuery ({ filter, procedureId, searchTerm, searchFields }) {
  const query = {
    ...filter,
    'parentStatementOfSegment.procedure.id': procedureId,
  }

  if (searchTerm !== '') {
    query.search = {
      value: searchTerm,
      ...(searchFields.length !== 0 ? { fieldsToSearch: searchFields } : {}),
    }
  }

  return query
}

/**
 * The URL of the segment export: the list URL with `/export.<format>` and the query parameters,
 * written like the list request does (`tags.id[]=a&tags.id[]=b`).
 *
 * @param {Object} params
 * @param {string} params.url The URL of the segment list
 * @param {string} params.format `csv` or `xlsx`
 * @param {Object} params.params The query parameters
 * @returns {string}
 */
export function buildSegmentExportUrl ({ url, format, params }) {
  return `${url}/export.${format}?${qs.stringify(params, { arrayFormat: 'brackets' })}`
}
