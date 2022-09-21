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
import DpSlidebar from '@DemosPlanCoreBundle/components/DpSlidebar'
import DpVersionHistory from '@DemosPlanStatementBundle/components/statement/DpVersionHistory'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import SegmentFilterStore from '../store/SegmentFilter'
import SegmentsList from '@DemosPlanProcedureBundle/components/SegmentsList/SegmentsList'

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
