/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_released_group.html.twig
 */

import { DpModal, DpUploadFiles } from '@demos-europe/demosplan-ui/src'
import DpMapModal from '@DpJs/components/statement/assessmentTable/DpMapModal'
import DpPublicDetailNoMap from '@DpJs/components/statement/DpPublicDetailNoMap'
import { initialize } from '@DpJs/InitVue'
import publicStatement from '@DpJs/store/statement/PublicStatement'
import StatementForm from '@DpJs/lib/statement/StatementForm'

const components = {
  DpMapModal,
  DpModal,
  DpPublicDetailNoMap,
  DpUploadFiles
}

const stores = {
  publicStatement
}

initialize(components, stores).then(() => {
  StatementForm()
})
