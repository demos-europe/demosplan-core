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

import DpFaqSupport from '@DpJs/components/faq/DpFaqSupport'
import RegisterFlyout from '@DpJs/components/user/RegisterFlyout'
import { highlightActiveLinks } from '@DpJs/lib/core/libs'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpFaqSupport,
  RegisterFlyout
}

const apiStores = ['customerContact']

initialize(components, {}, apiStores).then(() => {
  highlightActiveLinks('[data-highlight-current]')
})
