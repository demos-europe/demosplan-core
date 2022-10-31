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

import dpValidate from '@DpJs/lib/core/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import RegisterFlyout from '@DpJs/components/core/RegisterFlyout'
import SetPassword from '@DpJs/components/user/portalUser/SetPassword'

const components = {
  RegisterFlyout,
  SetPassword
}

initialize(components).then(() => {
  dpValidate()
})
