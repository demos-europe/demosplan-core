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
import DpDataTableExtended from '@DpJs/components/core/DpDataTable/DpDataTableExtended'
import dpValidate from '@DpJs/lib/core/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import RegisterFlyout from '@DpJs/components/core/RegisterFlyout'
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
