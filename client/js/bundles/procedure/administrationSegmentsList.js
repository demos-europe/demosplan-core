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
import BookmarkStore from '@DpJs/store/procedure/SegmentsBookmarks'
import { DpButton } from '@demos-europe/demosplan-ui'
import DpSegmentRecommendationEmail from '@DpJs/components/statement/statement/DpSegmentRecommendationEmail.vue'
import DpVersionHistory from '@DpJs/components/statement/statement/DpVersionHistory.vue'
import FilterFlyoutStore from '@DpJs/store/procedure/FilterFlyout'
import { initialize } from '@DpJs/InitVue'
import SegmentsBookmarks from '@DpJs/components/procedure/SegmentsList/SegmentsBookmarks.vue'
import SegmentsBookmarksButton from '@DpJs/components/procedure/SegmentsList/SegmentsBookmarksButton.vue'
import SegmentsList from '@DpJs/components/procedure/SegmentsList/SegmentsList.vue'
import SegmentsListFilter from '@DpJs/components/procedure/SegmentsList/SegmentsListFilter.vue'
import SegmentSlidebar from '@DpJs/components/procedure/SegmentsList/SegmentSlidebar.vue'
import SegmentSlidebarStore from '@DpJs/store/procedure/SegmentSlidebar'

const components = {
  DpButton,
  DpSegmentRecommendationEmail,
  DpVersionHistory,
  SegmentsBookmarks,
  SegmentsBookmarksButton,
  SegmentsList,
  SegmentsListFilter,
  SegmentSlidebar,
}
const stores = {
  Bookmark: BookmarkStore,
  FilterFlyout: FilterFlyoutStore,
  SegmentSlidebar: SegmentSlidebarStore,
}
const apiStores = [
  'AssignableUser',
  'Bookmark',
  'Place',
  'RecommendationVersion',
  'Statement',
  'StatementSegment',
  'Tag',
  'TagTopic',
]

/**
 * Use API Platform 3.0 for StatementSegment on this page
 */
const pageSpecificApi3Modules = {
  StatementSegment: [
    'list',
    'get',
  ],
}

initialize(components, stores, apiStores, {}, () => {}, pageSpecificApi3Modules)
