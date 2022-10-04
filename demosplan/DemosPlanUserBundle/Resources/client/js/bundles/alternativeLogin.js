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

import AlternativeLogin from '@DemosPlanUserBundle/components/AlternativeLogin'
import DpDataTableExtended from '@DpJs/components/core/DpDataTable/DpDataTableExtended'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import RegisterFlyout from '@DemosPlanCoreBundle/components/RegisterFlyout'
import SamlLoginForm from '@DemosPlanUserBundle/components/samlLoginForm/SamlLoginForm'

const components = {
  AlternativeLogin,
  DpDataTableExtended,
  RegisterFlyout,
  SamlLoginForm
}

initialize(components).then(() => {
  dpValidate()
})
