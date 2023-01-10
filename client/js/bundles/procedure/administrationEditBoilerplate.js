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

import DpEditBoilerplate from '@DpJs/components/procedure/admin/DpEditBoilerplate'
import { DpEditor, DpMultiselect } from '@demos-europe/demosplan-ui'
import dpValidate from '@demos-europe/demosplan-utils/lib/validation/dpValidate'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpEditBoilerplate,
  DpEditor,
  DpMultiselect
}

initialize(components).then(() => {
  dpValidate()
})
