/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for public_index.html.twig
 * where the Map is on the left hand side and the Procedurelist on the right.
 */
import { dpApi, prefixClass } from '@demos-europe/demosplan-ui'
import DpSearchProcedureMap from '@DpJs/components/procedure/publicindex/DpSearchProcedureMap'
import { initialize } from '@DpJs/InitVue'

const stores = {}
const components = {
  DpSearchProcedureMap
}
window.prefixClass = prefixClass

initialize(components, stores).then(() => {
  if (hasPermission('feature_procedures_mark_participated')) {
    const checkboxes = document.querySelectorAll('[data-done-procedure-id]')
    //  Update classes on items onLoad just in case the user hit History.back
    Array.from(checkboxes).forEach(checkbox => {
      if (checkbox.checked) {
        checkbox.closest(prefixClass('.c-procedurelist__item')).classList.add(prefixClass('is-done'))
      } else {
        checkbox.closest(prefixClass('.c-procedurelist__item')).classList.remove(prefixClass('is-done'))
      }
    })

    document.querySelector('[data-procedurelist-content]').addEventListener('change', e => {
      if (e.target.hasAttribute('data-done-procedure-id') === false) return
      const checkbox = e.target
      const isChecked = checkbox.checked
      const procedureId = checkbox.getAttribute('data-done-procedure-id')
      const route = isChecked ? 'dp_api_procedure_mark_participated' : 'dp_api_procedure_unmark_participated'

      dpApi({
        method: 'POST',
        url: Routing.generate(route, { procedureId }),
        data: { procedureId }
      })
        .then(() => {
          checkbox.closest(prefixClass('.c-procedurelist__item')).classList.toggle(prefixClass('is-done'))
        })
        .catch(() => {
          checkbox.checked = !isChecked
        })
    })
  }
})
