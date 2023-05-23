/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for alternative_login.html.twig
 */

import { DpDataTableExtended, DpRegisterFlyout, dpValidate } from '@demos-europe/demosplan-ui'
import AlternativeLogin from '@DpJs/components/user/AlternativeLogin'
import { initialize } from '@DpJs/InitVue'
import SamlLoginForm from '@DpJs/components/user/samlLoginForm/SamlLoginForm'

const components = {
  AlternativeLogin,
  DpDataTableExtended,
  DpRegisterFlyout,
  SamlLoginForm
}

initialize(components).then(() => {
  dpValidate()
})
