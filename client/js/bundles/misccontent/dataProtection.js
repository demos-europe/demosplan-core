/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { initialize } from '@DpJs/InitVue'
import RegisterFlyout from '@DpJs/components/user/RegisterFlyout'

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
