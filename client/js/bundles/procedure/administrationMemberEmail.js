/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_member_email.html.twig
 */
import { DpAccordion, DpEditor, DpInlineNotification, DpLabel, dpValidate } from '@demos-europe/demosplan-ui/src'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import DpEmailList from '@DpJs/components/procedure/basicSettings/DpEmailList'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpAccordion,
  DpBoilerPlateModal,
  DpEditor,
  DpEmailList,
  DpInlineNotification,
  DpLabel
}

const stores = {
  boilerplates: BoilerplatesStore
}

initialize(components, stores).then(() => {
  dpValidate()
})
