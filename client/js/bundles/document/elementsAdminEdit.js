/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for elements_admin_edit.html.twig
 */

import DpElementAdminEdit from '@DpJs/components/document/DpElementAdminEdit'
import { DpUploadFiles } from '@demos-europe/demosplan-ui'
import dpValidate from '@demos-europe/demosplan-utils/lib/validation/dpValidate'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpElementAdminEdit,
  DpUploadFiles
}

initialize(components).then(() => {
  dpValidate()
})
