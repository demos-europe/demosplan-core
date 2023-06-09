/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for paragraph_admin_edit.html.twig
 */

import { DpEditor, DpUploadFiles, dpValidate } from '@demos-europe/demosplan-ui/src'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpEditor,
  DpUploadFiles
}

initialize(components).then(() => {
  dpValidate()
})
