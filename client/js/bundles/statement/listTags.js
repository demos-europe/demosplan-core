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

import { AnimateById } from '@demos-europe/demosplan-utils'
import { DpTooltipIcon, DpUploadFiles } from '@demos-europe/demosplan-ui/components/core'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpTooltipIcon,
  DpUploadFiles
}

initialize(components).then(() => {
  AnimateById()
})
