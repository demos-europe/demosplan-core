/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_segments_bulk_edit.html.twig
 */
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import { hasPermission } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'
import SegmentsBulkEdit from '@DpJs/components/procedure/SegmentsBulkEdit/SegmentsBulkEdit'

const components = { SegmentsBulkEdit }
const stores = {}
const apiStores = ['Tag', 'TagTopic']

if (hasPermission('area_admin_boilerplates')) {
  stores.boilerplates = BoilerplatesStore
}

initialize(components, stores, apiStores)
