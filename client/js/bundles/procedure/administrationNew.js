/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
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
import { DpDateRangePicker } from 'demosplan-ui/components/core'
import DpNewProcedure from '@DpJs/components/procedure/admin/DpNewProcedure/DpNewProcedure'
import dpValidate from '@DpJs/lib/core/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { DpNewProcedure, DpDateRangePicker }

initialize(components)
  .then(() => {
    dpValidate()

    // Prevent multiple form submits
    document.addEventListener('customValidationPassed', (e) => {
      const form = e.detail.form
      try {
        form.querySelector('[type="submit"]').setAttribute('disabled', 'disabled')
      } catch (e) {
        form.querySelector('[type="submit"]').removeAttribute('disabled')
      }
    })

    if (PROJECT === 'bobhh') {
      CreateProcedure()
    }
  })
