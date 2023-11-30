/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_statement_fragments.html.twig
 */

import assessmentTableStore from '@DpJs/store/statement/AssessmentTable'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import DpFragmentList from '@DpJs/components/statement/fragmentList/DpFragmentList'
import DpFragmentListFilterModal from '@DpJs/components/statement/fragmentList/DpFragmentListFilterModal'
import fragmentStore from '@DpJs/store/statement/Fragment'
import { hasPermission } from '@demos-europe/demosplan-ui'
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

if (hasPermission('area_admin_boilerplates')) {
  stores.boilerplates = BoilerplatesStore
}

initialize(components, stores).then(() => {
  ListStatementFragments()
})
