/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for map_admin_gislayer_global_new.html.twig
 */

import { DpUploadFiles, dpValidate } from '@demos-europe/demosplan-ui'
import GisLayerEdit from '@DpJs/lib/map/GisLayerEdit'
import { initialize } from '@DpJs/InitVue'
import LayerSettings from '@DpJs/components/map/admin/LayerSettings'

const components = {
  DpUploadFiles,
  LayerSettings
}

initialize(components).then(() => {
  GisLayerEdit()
  dpValidate()
})
