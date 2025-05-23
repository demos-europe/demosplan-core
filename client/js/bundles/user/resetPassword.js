/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for password_recover.html.twig
 */

import { dpValidate } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'
import RegisterFlyout from '@DpJs/components/user/RegisterFlyout'

const components = {
  RegisterFlyout
}

initialize(components).then(() => {
  dpValidate()
})
