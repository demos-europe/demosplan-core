/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_list_boilerplate.html.twig
 */

import { DpFlyout, DpSplitButton } from '@demos-europe/demosplan-ui/src'
import AnimateById from '@DpJs/lib/shared/AnimateById'
import { initialize } from '@DpJs/InitVue'

const components = { DpFlyout, DpSplitButton }

initialize(components).then(() => {
  AnimateById()
})
