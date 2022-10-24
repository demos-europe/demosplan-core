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

import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import OrgaRegisterForm from '@DpJs/components/user/orgaRegisterForm/OrgaRegisterForm'
import RegisterFlyout from '@DpJs/components/core/RegisterFlyout'

const components = {
  OrgaRegisterForm,
  RegisterFlyout
}

initialize(components).then(() => {
  dpValidate()
})
