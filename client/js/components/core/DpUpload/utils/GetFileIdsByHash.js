/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { dpApi } from 'demosplan-utils'

/**
 * Fetch File-Ids by their hashes
 *
 * @param {Array<hashes>}
 *
 * @returns {Array<Ids>}
 */
function getFileIdsByHash (hashes) {
  return dpApi.get(
    Routing.generate('api_resource_list', { resourceType: 'File' }),
    {
      filter: {
        hasHash: {
          condition: {
            operator: 'IN',
            path: 'hash',
            value: hashes
          }
        }
      }
    },
    {
      serialize: true
    }
  )
    .then(({ data }) => {
      return data.data.map(el => el.id)
    })
}

export { getFileIdsByHash }
