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

const components = {
  StatementSegmentsList
}

const stores = {
  procedureMapSettings,
  SegmentSlidebar,
  splitstatement: SplitStatementStore
}

if (hasPermission('area_admin_boilerplates')) {
  stores.boilerplates = BoilerplatesStore
}

const apiStores = [
  'aggregationFilterItems',
  'assignableUser',
  'place',
  'segmentComment',
  'statement',
  'statementSegment',
  'tags',
  'user'
]

initialize(components, stores, apiStores)
