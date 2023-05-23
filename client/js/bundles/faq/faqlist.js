/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for faqlist.html.twig
 */

import { DpRegisterFlyout } from '@demos-europe/demosplan-ui'
import { highlightActiveLinks } from '@DpJs/lib/core/libs'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpRegisterFlyout
}

initialize(components).then(() => {
  highlightActiveLinks('[data-highlight-current]')
})
