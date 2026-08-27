/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_segments_list.html.twig
 */
import { DpSlidebar } from '@demos-europe/demosplan-ui'
import DpVersionHistory from '@DpJs/components/statement/statement/DpVersionHistory'
import FilterFlyoutStore from '@DpJs/store/procedure/FilterFlyout'
import { initialize } from '@DpJs/InitVue'
import SegmentsList from '@DpJs/components/procedure/SegmentsList/SegmentsList'
import SegmentsListFilter from '@DpJs/components/procedure/SegmentsList/SegmentsListFilter'
import SegmentsListFilterStore from '@DpJs/store/procedure/SegmentsListFilter'

const components = {
  SegmentsList,
  SegmentsListFilter,
  DpSlidebar,
  DpVersionHistory,
}
const stores = {
  FilterFlyout: FilterFlyoutStore,
  SegmentsListFilter: SegmentsListFilterStore,
}
const apiStores = [
  'AssignableUser',
  'Place',
  'RecommendationVersion',
  'StatementSegment',
  'Tag',
  'TagTopic',
]

initialize(components, stores, apiStores)
