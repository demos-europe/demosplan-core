/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for administration_edit.html.twig
 */

import { DpCheckbox, DpDateRangePicker, dpValidate } from '@demos-europe/demosplan-ui'
import AdministrationMaster from '@DpJs/lib/procedure/AdministrationMaster'
import DpBasicSettings from '@DpJs/components/procedure/basicSettings/DpBasicSettings'
// Import this separately because Planfest has a separate twig template which does not use DpBasicSettings
import DpEmailList from '@DpJs/components/procedure/basicSettings/DpEmailList'
import DPWizard from '@DpJs/lib/procedure/DPWizard'
import { initialize } from '@DpJs/InitVue'
import UrlPreview from '@DpJs/lib/shared/UrlPreview'

const components = { DpBasicSettings, DpCheckbox, DpDateRangePicker, DpEmailList }

initialize(components).then(() => {
  UrlPreview()
  DPWizard()
  AdministrationMaster()
  dpValidate()

  document.addEventListener('customValidationPassed', (e) => {
    const form = e.detail.form
    if (form.getAttribute('data-dp-validate') === 'configForm') {
      try {
        form.querySelector('[type="submit"]').setAttribute('disabled', 'disabled')
      } catch (e) {
        form.querySelector('[type="submit"]').removeAttribute('disabled')
      }
    }
  })
})
