/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for news_admin_new.html.twig
 */

import { DpChangeStateAtDate, DpEditor, DpLabel, DpUploadFiles, dpValidate } from '@demos-europe/demosplan-ui'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import { initialize } from '@DpJs/InitVue'
import newsAdminInit from '@DpJs/lib/news/newsAdmin'

const components = {
  DpBoilerPlateModal,
  DpChangeStateAtDate,
  DpEditor,
  DpLabel,
  DpUploadFiles
}

const stores = {
  boilerplates: BoilerplatesStore
}

initialize(components, stores).then(() => {
  dpValidate()
  newsAdminInit()
})
