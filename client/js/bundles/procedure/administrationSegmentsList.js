/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_segments_list.html.twig
 */
import DpSlidebar from '@DpJs/components/core/DpSlidebar'
import DpVersionHistory from '@DpJs/components/statement/statement/DpVersionHistory'
import { initialize } from '@DpJs/InitVue'
import SegmentFilterStore from '@DpJs/store/procedure/SegmentFilter'
import SegmentsList from '@DpJs/components/procedure/SegmentsList/SegmentsList'

const components = {
  SegmentsList,
  DpSlidebar,
  DpVersionHistory
}
const stores = {
  filter: SegmentFilterStore
}
const apiStores = ['assignableUser', 'place', 'statementSegment', 'tag', 'tagTopic']

initialize(components, stores, apiStores)
