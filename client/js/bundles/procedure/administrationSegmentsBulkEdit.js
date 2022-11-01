/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_segments_bulk_edit.html.twig
 */

import { initialize } from '@DpJs/InitVue'
import SegmentsBulkEdit from '@DpJs/components/procedure/SegmentsBulkEdit/SegmentsBulkEdit'

const components = { SegmentsBulkEdit }
const stores = {}
const apiStores = ['tag', 'tagTopic']

initialize(components, stores, apiStores)
