/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_statement_segments_list.html.twig
 */
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import { hasPermission } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'
import procedureMapSettings from '@DpJs/store/map/ProcedureMapSettings'
import SegmentSlidebar from '@DpJs/store/procedure/SegmentSlidebar'
import SplitStatementStore from '@DpJs/store/statement/SplitStatementStore'
import StatementSegmentsList from '@DpJs/components/procedure/StatementSegmentsList/StatementSegmentsList'
import Voter from '@DpJs/store/statement/Voter'

const components = {
  StatementSegmentsList
}

const stores = {
  ProcedureMapSettings: procedureMapSettings,
  SegmentSlidebar,
  SplitStatement: SplitStatementStore
}

if (hasPermission('area_admin_boilerplates')) {
  stores.Boilerplates = BoilerplatesStore
}

const apiStores = [
  'AggregationFilterItems',
  'AssignableUser',
  'ElementsDetails',
  'Place',
  'SegmentComment',
  'Statement',
  'StatementSegment',
  'StatementVote',
  'Tags',
  'User'
]

initialize(components, stores, apiStores)
