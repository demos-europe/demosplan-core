/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for news_admin_edit.html.twig
 */

import { DpCheckbox, DpEditor, DpLabel, DpUploadFiles, dpValidate, hasPermission } from '@demos-europe/demosplan-ui'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import ChangeStateAtDate from '@DpJs/components/news/ChangeStateAtDate'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import { initialize } from '@DpJs/InitVue'
import newsAdminInit from '@DpJs/lib/news/newsAdmin'

const components = {
  ChangeStateAtDate,
  DpCheckbox,
  DpEditor,
  DpLabel,
  DpUploadFiles
}

const stores = {}

if (hasPermission('area_admin_boilerplates')) {
  stores.boilerplates = BoilerplatesStore
  components.DpBoilerPlateModal = DpBoilerPlateModal
}

initialize(components, stores).then(() => {
  dpValidate()
  newsAdminInit()
})
