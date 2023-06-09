/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for orga_register_form.html.twig
 */

import { dpValidate } from '@demos-europe/demosplan-ui/src'
import { initialize } from '@DpJs/InitVue'
import OrgaRegisterForm from '@DpJs/components/user/orgaRegisterForm/OrgaRegisterForm'
import RegisterFlyout from '@DpJs/components/user/RegisterFlyout'

const components = {
  OrgaRegisterForm,
  RegisterFlyout
}

initialize(components).then(() => {
  dpValidate()
})
