/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { dpApi } from '@DemosPlanCoreBundle/plugins/DpApi'

function makeFormPost (payload, url) {
  const postData = new FormData()

  for (const [key, value] of Object.entries(payload)) {
    if (Array.isArray(value)) {
      value.forEach(el => postData.append(key + '[]', el))
    } else {
      postData.append(key, value)
    }
  }

  return dpApi({
    method: 'post',
    url: url,
    data: postData,
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}

export { makeFormPost }
