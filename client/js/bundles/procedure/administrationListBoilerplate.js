/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_list_boilerplate.html.twig
 */

import AnimateById from '@DpJs/lib/AnimateById'
import DpFlyout from '@DpJs/components/core/DpFlyout'
import DpSplitButton from '@DpJs/components/core/DpSplitButton'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { DpFlyout, DpSplitButton }

initialize(components).then(() => {
  AnimateById()
})
