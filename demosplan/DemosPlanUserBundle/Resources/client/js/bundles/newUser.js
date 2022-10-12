/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for gateway_newUser.html.twig
 */
import { dpApi } from '@DemosPlanCoreBundle/plugins/DpApi'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {}

initialize(components).then(() => {
  const logoutButton = document.querySelector('[data-post-logout]')

  if (logoutButton) {
    logoutButton.addEventListener('click', function (event) {
      return dpApi.post(Routing.generate('DemosPlan_user_logout'), new FormData())
        .then(response => {
          window.location.href = Routing.generate('core_home')
        })
    })
  }

  dpValidate()
})
