/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for password_recover.html.twig
 */

import dpValidate from '@DpJs/lib/core/validation/dpValidate'
import { initialize } from '@DpJs/InitVue'
import RegisterFlyout from '@DpJs/components/core/RegisterFlyout'

const components = {
  RegisterFlyout
}

initialize(components).then(() => {
  dpValidate()
})
