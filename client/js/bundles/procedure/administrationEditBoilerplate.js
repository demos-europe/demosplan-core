/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_edit_boilerplate.html.twig
 */

import { DpEditor, DpMultiselect, dpValidate } from '@demos-europe/demosplan-ui'
import DpEditBoilerplate from '@DpJs/components/procedure/admin/DpEditBoilerplate'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpEditBoilerplate,
  DpEditor,
  DpMultiselect
}

initialize(components).then(() => {
  dpValidate()
})
