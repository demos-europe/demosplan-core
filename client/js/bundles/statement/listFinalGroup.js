/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_final_group.html.twig
 */

import DpMapModal from '@DpJs/components/statement/assessmentTable/DpMapModal'
import DpPublicStatementList from '@DpJs/components/statement/publicStatementLists/DpPublicStatementList'
import { initialize } from '@DpJs/InitVue'
import publicStatement from '@DpJs/store/statement/PublicStatement'
import Tabs from '@DpJs/lib/statement/Tabs'

const components = {
  DpMapModal,
  DpPublicStatementList
}

const stores = {
  publicStatement
}

initialize(components, stores).then(() => {
  Tabs()
})
