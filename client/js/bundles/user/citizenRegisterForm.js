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
import dpValidate from '@DpJs/lib/core/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import RegisterFlyout from '@DpJs/components/core/RegisterFlyout'

const components = {
  CitizenRegisterForm,
  RegisterFlyout
}

initialize(components).then(() => {
  dpValidate()
})
