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
import DpSegmentRecommendationEmail from '@DpJs/components/statement/statement/DpSegmentRecommendationEmail'
import DpVersionHistory from '@DpJs/components/statement/statement/DpVersionHistory'
import FilterFlyoutStore from '@DpJs/store/procedure/FilterFlyout'
import { initialize } from '@DpJs/InitVue'
import SegmentSlidebar from '@DpJs/components/procedure/SegmentsList/SegmentSlidebar'
import SegmentSlidebarStore from '@DpJs/store/procedure/SegmentSlidebar'
import SegmentsList from '@DpJs/components/procedure/SegmentsList/SegmentsList'

const components = {
  SegmentsList,
  SegmentSlidebar,
  DpSegmentRecommendationEmail,
  DpVersionHistory,
}
const stores = {
  FilterFlyout: FilterFlyoutStore,
  SegmentSlidebar: SegmentSlidebarStore,
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
