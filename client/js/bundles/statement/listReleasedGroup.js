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

import { DpButton, DpModal, DpSelect, DpUploadFiles } from '@demos-europe/demosplan-ui'
import DpMapModal from '@DpJs/components/statement/assessmentTable/DpMapModal'
import DpPublicDetailNoMap from '@DpJs/components/statement/DpPublicDetailNoMap'
import DpPublicStatementList from '@DpJs/components/statement/publicStatementLists/DpPublicStatementList'
import { initialize } from '@DpJs/InitVue'
import publicStatement from '@DpJs/store/statement/PublicStatement'
import StatementForm from '@DpJs/lib/statement/StatementForm'
import StatementModal from '@DpJs/components/statement/publicStatementModal/StatementModal'

/*
 * DpPublicDetailNoMap renders the Twig markup as scoped slot content, which is compiled in the
 * scope of this app - so everything the template uses has to be registered here, not on the component
 */
const components = {
  DpButton,
  DpMapModal,
  DpModal,
  DpPublicDetailNoMap,
  DpPublicStatementList,
  DpSelect,
  DpUploadFiles,
  StatementModal,
}

const stores = {
  publicStatement,
}

initialize(components, stores).then(() => {
  StatementForm()
})
