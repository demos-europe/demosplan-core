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
import { DpLabel, dpValidate, hasPermission } from '@demos-europe/demosplan-ui'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpLabel,
  DpEditor: async () => {
    const { DpEditor } = await import('@demos-europe/demosplan-ui')
    return DpEditor
  }
}

const stores = {}

if (hasPermission('area_admin_boilerplates')) {
  stores.boilerplates = BoilerplatesStore
  components.DpBoilerPlateModal = DpBoilerPlateModal
}

initialize(components, stores).then(() => {
  dpValidate()
})
