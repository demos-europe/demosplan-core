/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for addon_page.html.twig, a page that only consists of the addon components of one hook.
 */

import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import { initialize } from '@DpJs/InitVue'

const components = {
  AddonWrapper,
}

initialize(components)
