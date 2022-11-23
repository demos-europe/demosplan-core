/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import dpValidate from '@demos-europe/demosplan-utils/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpEditor: async () => {
    const { DpEditor } = await import('@demos-europe/demosplan-ui/components/core')
    return DpEditor
  }
}

initialize(components).then(() => {
  dpValidate()
})
