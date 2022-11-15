/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for portal_user.html.twig
 */

import ChangePassword from '@DpJs/components/user/portalUser/ChangePassword'
import { DpAccordion } from 'demosplan-ui/components/core'
import dpValidate from '@DpJs/lib/core/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import PersonalData from '@DpJs/components/user/portalUser/PersonalData'

const components = {
  ChangePassword,
  DpAccordion,
  PersonalData
}

initialize(components).then(() => {
  dpValidate()
})
