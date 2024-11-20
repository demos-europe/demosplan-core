/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_new.html.twig
 */

import CreateProcedure from '@DpJs/lib/procedure/CreateProcedure'
import DpNewProcedure from '@DpJs/components/procedure/admin/DpNewProcedure/DpNewProcedure'
import { initialize } from '@DpJs/InitVue'
import NewProcedure from '@DpJs/store/procedure/NewProcedure'

const components = { DpNewProcedure }
const stores = {
  NewProcedure
}

initialize(components, stores)
  .then(() => {
    // Prevent multiple form submits
    document.addEventListener('customValidationPassed', (e) => {
      const form = e.detail.form
      try {
        form.querySelector('[type="submit"]').setAttribute('disabled', 'disabled')
      } catch (e) {
        form.querySelector('[type="submit"]').removeAttribute('disabled')
      }
    })

    if (hasPermission('feature_use_plis')) {
      CreateProcedure()
    }
  })
