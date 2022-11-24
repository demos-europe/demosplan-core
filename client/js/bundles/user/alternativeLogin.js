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

import { DpDataTableExtended, DpRegisterFlyout } from '@demos-europe/demosplan-ui'
import AlternativeLogin from '@DpJs/components/user/AlternativeLogin'
import dpValidate from '@demos-europe/demosplan-utils/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import SamlLoginForm from '@DpJs/components/user/samlLoginForm/SamlLoginForm'

debugger
console.log('qwe')

const components = {
  AlternativeLogin,
  DpDataTableExtended,
  DpRegisterFlyout,
  SamlLoginForm
}

initialize(components).then(() => {
  dpValidate()
})
