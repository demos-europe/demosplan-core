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

import { DpButton } from 'demosplan-ui/components'
import DpInlineNotification from '@DemosPlanCoreBundle/components/DpInlineNotification'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpButton,
  DpInlineNotification
}

initialize(components, {}, [])
