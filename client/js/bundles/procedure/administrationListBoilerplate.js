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

import { AnimateById } from 'demosplan-utils'
import { DpFlyout, DpSplitButton } from 'demosplan-ui/components/core'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { DpFlyout, DpSplitButton }

initialize(components).then(() => {
  AnimateById()
})
