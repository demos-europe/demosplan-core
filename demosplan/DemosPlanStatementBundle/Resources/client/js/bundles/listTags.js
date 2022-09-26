/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_tags.html.twig
 */

import AnimateById from '@DpJs/lib/AnimateById'
import DpTooltipIcon from '@DemosPlanCoreBundle/components/DpTooltipIcon'
import DpUploadFiles from '@DpJs/components/core/DpUpload/DpUploadFiles'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpTooltipIcon,
  DpUploadFiles
}

initialize(components).then(() => {
  AnimateById()
})
