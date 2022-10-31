/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for map_admin_gislayer_list.html.twig
 */

import DpAdminLayerList from '@DpJs/components/map/admin/DpAdminLayerList'
import DpSplitButton from '@DpJs/components/core/DpSplitButton'
import DpUploadFiles from '@DpJs/components/core/DpUpload/DpUploadFiles'
import dpValidate from '@DpJs/lib/core/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import layers from '@DpJs/store/map/Layers'

const stores = { layers }
const components = {
  DpAdminLayerList,
  DpSplitButton,
  DpUploadFiles
}

initialize(components, stores).then(() => {
  dpValidate()
})
