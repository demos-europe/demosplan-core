/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_send_email.html.twig
 */

import { DpCheckbox, DpEditor, dpValidate } from '@demos-europe/demosplan-ui'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import { initialize } from '@DpJs/InitVue'

const components = { DpCheckbox, DpEditor }

const stores = {
  boilerplates: BoilerplatesStore
}

initialize(components, stores).then(() => {
  dpValidate()
})
