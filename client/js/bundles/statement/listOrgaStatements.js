/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_orga_statements.html.twig
 */

import { DpButton } from '@demos-europe/demosplan-ui/components'
import { DpInlineNotification } from '@demos-europe/demosplan-ui/components/core'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpButton,
  DpInlineNotification
}

initialize(components, {}, [])
