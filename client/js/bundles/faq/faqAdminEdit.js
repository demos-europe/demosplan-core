/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for faq_admin_edit.html.twig
 */

import { DpEditor } from '@demos-europe/demosplan-ui/components/core'
import { dpValidate } from '@demos-europe/demosplan-utils/lib/validation'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpEditor
}

initialize(components).then(() => {
  dpValidate()
})
