/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for user_set_password.html.twig
 */

import { dpValidate } from '@demos-europe/demosplan-ui/src'
import { initialize } from '@DpJs/InitVue'
import RegisterFlyout from '@DpJs/components/user/RegisterFlyout'
import SetPassword from '@DpJs/components/user/portalUser/SetPassword'

const components = {
  RegisterFlyout,
  SetPassword
}

initialize(components).then(() => {
  dpValidate()
})
