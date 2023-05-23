/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for citizen_register_form.html.twig
 */

import { DpRegisterFlyout, dpValidate } from '@demos-europe/demosplan-ui'
import CitizenRegisterForm from '@DpJs/components/user/citizenRegisterForm/CitizenRegisterForm'
import { initialize } from '@DpJs/InitVue'

const components = {
  CitizenRegisterForm,
  DpRegisterFlyout
}

initialize(components).then(() => {
  dpValidate()
})
