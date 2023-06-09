/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_send_email.html.twig
 */

import { DpCheckbox, DpEditor, DpLabel, dpValidate } from '@demos-europe/demosplan-ui/src'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import { initialize } from '@DpJs/InitVue'

const components = { DpBoilerPlateModal, DpCheckbox, DpEditor, DpLabel }

const stores = {
  boilerplates: BoilerplatesStore
}

initialize(components, stores).then(() => {
  dpValidate()
})
