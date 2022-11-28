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

import dpValidate from '@demos-europe/demosplan-utils/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import OrgaRegisterForm from '@DpJs/components/user/orgaRegisterForm/OrgaRegisterForm'
import { DpRegisterFlyout } from '@demos-europe/demosplan-ui'

const components = {
  OrgaRegisterForm,
  DpRegisterFlyout
}

initialize(components).then(() => {
  dpValidate()
})
