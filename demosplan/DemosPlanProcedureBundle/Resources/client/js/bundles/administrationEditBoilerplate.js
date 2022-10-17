/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_edit_boilerplate.html.twig
 */

import DpEditBoilerplate from '../components/admin/DpEditBoilerplate'
import DpEditor from '@DpJs/components/core/DpEditor/DpEditor'
import DpMultiselect from '@DpJs/components/core/form/DpMultiselect'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpEditBoilerplate,
  DpEditor,
  DpMultiselect
}

initialize(components).then(() => {
  dpValidate()
})
