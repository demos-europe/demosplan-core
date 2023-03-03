/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for customer_settings_update_mail.html.twig
 */
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import { dpValidate } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpEditor: async () => {
    const { DpEditor } = await import('@demos-europe/demosplan-ui')
    return DpEditor
  }
}

const stores = {
  boilerplates: BoilerplatesStore
}

initialize(components, stores).then(() => {
  dpValidate()
})
