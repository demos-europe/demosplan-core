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

import DpUploadFiles from '@DpJs/components/core/DpUpload/DpUploadFiles'
import { dpValidate } from 'demosplan-utils/lib/validation'
import GisLayerEdit from '@DpJs/lib/map/GisLayerEdit'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import LayerSettings from '@DpJs/components/map/admin/LayerSettings'

const components = {
  DpUploadFiles,
  LayerSettings
}

initialize(components).then(() => {
  GisLayerEdit()
  dpValidate()
})
