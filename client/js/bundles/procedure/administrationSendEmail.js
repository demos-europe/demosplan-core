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
import DpEditor from '@DpJs/components/core/DpEditor/DpEditor'
import { dpValidate } from 'demosplan-utils/lib/validation'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { DpCheckbox, DpEditor }

initialize(components).then(() => {
  dpValidate()
})
