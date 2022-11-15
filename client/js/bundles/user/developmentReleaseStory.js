/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for development_release_story_threadentry_list.html.twig
 */

import { DpAccordion } from 'demosplan-ui/components/core'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import { VPopover } from 'demosplan-ui/directives'

const components = {
  DpAccordion,
  VPopover
}

initialize(components)
