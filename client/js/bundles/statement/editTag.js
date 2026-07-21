/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for edit_tag.html.twig
 */

import { DpEditor, DpMultiselect, DpRadio, dpValidate } from '@demos-europe/demosplan-ui'
import EditTag from '@DpJs/components/tags/EditTag'
import { initialize } from '@DpJs/InitVue'

const components = { DpEditor, DpMultiselect, DpRadio, EditTag }

initialize(components).then(() => {
  dpValidate()
})
