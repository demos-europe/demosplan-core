/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for user_set_password.html.twig
 */

import dpValidate from '@demos-europe/demosplan-utils/lib/validation/dpValidate'
import { initialize } from '@DpJs/InitVue'
import { DpRegisterFlyout } from '@demos-europe/demosplan-ui'
import SetPassword from '@DpJs/components/user/portalUser/SetPassword'

const components = {
  DpRegisterFlyout,
  SetPassword
}

initialize(components).then(() => {
  dpValidate()
})
