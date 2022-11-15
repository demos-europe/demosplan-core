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

import { DpEditor, DpUploadFiles } from 'demosplan-ui/components/core'
import { dpValidate } from 'demosplan-utils/lib/validation'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpEditor,
  DpUploadFiles
}

initialize(components).then(() => {
  dpValidate()
})
