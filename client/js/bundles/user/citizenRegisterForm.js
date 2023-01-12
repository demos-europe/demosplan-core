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

import CitizenRegisterForm from '@DpJs/components/user/citizenRegisterForm/CitizenRegisterForm'
import dpValidate from '@demos-europe/demosplan-utils/lib/validation/dpValidate'
import { initialize } from '@DpJs/InitVue'
import { DpRegisterFlyout } from '@demos-europe/demosplan-ui'

const components = {
  CitizenRegisterForm,
  DpRegisterFlyout
}

initialize(components).then(() => {
  dpValidate()
})
