/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for paragraph_admin_edit.html.twig
 */

import DpEditor from '@DpJs/components/core/DpEditor/DpEditor'
import DpUploadFiles from '@DpJs/components/core/DpUpload/DpUploadFiles'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpEditor,
  DpUploadFiles
}

initialize(components).then(() => {
  dpValidate()
})
