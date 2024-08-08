/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { DpDataTable, DpIcon } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'
import RegisterFlyout from '@DpJs/components/user/RegisterFlyout'

const components = {
  DpDataTable,
  DpIcon,
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
