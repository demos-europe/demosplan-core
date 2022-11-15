/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for view_statement.html.twig
 */

import AnimateById from '@DpJs/lib/core/AnimateById'
import { DpAccordion, DpHeightLimit } from 'demosplan-ui/components/core'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpAccordion,
  DpHeightLimit
}

initialize(components).then(() => {
  AnimateById()
})
