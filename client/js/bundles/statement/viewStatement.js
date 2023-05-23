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

import { DpAccordion, DpHeightLimit } from '@demos-europe/demosplan-ui'
import AnimateById from '@DpJs/lib/shared/AnimateById'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpAccordion,
  DpHeightLimit
}

initialize(components).then(() => {
  AnimateById()
})
