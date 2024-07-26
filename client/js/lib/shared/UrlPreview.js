/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { checkResponse, dpApi } from '@demos-europe/demosplan-ui'
import { v4 as uuid } from 'uuid'

/**
 * Update url preview
 */
export default function UrlPreview () {
  const slugInputs = document.querySelectorAll('[data-slug]')
  const shortUrlPreview = document.getElementById('shortUrl_preview')

  for (let i = 0; i < slugInputs.length; i++) {
    slugInputs[i].addEventListener('input', function (event) {
      const payload = {
        data: {
          attributes: {
            originalValue: event.target.value
          },
          type: 'slug-draft',
          id: uuid()
        }
      }

      const organisationId = event.target.getAttribute('data-organisation-id')
      const orgaPreview = document.getElementById(organisationId + ':urlPreview')

      return dpApi.post(Routing.generate('dp_api_slug_draft_create'), {}, payload)
        .then((response) => {
          if (orgaPreview) {
            const shortUrl = orgaPreview.getAttribute('data-shorturl')
            orgaPreview.textContent = shortUrl + response.data.data.attributes.slugifiedValue
          } else if (shortUrlPreview) {
            const shortUrl = shortUrlPreview.getAttribute('data-shorturl')
            shortUrlPreview.textContent = shortUrl + response.data.data.attributes.slugifiedValue
          }
        })
        .catch(error => checkResponse(error.response))
    })
  }
}
