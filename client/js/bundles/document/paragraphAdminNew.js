/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for paragraph_admin_new.html.twig
 */

import { DpEditor, dpValidate } from '@demos-europe/demosplan-ui/src'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpEditor
}

initialize(components).then(() => {
  dpValidate()
})
