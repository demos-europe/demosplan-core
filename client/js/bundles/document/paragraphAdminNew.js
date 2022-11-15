/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for paragraph_admin_new.html.twig
 */

import DpEditor from '@DpJs/components/core/DpEditor/DpEditor'
import dpValidate from 'demosplan-utils/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpEditor
}

initialize(components).then(() => {
  dpValidate()
})
