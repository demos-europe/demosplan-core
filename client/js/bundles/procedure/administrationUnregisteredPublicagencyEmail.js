/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_unregistered_publicagency_email.html.twig
 */
import { DpAccordion, DpEditor, DpLabel, dpValidate, hasPermission } from '@demos-europe/demosplan-ui'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpAccordion,
  DpEditor,
  DpLabel
}

const stores = {}

if (hasPermission('area_admin_boilerplates')) {
  stores.boilerplates = BoilerplatesStore
  components.DpBoilerPlateModal = DpBoilerPlateModal
}

initialize(components, stores).then(() => {
  dpValidate()
})
