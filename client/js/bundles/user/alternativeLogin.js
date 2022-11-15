/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for alternative_login.html.twig
 */

import AlternativeLogin from '@DpJs/components/user/AlternativeLogin'
import { DpDataTableExtended, DpRegisterFlyout } from 'demosplan-ui/components/core'
import { dpValidate } from 'demosplan-utils/lib/validation'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import SamlLoginForm from '@DpJs/components/user/samlLoginForm/SamlLoginForm'

const components = {
  AlternativeLogin,
  DpDataTableExtended,
  RegisterFlyout,
  SamlLoginForm
}

initialize(components).then(() => {
  dpValidate()
})
