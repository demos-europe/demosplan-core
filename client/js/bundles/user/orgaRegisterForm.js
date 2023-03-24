/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for orga_register_form.html.twig
 */

import { DpRegisterFlyout, dpValidate } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'
import OrgaRegisterForm from '@DpJs/components/user/orgaRegisterForm/OrgaRegisterForm'

const components = {
  OrgaRegisterForm,
  DpRegisterFlyout
}

initialize(components).then(() => {
  dpValidate()
})
