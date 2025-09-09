/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for map_admin_gislayer_list.html.twig
 */

import { DpSplitButton, DpUploadFiles, dpValidate } from '@demos-europe/demosplan-ui'
import AdminLayerList from '@DpJs/components/map/admin/AdminLayerList'
import { initialize } from '@DpJs/InitVue'
import layers from '@DpJs/store/map/Layers'

const stores = { layers }
const components = {
  AdminLayerList,
  DpSplitButton,
  DpUploadFiles
}

initialize(components, stores).then(() => {
  dpValidate()
})
