/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_statement_fragments_archive.html.twig
 */

import assessmentTableStore from '@DpJs/store/statement/AssessmentTable'
import DpFragmentList from '@DpJs/components/statement/fragmentList/DpFragmentList'
import DpFragmentListFilterModal from '@DpJs/components/statement/fragmentList/DpFragmentListFilterModal'
import fragmentStore from '@DpJs/store/statement/Fragment'
import { initialize } from '@DpJs/InitVue'
import ListStatementFragments from '@DpJs/lib/statement/ListStatementFragments'

const components = {
  DpFragmentList,
  DpFragmentListFilterModal
}

const stores = {
  assessmentTableStore,
  fragmentStore
}

initialize(components, stores).then(() => {
  ListStatementFragments()
})
