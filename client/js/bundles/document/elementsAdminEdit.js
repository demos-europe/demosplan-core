/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for elements_admin_edit.html.twig
 */

import { DpUploadFiles, dpValidate } from '@demos-europe/demosplan-ui'
import DpElementAdminEdit from '@DpJs/components/document/DpElementAdminEdit'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpElementAdminEdit,
  DpUploadFiles
}

initialize(components).then(() => {
  dpValidate()
})
