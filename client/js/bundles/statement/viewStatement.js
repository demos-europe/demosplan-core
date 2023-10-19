/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for view_statement.html.twig
 */

import AnimateById from '@DpJs/lib/shared/AnimateById'
import { DpAccordion } from '@demos-europe/demosplan-ui'
import HeightLimit from '@DpJs/components/statement/HeightLimit'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpAccordion,
  HeightLimit
}

initialize(components).then(() => {
  AnimateById()
})
