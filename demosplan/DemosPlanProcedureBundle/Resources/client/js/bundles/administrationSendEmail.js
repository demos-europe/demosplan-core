/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_send_email.html.twig
 */

import DpCheckbox from '@DpJs/components/core/form/DpCheckbox'
import DpTiptap from '@DpJs/components/core/DpTiptap'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { DpCheckbox, DpTiptap }

initialize(components).then(() => {
  dpValidate()
})
