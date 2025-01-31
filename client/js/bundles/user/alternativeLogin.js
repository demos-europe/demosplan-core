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

import AlternativeLogin from '@DpJs/components/user/AlternativeLogin'
import { DpDataTableExtended, dpValidate } from '@demos-europe/demosplan-ui'
import IdpLoginForm from '@DpJs/components/user/IdpLoginForm/IdpLoginForm'
import { initialize } from '@DpJs/InitVue'
import RegisterFlyout from '@DpJs/components/user/RegisterFlyout'

const components = {
  AlternativeLogin,
  DpDataTableExtended,
  RegisterFlyout,
  IdpLoginForm
}

initialize(components).then(() => {
  dpValidate()
})
