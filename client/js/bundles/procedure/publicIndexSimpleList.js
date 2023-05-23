/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for publix_index.html.twig in planfestsh.
 */
import { dpApi } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'

initialize()
  .then(() => {
    const updateList = form => {
      const data = new FormData(form)

      return dpApi({
        method: 'post',
        url: Routing.generate('DemosPlan_procedure_public_list_json'),
        data: data,
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
      }).then(({ data }) => {
        if (data.code === 100 && data.success === true) {
          document.querySelector('[data-procedurelist-content]').innerHTML = data.responseHtml
        }
      })
    }
    const form = document.querySelector('#procedurelistForm')
    const selectElements = form.querySelectorAll('select')

    Array.from(selectElements).forEach((element) => {
      element.addEventListener('change', updateList.bind(this, form))
    })
  })
