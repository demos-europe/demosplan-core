/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for assessment_table_view.html.twig
 */

import AssessmentTable from '@DpJs/lib/statement/AssessmentTable'
import AssessmentTableStore from '@DpJs/store/statement/AssessmentTable'
import AssessmentTableToc from '@DpJs/components/statement/assessmentTable/TocView/AssessmentTableToc'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import DpTable from '@DpJs/components/statement/assessmentTable/DpTable'
import FilterStore from '@DpJs/store/statement/Filter'
import FragmentStore from '@DpJs/store/statement/Fragment'
import { hasPermission } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'
import StatementStore from '@DpJs/store/statement/Statement'

const stores = {
  AssessmentTableStore,
  FilterStore,
  FragmentStore,
  StatementStore
}

const components = {
  AssessmentTableToc,
  DpTable
}

if (hasPermission('area_admin_boilerplates')) {
  stores.boilerplates = BoilerplatesStore
}

initialize(components, stores).then(AssessmentTable())
