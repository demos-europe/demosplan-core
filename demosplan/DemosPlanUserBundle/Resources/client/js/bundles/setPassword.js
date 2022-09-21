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

import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import RegisterFlyout from '@DemosPlanCoreBundle/components/RegisterFlyout'
import SetPassword from '@DemosPlanUserBundle/components/portalUser/SetPassword'

const components = {
  RegisterFlyout,
  SetPassword
}

initialize(components).then(() => {
  dpValidate()
})
