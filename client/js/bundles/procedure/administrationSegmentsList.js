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

const components = {
  SegmentsList,
  DpSlidebar,
  DpVersionHistory,
}
const stores = {
  FilterFlyout: FilterFlyoutStore,
}
const apiStores = [
  'AssignableUser',
  'AdminProcedure',
  'CustomField',
  'Place',
  'StatementSegment',
  'Tag',
  'TagTopic',
]

// For this page, slidebar control will be set up via provide/inject
// Create a control object that will be populated when slidebar mounts
const slidebarControl = {
  instance: null,
  show() {
    this.instance?.showSlidebar()
  },
  hide() {
    this.instance?.hideSlidebar()
  },
}

const provides = {
  slidebarControl,
}

initialize(components, stores, apiStores, {}, provides)
