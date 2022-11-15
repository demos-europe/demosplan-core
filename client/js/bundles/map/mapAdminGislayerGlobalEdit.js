/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for mmap_admin_gislayer_global_edit.html.twig
 */

import { DpUploadFiles } from '@demos-europe/demosplan-ui/components/core'
import GisLayerEdit from '@DpJs/lib/map/GisLayerEdit'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpUploadFiles
}

initialize(components).then(() => {
  GisLayerEdit()
})
