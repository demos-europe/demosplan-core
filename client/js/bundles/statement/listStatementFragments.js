/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_statement_fragments.html.twig
 */

import assessmentTableStore from '@DemosPlanStatementBundle/store/AssessmentTable'
import DpFragmentList from '@DpJs/components/statement/fragmentList/DpFragmentList'
import DpFragmentListFilterModal from '@DpJs/components/statement/fragmentList/DpFragmentListFilterModal'
import fragmentStore from '@DemosPlanStatementBundle/store/Fragment'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import ListStatementFragments from '@DemosPlanStatementBundle/lib/ListStatementFragments'

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
