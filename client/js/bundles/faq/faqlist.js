/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for faqlist.html.twig
 */

import { highlightActiveLinks } from 'demosplan-utils/lib/HighlightHashLink'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import { DpRegisterFlyout } from 'demosplan-ui/components/core'

const components = {
  RegisterFlyout
}

initialize(components).then(() => {
  highlightActiveLinks('[data-highlight-current]')
})
