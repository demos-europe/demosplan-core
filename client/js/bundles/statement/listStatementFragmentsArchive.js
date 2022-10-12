/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_statement_fragments_archive.html.twig
 */

import assessmentTableStore from './../store/AssessmentTable'
import DpFragmentList from './../components/fragmentList/DpFragmentList'
import DpFragmentListFilterModal from './../components/fragmentList/DpFragmentListFilterModal'
import fragmentStore from './../store/Fragment'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import ListStatementFragments from './../lib/ListStatementFragments'

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
