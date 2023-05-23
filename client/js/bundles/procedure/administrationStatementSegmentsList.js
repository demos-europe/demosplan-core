/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
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
import { initialize } from '@DpJs/InitVue'
import SegmentSlidebar from '@DpJs/store/procedure/SegmentSlidebar'
import SplitStatementStore from '@DpJs/store/procedure/SplitStatementStore'
import StatementSegmentsList from '@DpJs/components/procedure/StatementSegmentsList/StatementSegmentsList'

const components = {
  StatementSegmentsList
}

const stores = {
  boilerplates: BoilerplatesStore,
  SegmentSlidebar,
  splitstatement: SplitStatementStore
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
