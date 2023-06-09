/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for customer_settings_update_mail.html.twig
 */
import { DpLabel, dpValidate } from '@demos-europe/demosplan-ui/src'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpBoilerPlateModal,
  DpLabel,
  DpEditor: async () => {
    const { DpEditor } = await import('@demos-europe/demosplan-ui/src')
    return DpEditor
  }
}

const stores = {
  boilerplates: BoilerplatesStore
}

initialize(components, stores).then(() => {
  dpValidate()
})
