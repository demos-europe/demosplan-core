/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { initialize } from '@DemosPlanCoreBundle/InitVue'
import RegisterFlyout from '@DemosPlanCoreBundle/components/RegisterFlyout'

const components = {
  RegisterFlyout
}

initialize(components).then(() => {
  const consentHook = document.querySelector('[data-change-cookie-consent]')
  if (consentHook) {
    consentHook.addEventListener('click', () => {
      window.dplan.consent.adjustSettings()
    })
  }
})
